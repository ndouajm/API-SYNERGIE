<?php

namespace App\Support;

use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class XlsxExport
{
    public static function download(string $filename, string $title, array $headers, iterable $rows): StreamedResponse
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException("L'extension PHP ZipArchive est nécessaire pour générer les exports Excel.");
        }

        return response()->streamDownload(function () use ($title, $headers, $rows) {
            $archivePath = tempnam(sys_get_temp_dir(), 'synergie-xlsx-');
            if ($archivePath === false) {
                throw new RuntimeException("Impossible de créer les fichiers temporaires de l'export Excel.");
            }
            $sheetPath = tempnam(sys_get_temp_dir(), 'synergie-sheet-');
            if ($sheetPath === false) {
                unlink($archivePath);
                throw new RuntimeException("Impossible de créer les fichiers temporaires de l'export Excel.");
            }

            try {
                self::writeWorksheet($sheetPath, $title, $headers, $rows);

                $archive = new ZipArchive;
                $result = $archive->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
                if ($result !== true) {
                    throw new RuntimeException("Impossible de créer le classeur Excel.");
                }

                $files = [
                    '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                        .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                        .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                        .'<Default Extension="xml" ContentType="application/xml"/>'
                        .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                        .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                        .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
                        .'</Types>',
                    '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                        .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                        .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                        .'</Relationships>',
                    'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                        .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                        .'<sheets><sheet name="Adhésions" sheetId="1" r:id="rId1"/></sheets>'
                        .'</workbook>',
                    'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                        .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                        .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                        .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
                        .'</Relationships>',
                    'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                        .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                        .'<fonts count="3"><font><sz val="11"/><name val="Calibri"/></font>'
                        .'<font><b/><sz val="16"/><name val="Calibri"/></font>'
                        .'<font><b/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>'
                        .'<fills count="3"><fill><patternFill patternType="none"/></fill>'
                        .'<fill><patternFill patternType="gray125"/></fill>'
                        .'<fill><patternFill patternType="solid"><fgColor rgb="FF17457A"/><bgColor indexed="64"/></patternFill></fill></fills>'
                        .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
                        .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
                        .'<cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
                        .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
                        .'<xf numFmtId="0" fontId="2" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs>'
                        .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
                        .'</styleSheet>',
                ];
                foreach ($files as $path => $contents) {
                    if (! $archive->addFromString($path, $contents)) {
                        throw new RuntimeException("Impossible d'écrire le fichier {$path} dans le classeur Excel.");
                    }
                }
                if (! $archive->addFile($sheetPath, 'xl/worksheets/sheet1.xml')) {
                    throw new RuntimeException("Impossible d'ajouter les données au classeur Excel.");
                }
                if (! $archive->close()) {
                    throw new RuntimeException("Impossible de finaliser le classeur Excel.");
                }

                if (readfile($archivePath) === false) {
                    throw new RuntimeException("Impossible de transmettre le fichier Excel généré.");
                }
            } finally {
                @unlink($archivePath);
                @unlink($sheetPath);
            }
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private static function writeWorksheet(string $path, string $title, array $headers, iterable $rows): void
    {
        $output = fopen($path, 'wb');
        if ($output === false) {
            throw new RuntimeException("Impossible d'écrire les données de l'export Excel.");
        }

        try {
            $columnCount = count($headers);
            $lastColumn = self::columnName($columnCount);
            fwrite($output, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="2" topLeftCell="A3" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
                .'<sheetFormatPr defaultRowHeight="18"/><sheetData>');

            fwrite($output, '<row r="1"><c r="A1" s="1" t="inlineStr"><is><t>'.self::escape($title).'</t></is></c></row>');
            fwrite($output, '<row r="2">');
            foreach ($headers as $index => $header) {
                fwrite($output, self::stringCell(self::columnName($index + 1).'2', (string) $header, 2));
            }
            fwrite($output, '</row>');

            $rowNumber = 2;
            foreach ($rows as $row) {
                $rowNumber++;
                fwrite($output, '<row r="'.$rowNumber.'">');
                foreach (array_values($row) as $index => $value) {
                    $reference = self::columnName($index + 1).$rowNumber;
                    if (is_int($value) || is_float($value)) {
                        fwrite($output, '<c r="'.$reference.'"><v>'.$value.'</v></c>');
                    } else {
                        fwrite($output, self::stringCell($reference, (string) ($value ?? '')));
                    }
                }
                fwrite($output, '</row>');
            }
            fwrite($output, '</sheetData><autoFilter ref="A2:'.$lastColumn.max(2, $rowNumber).'"/>');
            fwrite($output, '<mergeCells count="1"><mergeCell ref="A1:'.$lastColumn.'1"/></mergeCells></worksheet>');
        } finally {
            fclose($output);
        }
    }

    private static function stringCell(string $reference, string $value, int $style = 0): string
    {
        return '<c r="'.$reference.'"'.($style ? ' s="'.$style.'"' : '').' t="inlineStr"><is><t xml:space="preserve">'
            .self::escape($value).'</t></is></c>';
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function columnName(int $index): string
    {
        $column = '';
        while ($index > 0) {
            $index--;
            $column = chr(65 + ($index % 26)).$column;
            $index = intdiv($index, 26);
        }

        return $column;
    }
}
