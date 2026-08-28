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

    /** @return array{proof_path: string, proof_original_name: string, proof_mime: string, proof_size: int} */
    public function store(UploadedFile $file, string $folder): array
    {
        $mime = $file->getMimeType();
        if (!in_array($mime, self::ALLOWED_MIME, true)) {
            throw new \InvalidArgumentException("Unsupported proof file type ({$mime}). Use JPG, PNG, WEBP, or PDF.");
        }
        if ($file->getSize() > self::MAX_BYTES) {
            throw new \InvalidArgumentException('Proof file exceeds the 8MB size limit.');
        }

        $extension = match ($mime) {
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf',
        };
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
