<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class DataUrlImage
{
    private const MAX_BYTES = 1024 * 1024;

    public static function decode(?string $dataUrl, string $field): array
    {
        if (! is_string($dataUrl) || ! preg_match('/\Adata:image\/(png|jpeg|webp);base64,([A-Za-z0-9+\/=]+)\z/', $dataUrl, $matches)) {
            throw ValidationException::withMessages([
                $field => 'Fournissez une image PNG, JPEG ou WebP valide.',
            ]);
        }

        $contents = base64_decode($matches[2], true);
        if ($contents === false || strlen($contents) > self::MAX_BYTES) {
            throw ValidationException::withMessages([
                $field => 'Chaque image ne doit pas dépasser 1 Mo.',
            ]);
        }

        $image = getimagesizefromstring($contents);
        $expectedMime = match ($matches[1]) {
            'png' => 'image/png',
            'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
        };

        if ($image === false || $image['mime'] !== $expectedMime) {
            throw ValidationException::withMessages([
                $field => 'Le contenu du fichier image est invalide.',
            ]);
        }

        return [
            'contents' => $contents,
            'extension' => $matches[1] === 'jpeg' ? 'jpg' : $matches[1],
        ];
    }
}
