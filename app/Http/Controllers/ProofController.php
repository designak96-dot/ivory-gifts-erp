<?php

namespace App\Http\Controllers;

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

    private function stream(string $path, ?string $originalName, ?string $mime)
    {
        abort_unless(Storage::disk('local')->exists($path), 404);
        return Storage::disk('local')->response($path, $originalName, [
            'Content-Type' => $mime ?? 'application/octet-stream',
        ]);
    }
}
