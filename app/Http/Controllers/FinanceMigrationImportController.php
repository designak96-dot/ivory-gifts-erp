<?php

namespace App\Http\Controllers;

use App\Models\DataImport;
use App\Services\{DataImportService, FinanceMigrationImportService};
use Illuminate\Http\Request;

/** Owner/Admin only, per spec — finance migration commits real accounting records. */
class FinanceMigrationImportController extends Controller
{
    private const TYPES = [
        'material_purchases' => 'Material Purchases',
        'expenses' => 'Expenses (General, Salaries, Rent — auto-detected by category)',
        'other_income' => 'Other Income',
        'ivory_delivery_income' => 'Ivory Delivery Income',
        'ifast_delivery_income' => 'iFast Delivery Income',
    ];

    public function __construct(private DataImportService $parser, private FinanceMigrationImportService $service) {}

    public function create()
    {
        abort_unless(auth()->user()->hasPermission('imports.manage'), 403);
        return view('imports.finance-create', ['types' => self::TYPES]);
    }

    private function resolveRows(Request $request): array
    {
        $request->validate(['type' => 'required|in:'.implode(',', array_keys(self::TYPES)), 'file' => 'required|file|max:20480']);
        $file = $request->file('file');
        $extension = $file->getClientOriginalExtension();
        $hash = $this->parser->fileHash($file->getRealPath());

        $priorImport = DataImport::where('file_hash', $hash)->where('status', 'completed')->where('is_dry_run', false)->latest()->first();
        session([$priorImport ? 'finance_duplicate_warning' : 'finance_duplicate_warning' => $priorImport ? "This exact file was already imported on {$priorImport->created_at->format('d M Y H:i')} — re-importing may create duplicate records unless the source data has genuinely changed." : null]);
        session(['finance_file_hash' => $hash, 'finance_original_filename' => $file->getClientOriginalName()]);

        return $this->parser->parseFile($file->getRealPath(), $extension);
    }

    public function preview(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('imports.manage'), 403);
        $rows = $this->resolveRows($request);
        $type = $request->input('type');

        $preview = $this->service->preview($rows);

        // Distinct, unrecognized payment method values in the source data — the
        // user must map each one before commit; never silently defaulted to Cash.
        $recognized = ['cash', 'bank', 'card'];
        $sourceValues = collect($rows)->map(fn ($r) => strtolower(trim((string) ($r['payment method'] ?? $r['payment_method'] ?? ''))))->filter()->unique()->values();
        $unmappedMethods = $sourceValues->reject(fn ($v) => in_array($v, $recognized, true))->all();

        session(['finance_rows' => $rows, 'finance_type' => $type]);

        return view('imports.finance-preview', ['preview' => $preview, 'type' => $type, 'typeLabel' => self::TYPES[$type] ?? $type, 'unmappedMethods' => $unmappedMethods, 'duplicateWarning' => session('finance_duplicate_warning')]);
    }

    public function dryRun(Request $request)
    {
        return $this->run($request, isDryRun: true);
    }

    public function commit(Request $request)
    {
        $request->validate(['reviewed_confirmation' => 'accepted'], ['reviewed_confirmation.accepted' => 'You must confirm you reviewed the preview before this import can create accounting records.']);
        return $this->run($request, isDryRun: false);
    }

    private function run(Request $request, bool $isDryRun)
    {
        abort_unless(auth()->user()->hasPermission('imports.manage'), 403);
        $rows = session('finance_rows');
        $type = session('finance_type');
        abort_unless($rows !== null, 419, 'Preview session expired — please re-upload the file.');

        $paymentMap = [];
        foreach ((array) $request->input('payment_method_map', []) as $sourceValue => $mapped) {
            $paymentMap[strtolower(trim($sourceValue))] = $mapped;
        }

        $import = match ($type) {
            'material_purchases' => $this->service->commitMaterialPurchases($rows, auth()->id(), $isDryRun, $paymentMap),
            'expenses' => $this->service->commitExpenses($rows, auth()->id(), $isDryRun, $paymentMap),
            'other_income' => $this->service->commitIncome($rows, auth()->id(), $isDryRun, 'other', $paymentMap),
            'ivory_delivery_income' => $this->service->commitIncome($rows, auth()->id(), $isDryRun, 'ivory_delivery', $paymentMap),
            'ifast_delivery_income' => $this->service->commitIncome($rows, auth()->id(), $isDryRun, 'ifast_delivery', $paymentMap),
            default => abort(422, 'Unknown finance import type.'),
        };

        if (!$isDryRun) {
            $import->update(['file_hash' => session('finance_file_hash'), 'original_filename' => session('finance_original_filename')]);
            session()->forget(['finance_rows', 'finance_type', 'finance_file_hash', 'finance_original_filename', 'finance_duplicate_warning']);
        }

        return redirect()->route('imports.history')->with('success', ($isDryRun ? 'Dry run' : 'Import').": {$import->created_count} created, {$import->skipped_count} skipped, {$import->error_count} errors.");
    }

    public function template(string $type)
    {
        abort_unless(array_key_exists($type, self::TYPES), 404);

        $templates = [
            'material_purchases' => [
                'headers' => ['date', 'expense_category', 'invoice no', 'description', 'quantity', 'amount', 'total_amount + tax', 'supplier', 'payment method'],
                'rows' => [
                    ['2025-06-01', 'Material Purchases - Ivory Gifts (COGS)', 'INV-145622', 'Ultra Print Vinyl', '10', '400', '420', 'Blue Rhine', 'bank'],
                    ['2025-06-01', 'Material Purchases - Ivory Gifts (COGS)', 'INV-145622', 'Acrylic Sheet', '5', '150', '157.50', 'Blue Rhine', 'bank'],
                ],
            ],
            'expenses' => [
                'headers' => ['date', 'expense_category', 'invoice no', 'description', 'payee', 'amount', 'total_amount + tax', 'supplier', 'payment method'],
                'rows' => [
                    ['2025-06-01', 'Office Expense', 'INV-9001', 'Office supplies', '', '150', '157.50', 'Amazon', 'card'],
                    ['2025-06-30', 'Salaries', '', 'June salary', 'Ahmed Ali', '3000', '', '', 'bank'],
                    ['2025-06-01', 'Rent Expense', '', 'Office rent June', 'ABC Properties', '5000', '', '', 'bank'],
                ],
            ],
            'other_income' => [
                'headers' => ['date', 'description', 'customer', 'amount', 'total_amount + tax', 'remarks', 'payment method'],
                'rows' => [['2025-06-05', 'Scrap material sale', 'Walk-in', '200', '210', '', 'cash']],
            ],
            'ivory_delivery_income' => [
                'headers' => ['date', 'description', 'customer', 'amount', 'total_amount + tax', 'remarks', 'payment method'],
                'rows' => [['2025-06-05', 'Ivory delivery batch', 'Various', '500', '', 'Weekly batch', 'bank']],
            ],
            'ifast_delivery_income' => [
                'headers' => ['date', 'description', 'customer', 'amount', 'total_amount + tax', 'remarks', 'payment method'],
                'rows' => [['2025-06-05', 'iFast delivery batch', 'Various', '350', '', 'Weekly batch', 'bank']],
            ],
        ];

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
