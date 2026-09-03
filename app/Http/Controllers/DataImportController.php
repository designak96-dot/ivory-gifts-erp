<?php

namespace App\Http\Controllers;

use App\Models\DataImport;
use App\Services\{DataImportService, SalesWorkflow, SimpleWorkflowService};
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
            'type' => 'required|in:customers,orders,current_orders',
            'file' => 'required|file|max:20480',
        ]);
        $file = $request->file('file');
        $extension = $file->getClientOriginalExtension();
        $hash = $this->service->fileHash($file->getRealPath());

        // Duplicate-import protection: the same exact file, re-uploaded,
        // is a real and common accident during migration — warn rather
        // than silently duplicate every record in it.
        $priorImport = DataImport::where('file_hash', $hash)->where('status', 'completed')->where('is_dry_run', false)->latest()->first();
        if ($priorImport) {
            session(['import_duplicate_warning' => "This exact file was already imported on {$priorImport->created_at->format('d M Y H:i')} (import #{$priorImport->id}) — re-importing it will create duplicate records unless the source data has genuinely changed."]);
        } else {
            session()->forget('import_duplicate_warning');
        }

        session(['import_file_hash' => $hash, 'import_original_filename' => $file->getClientOriginalName()]);

        return $this->service->parseFile($file->getRealPath(), $extension);
    }

    public function preview(Request $request)
    {
        $rows = $this->resolveRows($request);
        $type = $request->input('type');

        $preview = match ($type) {
            'customers' => $this->service->previewCustomers($rows),
            'current_orders' => $this->service->previewCurrentOrders($rows),
            default => $this->service->previewOrders($rows),
        };

        session(['import_rows' => $rows, 'import_type' => $type]);

        return view('imports.preview', ['preview' => $preview, 'type' => $type, 'duplicateWarning' => session('import_duplicate_warning')]);
    }

    public function dryRun(Request $request, SalesWorkflow $workflow, SimpleWorkflowService $simpleWorkflow)
    {
        return $this->run($request, $workflow, $simpleWorkflow, isDryRun: true);
    }

    public function commit(Request $request, SalesWorkflow $workflow, SimpleWorkflowService $simpleWorkflow)
    {
        return $this->run($request, $workflow, $simpleWorkflow, isDryRun: false);
    }

    private function run(Request $request, SalesWorkflow $workflow, SimpleWorkflowService $simpleWorkflow, bool $isDryRun)
    {
        $rows = session('import_rows');
        $type = session('import_type');
        abort_unless($rows !== null, 419, 'Preview session expired — please re-upload the file.');

        $import = match ($type) {
            'customers' => $this->service->commitCustomers($rows, auth()->id(), $isDryRun),
            'current_orders' => $this->service->commitCurrentOrders($rows, auth()->id(), $isDryRun, $workflow, $simpleWorkflow),
            default => $this->service->commitOrders($rows, auth()->id(), $isDryRun, $workflow),
        };

        if (!$isDryRun) {
            $import->update(['file_hash' => session('import_file_hash'), 'original_filename' => session('import_original_filename')]);
            session()->forget(['import_rows', 'import_type', 'import_file_hash', 'import_original_filename', 'import_duplicate_warning']);
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

    public function template(string $type)
    {
        $templates = [
            'customers' => [
                'headers' => ['source_id', 'name', 'company_name', 'phone', 'whatsapp', 'email', 'emirate', 'area', 'notes', 'status'],
                'rows' => [['', 'Ahmed Al Mansoori', '', '+971501234567', '', 'ahmed@example.com', 'Abu Dhabi', 'Al Reem Island', 'VIP customer', 'active']],
            ],
            'current_orders' => [
                'headers' => ['manual_reference', 'customer_name', 'customer_phone', 'order_date', 'delivery_date', 'emirate', 'description', 'qty', 'unit_price', 'tax_rate', 'status', 'confirmation', 'design', 'paid_amount', 'notes'],
                'rows' => [['500', 'Ahmed Al Mansoori', '+971501234567', now()->toDateString(), now()->addDays(4)->toDateString(), 'Abu Dhabi', '3D Acrylic Name Sign', '1', '300', '5', 'pending', 'waiting_deposit', 'need_designer', '150', 'Deposit received']],
            ],
            'orders' => [
                'headers' => ['source_order_number', 'customer_name', 'customer_phone', 'order_date', 'delivery_date', 'emirate', 'item_description', 'item_qty', 'item_price', 'delivery_charge', 'vat_amount', 'total_payable', 'paid_amount', 'payment_status', 'confirmation_status', 'design_status', 'notes'],
                'rows' => [
                    ['OLD-1001', 'Ahmed Al Mansoori', '+971501234567', '2025-06-01', '2025-06-05', 'Abu Dhabi', '3D Name Sign - Vertical', '1', '400', '20', '20', '450', '450', 'paid', 'confirmed', 'not_required', 'Delivered on time'],
                    ['OLD-1002', 'Fatima Hassan', '+971502345678', '2025-06-10', '2025-06-15', 'Dubai', 'Custom Acrylic Display Stand', '1', '850', '0', '0', '850', '850', 'paid', 'confirmed', 'not_required', ''],
                ],
            ],
        ];

        abort_unless(array_key_exists($type, $templates), 404);
        $t = $templates[$type];
        return response()->streamDownload(function () use ($t) {
            $out = fopen('php://output', 'w');
            fputs($out, "\xEF\xBB\xBF");
            fputcsv($out, $t['headers']);
            foreach ($t['rows'] as $row) fputcsv($out, $row);
            fclose($out);
        }, "{$type}-template.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
