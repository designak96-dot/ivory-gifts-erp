<?php

namespace App\Services;

use App\Models\NumberingSequence;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class NumberingService
{
    public function next(string $type): string
    {
        return DB::transaction(function () use ($type) {
            $sequence = NumberingSequence::where('document_type', $type)->lockForUpdate()->first();
            if (!$sequence) throw new RuntimeException("Missing numbering sequence: {$type}");
            $now = now();
            $reset = ($sequence->reset_policy === 'yearly' && (int)$sequence->year !== $now->year)
                || ($sequence->reset_policy === 'monthly' && ((int)$sequence->year !== $now->year || (int)$sequence->month !== $now->month));
            if ($reset) $sequence->current_value = 0;
            $sequence->current_value++;
            $sequence->year = $now->year;
            $sequence->month = $now->month;
            $sequence->save();
            $prefix = str_replace(['{YYYY}', '{YY}', '{MM}'], [$now->format('Y'), $now->format('y'), $now->format('m')], $sequence->prefix);
            return $prefix.str_pad((string)$sequence->current_value, $sequence->padding, '0', STR_PAD_LEFT);
        });
    }

    /**
     * Sales Order number format: MANUAL-MMYY — exactly the manual reference,
     * a dash, then the order date's 2-digit month and 2-digit year. No
     * system-generated sequence number, no extra prefix/suffix text, per
     * the explicit spec ("Do not add extra text to Order Number"). This
     * supersedes the earlier MANUAL-XXXXMMYY format (which included a
     * system sequence) — that format was a misread of an earlier revision
     * of the requirement; this one is deliberately simpler and pure
     * formatting, no database read needed at all.
     */
    public function formatSalesOrderNumber(string $manualReference, \Carbon\Carbon $orderDate): string
    {
        return strtoupper(trim($manualReference)).'-'.$orderDate->format('my');
    }
}
