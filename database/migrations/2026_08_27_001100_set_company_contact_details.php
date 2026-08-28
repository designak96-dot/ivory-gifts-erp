<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $values = [
            'company_trn' => '104164777500003',
            'company_address' => 'Mussafah 26, Abu Dhabi',
            'company_phone' => '+971 52 579 2085',
        ];
        // Applied unconditionally — these are explicit values provided
        // directly as configuration, not a speculative fallback. (A
        // blank-only guard was considered, but SystemDataSeeder already
        // seeds company_address with a generic placeholder — "Abu Dhabi,
        // United Arab Emirates" — which isn't real Owner-entered data
        // either, so a blank-only check wouldn't have reliably applied
        // the real value anyway.)
        foreach ($values as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value, 'group' => 'general']);
        }
    }

    public function down(): void
    {
        // Non-destructive — leaves the values in place on rollback.
    }
};
