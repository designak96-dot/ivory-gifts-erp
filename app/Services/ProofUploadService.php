<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores payment/expense proof files on the PRIVATE disk (storage/app/private,
 * not storage/app/public) — these are never reachable by a guessable public
 * URL. Access is only through ProofController, which checks the same
 * permission as viewing the parent payment/expense record.
 */
class ProofUploadService
{
    private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
    private const MAX_BYTES = 8 * 1024 * 1024; // 8MB
    private const EXTENSION_MAP = [
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf',
        'text/csv' => 'csv', 'text/plain' => 'csv', 'application/csv' => 'csv', 'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/zip' => 'xlsx', // some servers report XLSX (a zip container) with this generic mime
    ];

    /**
     * @param array|null $allowedMime Optional override of the accepted
     * mime types, for callers with different needs than the default
     * proof-image whitelist (e.g. bank statement uploads). Every
     * existing caller that doesn't pass this keeps the exact original
     * behavior unchanged.
     * @return array{proof_path: string, proof_original_name: string, proof_mime: string, proof_size: int}
     */
    public function store(UploadedFile $file, string $folder, ?array $allowedMime = null): array
    {
        $whitelist = $allowedMime ?? self::ALLOWED_MIME;
        $mime = $file->getMimeType();
        if (!in_array($mime, $whitelist, true)) {
            throw new \InvalidArgumentException("Unsupported file type ({$mime}). Use ".implode(', ', $whitelist).'.');
        }
        if ($file->getSize() > self::MAX_BYTES) {
            throw new \InvalidArgumentException('File exceeds the 8MB size limit.');
        }

        $extension = self::EXTENSION_MAP[$mime] ?? strtolower($file->getClientOriginalExtension()) ?: 'bin';
        $storedName = Str::random(24).'.'.$extension;
        $relative = "{$folder}/{$storedName}";
        Storage::disk('local')->putFileAs($folder, $file, $storedName);

        return [
            'proof_path' => $relative,
            'proof_original_name' => $file->getClientOriginalName(),
            'proof_mime' => $mime,
            'proof_size' => $file->getSize(),
        ];
    }
}
