<?php

namespace App\Http\Controllers;

use App\Models\AccountTransfer;
use App\Models\Expense;
use App\Models\Payment;
use Illuminate\Support\Facades\Storage;

/**
 * Streams payment/expense proof files from the PRIVATE disk. There is no
 * public URL for these files at all — every request goes through here,
 * checked against the same permission as managing the parent record, so
 * access follows the ERP's existing authorization rather than a guessable
 * storage path.
 */
class ProofController extends Controller
{
    public function payment(Payment $payment)
    {
        abort_unless(auth()->user()->hasPermission('payments.manage'), 403);
        abort_unless($payment->proof_path, 404, 'This payment has no proof on file.');
        return $this->stream($payment->proof_path, $payment->proof_original_name, $payment->proof_mime);
    }

    public function expense(Expense $expense)
    {
        abort_unless(auth()->user()->hasPermission('expenses.manage') || auth()->user()->hasPermission('expenses.view'), 403);
        abort_unless($expense->proof_path, 404, 'This expense has no proof on file.');
        return $this->stream($expense->proof_path, $expense->proof_original_name, $expense->proof_mime);
    }

    public function expenseInvoice(Expense $expense)
    {
        abort_unless(auth()->user()->hasPermission('expenses.manage') || auth()->user()->hasPermission('expenses.view'), 403);
        abort_unless($expense->invoice_path, 404, 'This expense has no invoice/bill on file.');
        return $this->stream($expense->invoice_path, $expense->invoice_original_name, $expense->invoice_mime);
    }

    public function accountTransfer(AccountTransfer $transfer)
    {
        abort_unless(auth()->user()->hasPermission('accounting.view'), 403);
        abort_unless($transfer->proof_path, 404, 'This transfer has no proof on file.');
        return $this->stream($transfer->proof_path, $transfer->proof_original_name, $transfer->proof_mime);
    }

    private function stream(string $path, ?string $originalName, ?string $mime)
    {
        abort_unless(Storage::disk('local')->exists($path), 404);
        if (request()->boolean('download')) {
            return Storage::disk('local')->download($path, $originalName);
        }
        return Storage::disk('local')->response($path, $originalName, [
            'Content-Type' => $mime ?? 'application/octet-stream',
        ]);
    }
}
