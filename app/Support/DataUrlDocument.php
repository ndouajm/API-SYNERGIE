<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class DataUrlDocument
{
    private const MAX_BYTES = 2 * 1024 * 1024;

    public static function decode(?string $dataUrl, string $field): array
    {
        if (! is_string($dataUrl) || ! preg_match('/\Adata:(image\/(?:png|jpeg|webp)|application\/pdf);base64,([A-Za-z0-9+\/=]+)\z/', $dataUrl, $matches)) {
            throw ValidationException::withMessages([
                $field => 'Fournissez un justificatif PDF, PNG, JPEG ou WebP valide.',
            ]);
        }

        $contents = base64_decode($matches[2], true);
        if ($contents === false || strlen($contents) > self::MAX_BYTES) {
            throw ValidationException::withMessages([
                $field => 'Chaque justificatif ne doit pas dépasser 2 Mo.',
            ]);
        }

        if ($matches[1] === 'application/pdf') {
            if (! str_starts_with($contents, '%PDF-')) {
                throw ValidationException::withMessages([
                    $field => 'Le contenu du fichier PDF est invalide.',
                ]);
            }

            return ['contents' => $contents, 'extension' => 'pdf'];
        }

        $image = getimagesizefromstring($contents);
        $expectedMime = match ($matches[1]) {
            'image/png' => 'image/png',
            'image/jpeg' => 'image/jpeg',
            'image/webp' => 'image/webp',
        };
        if ($image === false || $image['mime'] !== $expectedMime) {
            throw ValidationException::withMessages([
                $field => 'Le contenu du fichier image est invalide.',
            ]);
        }

        return [
            'contents' => $contents,
            'extension' => $matches[1] === 'image/jpeg' ? 'jpg' : substr($matches[1], 6),
        ];
    }
}
