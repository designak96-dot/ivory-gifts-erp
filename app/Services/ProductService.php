<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Single place for product creation/update logic — used by both the main
 * Products module and the Sales Order "+ Add product" popup, per the
 * explicit instruction not to maintain two different product forms/paths.
 * A future field change only needs to happen here and in one Blade partial.
 */
class ProductService
{
    public static function validationRules(?Product $existing = null): array
    {
        $productId = $existing?->id ?? 'NULL';
        return [
            'sku' => "nullable|string|max:40|alpha_dash|unique:products,sku,{$productId}",
            'barcode' => "nullable|string|max:80|unique:products,barcode,{$productId}",
            'name_en' => 'required|string|max:190',
            'name_ar' => 'nullable|string|max:190',
            'category_id' => 'nullable|exists:product_categories,id',
            'sale_price' => 'required|numeric|min:0',
            'cost_price' => 'required|numeric|min:0',
            'tax_rate_id' => 'nullable|exists:tax_rates,id',
            'unit' => 'required|string|max:30',
            'reorder_level' => 'nullable|numeric|min:0',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'production_time_days' => 'nullable|integer|min:0|max:365',
            'is_active' => 'nullable|boolean',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ];
    }

    public function create(array $data, ?UploadedFile $image): Product
    {
        $data['sku'] = ($data['sku'] ?? '') ?: $this->automaticSku();
        unset($data['image']);
        if ($image) {
            $data = array_merge($data, $this->storeImage($image));
        }
        return Product::create($data);
    }

    public function update(Product $product, array $data, ?UploadedFile $image): Product
    {
        if (empty($data['sku'] ?? null)) {
            $data['sku'] = $product->sku;
        }
        unset($data['image']);
        if ($image) {
            $data = array_merge($data, $this->storeImage($image));
        }
        $product->update($data);
        return $product;
    }

    /**
     * Short, readable, unique SKU — no "AUTO-" prefix, ~6 characters.
     * Crockford-ish alphabet (no ambiguous 0/O/1/I) to stay short while
     * remaining collision-safe via the existence check + retry loop.
     */
    public function automaticSku(): string
    {
        $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        do {
            $sku = '';
            for ($i = 0; $i < 6; $i++) $sku .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        } while (Product::where('sku', $sku)->exists());
        return $sku;
    }

    private function storeImage(UploadedFile $file): array
    {
        $mime = $file->getMimeType();
        $safeExt = match ($mime) { 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', default => null };
        abort_unless($safeExt, 422, 'Unsupported image type.');
        $filename = 'product_'.Str::random(12).'.'.$safeExt;
        Storage::disk('public')->putFileAs('products', $file, $filename);

        return [
            'image_path' => 'products/'.$filename,
            'thumbnail_path' => $this->makeThumbnail($file->getRealPath(), $mime, $filename),
        ];
    }

    private function makeThumbnail(string $sourcePath, string $mime, string $filename): ?string
    {
        $src = match ($mime) { 'image/jpeg' => @imagecreatefromjpeg($sourcePath), 'image/png' => @imagecreatefrompng($sourcePath), 'image/webp' => @imagecreatefromwebp($sourcePath), default => null };
        if (!$src) return null;
        $w = imagesx($src); $h = imagesy($src); $tw = 300; $th = (int) round($h * ($tw / $w));
        $thumb = imagecreatetruecolor($tw, $th);
        if ($mime === 'image/png') { imagealphablending($thumb, false); imagesavealpha($thumb, true); }
        imagecopyresampled($thumb, $src, 0, 0, 0, 0, $tw, $th, $w, $h);
        $relative = 'products/thumb_'.$filename;
        $dest = Storage::disk('public')->path($relative);
        @mkdir(dirname($dest), 0775, true);
        match ($mime) { 'image/jpeg' => imagejpeg($thumb, $dest, 82), 'image/png' => imagepng($thumb, $dest), 'image/webp' => imagewebp($thumb, $dest, 82), default => null };
        imagedestroy($src); imagedestroy($thumb);
        return $relative;
    }
}
