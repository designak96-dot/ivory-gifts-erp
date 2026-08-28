<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Handles logo/signature upload, replacement, and removal. Files are
 * validated by real MIME sniffing (not just the client-supplied extension),
 * capped in size, and SVGs are sanitized by stripping <script>, event
 * handler attributes, and external references before storage — never
 * stored as-received.
 */
class BrandingService
{
    private const ALLOWED_MIME = ['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'];
    private const MAX_BYTES = 3 * 1024 * 1024; // 3MB

    public function storeLogo(UploadedFile $file): string
    {
        return $this->storeImage($file, 'branding', 'logo');
    }

    public function storeSignature(UploadedFile $file): string
    {
        return $this->storeImage($file, 'branding', 'signature');
    }

    public function removeLogo(): void
    {
        $this->removeStored('logo_path');
    }

    public function removeSignature(): void
    {
        $this->removeStored('signature_path');
    }

    private function storeImage(UploadedFile $file, string $folder, string $prefix): string
    {
        $mime = $file->getMimeType(); // real content sniff, not the client-supplied extension
        if (!in_array($mime, self::ALLOWED_MIME, true)) {
            throw new \InvalidArgumentException("Unsupported file type ({$mime}). Use PNG, JPEG, WebP, or SVG.");
        }
        if ($file->getSize() > self::MAX_BYTES) {
            throw new \InvalidArgumentException('File exceeds the 3MB size limit.');
        }

        $extension = match ($mime) {
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
        };

        $contents = file_get_contents($file->getRealPath());
        if ($mime === 'image/svg+xml') {
            $contents = $this->sanitizeSvg($contents);
        }

        $filename = $prefix.'_'.Str::random(12).'.'.$extension;
        $relative = $folder.'/'.$filename;
        Storage::disk('public')->put($relative, $contents);

        return $relative;
    }

    /**
     * Strips the classic SVG attack surface: <script>, on* event handler
     * attributes, and external references via xlink:href/href to anything
     * other than a same-document fragment (#id). Not a full sanitizer for
     * every conceivable SVG feature, but removes the concrete XSS vectors.
     */
    private function sanitizeSvg(string $svg): string
    {
        $svg = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $svg);
        $svg = preg_replace('/\son\w+\s*=\s*"[^"]*"/i', '', $svg);
        $svg = preg_replace("/\son\w+\s*=\s*'[^']*'/i", '', $svg);
        $svg = preg_replace('/\s(xlink:href|href)\s*=\s*"(?!#)[^"]*"/i', '', $svg);
        $svg = preg_replace('#<foreignObject\b[^>]*>.*?</foreignObject>#is', '', $svg);

        if (!str_contains($svg, '<svg')) {
            throw new \InvalidArgumentException('Uploaded file is not a valid SVG.');
        }

        return $svg;
    }

    private function removeStored(string $settingKey): void
    {
        $current = Setting::value($settingKey);
        if ($current) {
            Storage::disk('public')->delete($current);
        }
        Setting::updateOrCreate(['key' => $settingKey], ['value' => null, 'group' => 'branding']);
    }
}
