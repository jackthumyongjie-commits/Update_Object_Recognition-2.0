<?php

declare(strict_types=1);

/**
 * Validates and normalizes Base64 image payloads from the client.
 */
final class ImageValidator
{
    private const MAX_BYTES = 4_000_000;
    private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * @return array{ok:true,binary:string,mime:string}|array{ok:false,error:string}
     */
    public function validate(string $imageInput): array
    {
        $raw = trim($imageInput);
        if ($raw === '') {
            return ['ok' => false, 'error' => 'Empty image data.'];
        }

        $mime = 'image/jpeg';
        if (preg_match('#^data:(image/[a-zA-Z0-9.+-]+);base64,#i', $raw, $matches)) {
            $mime = strtolower($matches[1]);
            $raw = substr($raw, strlen($matches[0]));
        }

        $raw = preg_replace('/\s+/', '', $raw) ?? '';
        if ($raw === '' || !preg_match('#^[A-Za-z0-9+/]+=*$#', $raw)) {
            return ['ok' => false, 'error' => 'Invalid Base64 image data.'];
        }

        $binary = base64_decode($raw, true);
        if ($binary === false || $binary === '') {
            return ['ok' => false, 'error' => 'Unable to decode image data.'];
        }

        if (strlen($binary) > self::MAX_BYTES) {
            return ['ok' => false, 'error' => 'Image is too large.'];
        }

        $detected = $this->detectMime($binary);
        if ($detected !== null) {
            $mime = $detected;
        }

        if (!in_array($mime, self::ALLOWED_MIME, true)) {
            return ['ok' => false, 'error' => 'Unsupported image type.'];
        }

        if (@getimagesizefromstring($binary) === false) {
            return ['ok' => false, 'error' => 'Image content is invalid.'];
        }

        return [
            'ok' => true,
            'binary' => $binary,
            'mime' => $mime,
        ];
    }

    private function detectMime(string $binary): ?string
    {
        if (strncmp($binary, "\xFF\xD8\xFF", 3) === 0) {
            return 'image/jpeg';
        }
        if (strncmp($binary, "\x89PNG\r\n\x1A\n", 8) === 0) {
            return 'image/png';
        }
        if (strncmp($binary, 'RIFF', 4) === 0 && substr($binary, 8, 4) === 'WEBP') {
            return 'image/webp';
        }

        return null;
    }
}
