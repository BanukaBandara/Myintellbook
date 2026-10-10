<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A base64 data URI that really is a PNG, JPEG or WebP image within a size limit.
 * The declared type alone isn't trusted: the bytes are decoded and inspected, so SVG
 * (which can carry scripts) and non-image payloads are rejected.
 */
class Base64Image implements ValidationRule
{
    private const MIME_BY_SUBTYPE = [
        'png' => 'image/png',
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'webp' => 'image/webp',
    ];

    public function __construct(private readonly int $maxBytes = 2 * 1024 * 1024) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value)
            || !preg_match('#^data:image/(png|jpe?g|webp);base64,([A-Za-z0-9+/=\r\n]+)$#', $value, $matches)) {
            $fail('The image must be a PNG, JPEG or WebP file.');
            return;
        }

        $bytes = base64_decode($matches[2], true);
        if ($bytes === false || $bytes === '') {
            $fail('The image could not be read.');
            return;
        }

        if (strlen($bytes) > $this->maxBytes) {
            $fail('The image must not be larger than '.round($this->maxBytes / 1048576, 1).' MB.');
            return;
        }

        $info = @getimagesizefromstring($bytes);
        $expected = self::MIME_BY_SUBTYPE[$matches[1]];
        if ($info === false || ($info['mime'] ?? null) !== $expected) {
            $fail('The image content does not match its type.');
        }
    }
}
