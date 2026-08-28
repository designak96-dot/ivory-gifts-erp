<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductImport;
use App\Models\ProductImportRow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Imports the real WhatsApp-order-form product export: a flat JSON array of
 * {id, category, name, name_ar, price, min_qty, sort_order, active, image}
 * plus a folder/zip of images referenced by basename (the "uploads/" prefix
 * in the JSON's image path does not match the zip's actual folder layout —
 * confirmed by inspecting both files directly, so matching is done by
 * basename only, not full path).
 *
 * "id" in the source JSON is the stable source identifier used for
 * idempotent upsert — NOT the internal product SKU. A SKU is always
 * generated internally (via NumberingService-style sequential code) and
 * never required from the person running the import, matching "no
 * mandatory visible SKU".
 */
class ProductImportService
{
    private const MAX_ZIP_UNCOMPRESSED_BYTES = 500 * 1024 * 1024; // 500MB safety cap
    private const MAX_ZIP_FILE_COUNT = 5000;
    private const ALLOWED_IMAGE_MIME = ['image/jpeg', 'image/png', 'image/webp'];
    private const MAX_IMAGE_BYTES = 8 * 1024 * 1024;

    /**
     * @param string $path absolute path to the uploaded file
     * @param string $extension 'json' or 'csv'
     */
    public function parseFile(string $path, string $extension): array
    {
        $extension = strtolower($extension);
        if ($extension === 'json') {
            return $this->parseJson($path);
        }
        if (in_array($extension, ['csv', 'txt'], true)) {
            return $this->parseCsv($path);
        }
        throw new \RuntimeException("Unsupported product import file extension: {$extension}. Use CSV, JSON, or a ZIP containing either.");
    }

    private function parseCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        $headers = array_map(fn ($h) => strtolower(trim($h)), fgetcsv($handle));
        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            if ($row === [null]) continue;
            $assoc = array_combine($headers, array_pad($row, count($headers), null));
            // Normalize the bulk-import CSV's field names onto the same
            // shape preview()/commit() already understand.
            $rows[] = [
                'sku' => $assoc['sku'] ?? null,
                'id' => $assoc['sku'] ?? null, // sku doubles as the stable match key for this format
                'name' => $assoc['name'] ?? '',
                'category' => $assoc['category'] ?? null,
                'description' => $assoc['description'] ?? null,
                'cost_price' => $assoc['cost_price'] ?? null,
                'price' => $assoc['selling_price'] ?? $assoc['price'] ?? 0,
                'vat_rate' => $assoc['vat_rate'] ?? null,
                'stock_quantity' => $assoc['stock_quantity'] ?? null,
                'unit' => $assoc['unit'] ?? null,
                'image' => $assoc['image_filename'] ?? $assoc['image'] ?? null,
                'active' => isset($assoc['active']) ? in_array(strtolower((string) $assoc['active']), ['1', 'true', 'yes', 'active'], true) : true,
            ];
        }
        fclose($handle);
        return $rows;
    }

    /** A downloadable starting point so future imports are formatted correctly from the start. */
    public function csvTemplate(): string
    {
        $header = "sku,name,category,description,cost_price,selling_price,vat_rate,stock_quantity,unit,image_filename,active\n";
        $example = "CUP-001,Ceramic Cup,Cups,Personalized ceramic cup,10,25,5,100,pcs,CUP-001.jpg,true\n";
        return $header.$example;
    }

    public function jsonTemplate(): string
    {
        return json_encode([[
            'sku' => 'CUP-001', 'name' => 'Ceramic Cup', 'category' => 'Cups',
            'description' => 'Personalized ceramic cup', 'cost_price' => 10, 'selling_price' => 25,
            'vat_rate' => 5, 'stock_quantity' => 100, 'unit' => 'pcs',
            'image_filename' => 'CUP-001.jpg', 'active' => true,
        ]], JSON_PRETTY_PRINT);
    }

    /**
     * @param string $jsonPath absolute path to products.json (already extracted or uploaded directly)
     * @param string|null $imagesDir absolute path to the directory containing referenced images (already safely extracted), or null if none supplied
     * @return array{rows: array, missingImages: array, categories: array}
     */
    public function preview(string $filePath, ?string $imagesDir, string $extension = 'json'): array
    {
        $items = $this->parseFile($filePath, $extension);
        $rows = [];
        $missingImages = [];

        foreach ($items as $item) {
            $sourceId = (string) ($item['id'] ?? '');
            $imageBasename = $item['image'] ?? null ? basename($item['image']) : null;
            $imageExists = $imageBasename && $imagesDir && is_file($imagesDir.'/'.$imageBasename);

            if ($imageBasename && !$imageExists) {
                $missingImages[] = ['source_id' => $sourceId, 'name' => $item['name'] ?? '', 'expected_file' => $imageBasename];
            }

            $existing = $sourceId !== '' ? Product::where('source_id', $sourceId)->first() : null;

            $rows[] = [
                'source_id' => $sourceId,
                'name' => $item['name'] ?? '',
                'name_ar' => $item['name_ar'] ?? null,
                'category' => $item['category'] ?? null,
                'price' => (float) ($item['price'] ?? 0),
                'min_qty' => (int) ($item['min_qty'] ?? 1),
                'sort_order' => (int) ($item['sort_order'] ?? 0),
                'active' => (bool) ($item['active'] ?? true),
                'image_basename' => $imageBasename,
                'image_found' => $imageExists,
                'action' => $existing ? 'update' : 'create',
            ];
        }

        return [
            'rows' => $rows,
            'missing_images' => $missingImages,
            'total' => count($rows),
        ];
    }

    /**
     * Commits the import inside a transaction per chunk (not one giant
     * transaction across all 84+ rows) so a failure partway through a large
     * catalogue doesn't force an all-or-nothing rollback of everything
     * already validated as good in earlier chunks.
     */
    public function commit(string $filePath, ?string $imagesDir, int $userId, bool $isDryRun = false, string $extension = 'json'): ProductImport
    {
        $items = $this->parseFile($filePath, $extension);

        $import = ProductImport::create([
            'status' => 'pending',
            'is_dry_run' => $isDryRun,
            'total_rows' => count($items),
            'created_by' => $userId,
        ]);

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = 0;
        $missingImageCount = 0;

        foreach (array_chunk($items, 25) as $chunk) {
            DB::transaction(function () use ($chunk, $imagesDir, $import, $isDryRun, &$created, &$updated, &$skipped, &$errors, &$missingImageCount) {
                foreach ($chunk as $item) {
                    $sourceId = trim((string) ($item['id'] ?? ''));
                    $name = trim((string) ($item['name'] ?? ''));

                    if ($sourceId === '' || $name === '') {
                        $skipped++;
                        ProductImportRow::create([
                            'product_import_id' => $import->id,
                            'source_id' => $sourceId ?: null,
                            'name' => $name ?: null,
                            'outcome' => 'skipped',
                            'message' => 'Missing required id or name — row ignored rather than guessed.',
                        ]);
                        continue;
                    }

                    try {
                        $categoryId = $this->resolveCategory($item['category'] ?? null);
                        $imagePaths = $this->resolveImage($item['image'] ?? null, $imagesDir, $isDryRun, $missingImageCount);

                        // Bulk CSV/JSON format (sku present) matches by SKU
                        // per that format's explicit spec: "Existing SKU ->
                        // update, New SKU -> create". The WhatsApp-export
                        // format (no sku field, just id) keeps matching by
                        // source_id as before — these are two different
                        // import sources with two different natural keys.
                        $sku = trim((string) ($item['sku'] ?? ''));
                        $matchBySku = $sku !== '';
                        $existing = $matchBySku
                            ? Product::where('sku', $sku)->first()
                            : Product::where('source_id', $sourceId)->first();

                        if ($isDryRun) {
                            // Dry run: validate everything above, write nothing.
                            $existing ? $updated++ : $created++;
                            ProductImportRow::create([
                                'product_import_id' => $import->id,
                                'source_id' => $sourceId,
                                'name' => $name,
                                'outcome' => $existing ? 'updated' : 'created',
                                'message' => 'Dry run — validated only, not written.',
                            ]);
                            continue;
                        }

                        $payload = [
                            'source_id' => $sourceId,
                            'name_en' => $name,
                            'name_ar' => $item['name_ar'] ?? null,
                            'category_id' => $categoryId,
                            'sale_price' => (float) ($item['price'] ?? 0),
                            'min_qty' => max(1, (int) ($item['min_qty'] ?? 1)),
                            'sort_order' => (int) ($item['sort_order'] ?? 0),
                            'is_active' => (bool) ($item['active'] ?? true),
                        ];
                        if (isset($item['cost_price']) && $item['cost_price'] !== null && $item['cost_price'] !== '') {
                            $payload['cost_price'] = (float) $item['cost_price'];
                        } elseif (!$existing) {
                            $payload['cost_price'] = 0;
                        }
                        if (isset($item['description']) && $item['description'] !== null) {
                            $payload['description'] = $item['description'];
                        }
                        if (isset($item['unit']) && $item['unit']) {
                            $payload['unit'] = $item['unit'];
                        } elseif (!$existing) {
                            $payload['unit'] = 'piece';
                        }
                        // Never overwrite an existing image with a blank one
                        // — only apply new image paths when this row
                        // actually resolved a real image.
                        if ($imagePaths) {
                            $payload['image_path'] = $imagePaths['image'];
                            $payload['thumbnail_path'] = $imagePaths['thumbnail'];
                        }
                        if ($matchBySku) {
                            $payload['sku'] = $sku;
                        } elseif (!$existing) {
                            $payload['sku'] = $this->generateInternalSku();
                        }

                        $matchKey = $matchBySku ? ['sku' => $sku] : ['source_id' => $sourceId];
                        Product::updateOrCreate($matchKey, $payload);

                        $existing ? $updated++ : $created++;
                        ProductImportRow::create([
                            'product_import_id' => $import->id,
                            'source_id' => $sourceId,
                            'name' => $name,
                            'outcome' => $existing ? 'updated' : 'created',
                            'message' => null,
                        ]);
                    } catch (\Throwable $e) {
                        $errors++;
                        ProductImportRow::create([
                            'product_import_id' => $import->id,
                            'source_id' => $sourceId ?: null,
                            'name' => $name ?: null,
                            'outcome' => 'error',
                            'message' => $e->getMessage(),
                        ]);
                    }
                }
            });
        }

        $import->update([
            'status' => 'completed',
            'created_count' => $created,
            'updated_count' => $updated,
            'skipped_count' => $skipped,
            'error_count' => $errors,
            'missing_image_count' => $missingImageCount,
        ]);

        return $import;
    }

    private function parseJson(string $jsonPath): array
    {
        if (!is_file($jsonPath)) {
            throw new \RuntimeException("products.json not found at expected path.");
        }
        $raw = file_get_contents($jsonPath);
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new \RuntimeException('products.json did not decode to a JSON array — refusing to guess its structure.');
        }
        return $data;
    }

    private function resolveCategory(?string $name): ?int
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }
        return ProductCategory::firstOrCreate(['name_en' => $name])->id;
    }

    /**
     * @return array{image: string, thumbnail: string}|null
     */
    private function resolveImage(?string $imageRef, ?string $imagesDir, bool $isDryRun, int &$missingImageCount): ?array
    {
        if (!$imageRef) {
            return null;
        }
        $basename = basename($imageRef); // strip any path — matched by filename only, see class docblock
        if ($basename !== $imageRef && str_contains($imageRef, '..')) {
            throw new \RuntimeException("Rejected image reference containing path traversal sequence: {$imageRef}");
        }

        if (!$imagesDir || !is_file($imagesDir.'/'.$basename)) {
            $missingImageCount++;
            return null; // Missing-image report handles this — product still imports, just without a photo.
        }

        if ($isDryRun) {
            return ['image' => 'products/'.$basename, 'thumbnail' => 'products/thumb_'.$basename];
        }

        $sourcePath = $imagesDir.'/'.$basename;
        $mime = mime_content_type($sourcePath);
        if (!in_array($mime, self::ALLOWED_IMAGE_MIME, true)) {
            throw new \RuntimeException("Rejected image with disallowed MIME type ({$mime}): {$basename}");
        }
        if (filesize($sourcePath) > self::MAX_IMAGE_BYTES) {
            throw new \RuntimeException("Rejected image exceeding size limit: {$basename}");
        }

        $safeBasename = Str::slug(pathinfo($basename, PATHINFO_FILENAME)).'.'.pathinfo($basename, PATHINFO_EXTENSION);
        $storedRelative = 'products/'.$safeBasename;
        Storage::disk('public')->put($storedRelative, file_get_contents($sourcePath));

        $thumbRelative = 'products/thumb_'.$safeBasename;
        $this->makeThumbnail($sourcePath, Storage::disk('public')->path($thumbRelative), $mime);

        return ['image' => $storedRelative, 'thumbnail' => $thumbRelative];
    }

    /**
     * GD-based thumbnailing — no Composer image package required, GD ships
     * with the PHP install already confirmed present on this server.
     */
    private function makeThumbnail(string $sourcePath, string $destPath, string $mime): void
    {
        $src = match ($mime) {
            'image/jpeg' => imagecreatefromjpeg($sourcePath),
            'image/png' => imagecreatefrompng($sourcePath),
            'image/webp' => imagecreatefromwebp($sourcePath),
            default => null,
        };
        if (!$src) {
            return;
        }

        $width = imagesx($src);
        $height = imagesy($src);
        $targetWidth = 300;
        $targetHeight = (int) round($height * ($targetWidth / $width));

        $thumb = imagecreatetruecolor($targetWidth, $targetHeight);
        if ($mime === 'image/png') {
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
        }
        imagecopyresampled($thumb, $src, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        @mkdir(dirname($destPath), 0775, true);
        match ($mime) {
            'image/jpeg' => imagejpeg($thumb, $destPath, 82),
            'image/png' => imagepng($thumb, $destPath),
            'image/webp' => imagewebp($thumb, $destPath, 82),
            default => null,
        };

        imagedestroy($src);
        imagedestroy($thumb);
    }

    private function generateInternalSku(): string
    {
        do {
            $sku = 'P'.strtoupper(Str::random(6));
        } while (Product::where('sku', $sku)->exists());
        return $sku;
    }

    /**
     * Safely extracts a ZIP into a fresh temp directory: rejects entries with
     * path-traversal sequences or absolute paths, rejects excessive file
     * count, and caps total uncompressed size to guard against zip bombs.
     */
    public function safeExtractZip(string $zipPath): string
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('Could not open the uploaded ZIP file.');
        }

        if ($zip->numFiles > self::MAX_ZIP_FILE_COUNT) {
            $zip->close();
            throw new \RuntimeException('ZIP contains too many files — rejected as a safety precaution.');
        }

        $totalUncompressed = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = $stat['name'];

            if (str_contains($name, '..') || str_starts_with($name, '/') || preg_match('#^[a-zA-Z]:\\\\#', $name)) {
                $zip->close();
                throw new \RuntimeException("Rejected ZIP entry with unsafe path: {$name}");
            }

            $totalUncompressed += $stat['size'];
            if ($totalUncompressed > self::MAX_ZIP_UNCOMPRESSED_BYTES) {
                $zip->close();
                throw new \RuntimeException('ZIP exceeds the maximum allowed uncompressed size — rejected as a safety precaution (possible zip bomb).');
            }
        }

        $destination = storage_path('app/private/import-tmp/'.Str::uuid());
        mkdir($destination, 0775, true);
        $zip->extractTo($destination);
        $zip->close();

        return $destination;
    }
}
