<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds bank/cash/card/petty-cash tracking directly onto the existing
 * chart_of_accounts table rather than a parallel structure — a "Bank &
 * Cash Account" is simply an asset account with a subtype, so its
 * balance is computed from the SAME real journal lines everything else
 * already posts to, guaranteeing it can never drift out of sync.
 * Purely additive; never touches existing account rows' balances.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->string('account_subtype')->nullable()->after('type'); // bank, cash, card, petty_cash
            $table->decimal('opening_balance', 14, 2)->default(0)->after('account_subtype');
        });

        // Mark the two existing default accounts (1000 Cash, 1010 Bank)
        // with their real subtype, so they immediately show up as
        // trackable accounts without needing to be recreated.
        \DB::table('chart_of_accounts')->where('code', '1000')->update(['account_subtype' => 'cash']);
        \DB::table('chart_of_accounts')->where('code', '1010')->update(['account_subtype' => 'bank']);
    }

    public function down(): void
    {
        // Non-destructive, matching this project's established convention.
    }
};
