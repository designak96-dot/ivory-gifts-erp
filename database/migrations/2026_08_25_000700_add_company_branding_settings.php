<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * Branding is stored as rows in the existing `settings` table (same pattern
 * already used for company_name/trn/etc.) rather than a new dedicated table
 * — keeps SettingsController's existing updateOrCreate-by-key pattern
 * working unchanged for the new fields. This migration only seeds the new
 * keys with empty defaults; it never touches existing values.
 */
return new class extends Migration
{
    public function up(): void
    {
        $newKeys = [
            'company_legal_name', 'company_trade_name', 'company_website',
            'company_bank_details', 'quotation_terms', 'invoice_terms',
            'document_footer', 'logo_path', 'signature_path',
            'delivery_note_hide_prices',
        ];
        foreach ($newKeys as $key) {
            Setting::firstOrCreate(['key' => $key], ['value' => null, 'group' => 'branding']);
        }
    }

    public function down(): void
    {
        // Deliberately non-destructive: branding values are business data
        // (a real uploaded logo, real bank details) — a migration rollback
        // must never delete them. down() is intentionally a no-op.
    }
};
