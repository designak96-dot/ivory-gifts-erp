<?php

namespace App\Http\Controllers;

use App\Models\DataImport;
use App\Services\DataImportService;
use App\Services\SalesWorkflow;
use Illuminate\Http\Request;

class DataImportController extends Controller
{
    public function __construct(private DataImportService $service) {}

    public function create()
    {
        return view('imports.create');
    }

    public function history()
    {
        $imports = DataImport::with('rows')->latest()->paginate(20);
        return view('imports.history', compact('imports'));
    }

    private function resolveRows(Request $request): array
    {
        $request->validate([
            'type' => 'required|in:customers,orders',
            'file' => 'required|file|max:20480',
        ]);
        $extension = $request->file('file')->getClientOriginalExtension();
        return $this->service->parseFile($request->file('file')->getRealPath(), $extension);
    }

    public function preview(Request $request)
    {
        $rows = $this->resolveRows($request);
        $type = $request->input('type');

        $preview = $type === 'customers' ? $this->service->previewCustomers($rows) : $this->service->previewOrders($rows);

        session(['import_rows' => $rows, 'import_type' => $type]);

        return view('imports.preview', ['preview' => $preview, 'type' => $type]);
    }

    public function dryRun(Request $request, SalesWorkflow $workflow)
    {
        return $this->run($request, $workflow, isDryRun: true);
    }

    public function commit(Request $request, SalesWorkflow $workflow)
    {
        return $this->run($request, $workflow, isDryRun: false);
    }

    private function run(Request $request, SalesWorkflow $workflow, bool $isDryRun)
    {
        $rows = session('import_rows');
        $type = session('import_type');
        abort_unless($rows !== null, 419, 'Preview session expired — please re-upload the file.');

        $import = $type === 'customers'
            ? $this->service->commitCustomers($rows, auth()->id(), $isDryRun)
            : $this->service->commitOrders($rows, auth()->id(), $isDryRun, $workflow);

        if (!$isDryRun) {
            session()->forget(['import_rows', 'import_type']);
        }

        return redirect()->route('imports.history')->with('success', ($isDryRun ? 'Dry run' : 'Import').": {$import->created_count} created, {$import->updated_count} updated, {$import->conflict_count} conflicts (not overwritten), {$import->skipped_count} skipped, {$import->error_count} errors.");
    }

    public function errorReport(DataImport $import)
    {
        abort_unless($import->error_count > 0 || $import->conflict_count > 0, 404);
        $name = "import-{$import->id}-issues.csv";
        return response()->streamDownload(function () use ($import) {
            $out = fopen('php://output', 'w');
            fputs($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Source ID', 'Label', 'Outcome', 'Message']);
            $import->rows()->whereIn('outcome', ['error', 'conflict'])->each(fn ($row) => fputcsv($out, [$row->source_id, $row->label, $row->outcome, $row->message]));
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
