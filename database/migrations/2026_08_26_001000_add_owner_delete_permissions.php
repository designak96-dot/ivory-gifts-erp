<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Owner-only delete permissions for Sales Orders, Payments, Invoices — kept
 * as distinct permissions from the existing *.manage ones (which several
 * non-Owner roles already have) rather than overloading .manage to also
 * mean "can delete", so delete access never spreads to a role beyond what
 * was explicitly requested.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->softDeletes();
        });

        $names = ['orders.delete' => 'Delete sales orders', 'payments.delete' => 'Delete payments', 'invoices.delete' => 'Delete invoices'];
        foreach ($names as $name => $label) {
            Permission::updateOrCreate(['name' => $name], ['label' => $label]);
        }

        $owner = Role::where('name', 'owner')->first();
        if ($owner) {
            $ids = Permission::whereIn('name', array_keys($names))->pluck('id');
            $owner->permissions()->syncWithoutDetaching($ids);
        }
    }

    public function down(): void
    {
        // Non-destructive: permissions and the soft-delete column are left
        // in place even on rollback, since removing them could silently
        // reveal already soft-deleted records or strip an in-use
        // permission — a rollback of this migration is not expected to be
        // run against a live system with real deletions already recorded.
    }
};
