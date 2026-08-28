<?php

namespace App\Http\Controllers;

use App\Models\ProductImport;
use App\Services\ProductImportService;
use Illuminate\Http\Request;

class ProductImportController extends Controller
{
    public function __construct(private ProductImportService $service) {}

    public function index()
    {
        $imports = ProductImport::with('rows')->latest()->paginate(20);
        return view('products.import-history', compact('imports'));
    }

    public function create()
    {
        return view('products.import');
    }

    /**
     * Accepts products.json or products.csv alone, or one ZIP containing
     * either plus its image folder — never fetches a remote image
     * automatically.
     */
    private function resolveUploadedInputs(Request $request): array
    {
        $request->validate([
            'source' => 'required|in:json,csv,zip',
            'json_file' => 'required_if:source,json|file|mimes:json,txt|max:10240',
            'csv_file' => 'required_if:source,csv|file|mimes:csv,txt|max:10240',
            'zip_file' => 'required_if:source,zip|file|mimes:zip|max:512000',
        ]);

        if ($request->input('source') === 'json') {
            return [$request->file('json_file')->getRealPath(), null, 'json'];
        }
        if ($request->input('source') === 'csv') {
            return [$request->file('csv_file')->getRealPath(), null, 'csv'];
        }

        $extractedDir = $this->service->safeExtractZip($request->file('zip_file')->getRealPath());

        $dataPath = $this->findFile($extractedDir, 'products.json');
        $extension = 'json';
        if (!$dataPath) {
            $dataPath = $this->findFile($extractedDir, 'products.csv');
            $extension = 'csv';
        }
        if (!$dataPath) {
            throw new \RuntimeException('The ZIP did not contain a products.json or products.csv file at any depth.');
        }

        $imagesDir = $this->findImagesDir($extractedDir);

        return [$dataPath, $imagesDir, $extension];
    }

    public function preview(Request $request)
    {
        [$path, $imagesDir, $extension] = $this->resolveUploadedInputs($request);
        $preview = $this->service->preview($path, $imagesDir, $extension);

        session(['product_import_path' => $path, 'product_import_images_dir' => $imagesDir, 'product_import_extension' => $extension]);

        return view('products.import-preview', ['preview' => $preview]);
    }

    public function dryRun(Request $request)
    {
        $path = session('product_import_path');
        $imagesDir = session('product_import_images_dir');
        $extension = session('product_import_extension', 'json');
        abort_unless($path, 419, 'Preview session expired — please re-upload the file.');

        $import = $this->service->commit($path, $imagesDir, auth()->id(), isDryRun: true, extension: $extension);

        return redirect()->route('products.import.history')->with('success', "Dry run complete: {$import->created_count} would be created, {$import->updated_count} would be updated, {$import->skipped_count} skipped, {$import->error_count} errors.");
    }

    public function commit(Request $request)
    {
        $path = session('product_import_path');
        $imagesDir = session('product_import_images_dir');
        $extension = session('product_import_extension', 'json');
        abort_unless($path, 419, 'Preview session expired — please re-upload the file.');

        $import = $this->service->commit($path, $imagesDir, auth()->id(), isDryRun: false, extension: $extension);

        session()->forget(['product_import_path', 'product_import_images_dir', 'product_import_extension']);

        return redirect()->route('products.import.history')->with('success', "Import complete: {$import->created_count} created, {$import->updated_count} updated, {$import->skipped_count} skipped, {$import->error_count} errors, {$import->missing_image_count} missing images.");
    }

    public function csvTemplate()
    {
        return response($this->service->csvTemplate(), 200, [
            'Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="product-import-template.csv"',
        ]);
    }

    public function jsonTemplate()
    {
        return response($this->service->jsonTemplate(), 200, [
            'Content-Type' => 'application/json', 'Content-Disposition' => 'attachment; filename="product-import-example.json"',
        ]);
    }

    private function findFile(string $dir, string $name): ?string
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->getFilename() === $name) {
                return $file->getPathname();
            }
        }
        return null;
    }

    private function findImagesDir(string $dir): ?string
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile() && in_array(strtolower($file->getExtension()), ['jpg', 'jpeg', 'png', 'webp'], true)) {
                return $file->getPath();
            }
        }
        return null;
    }
}
