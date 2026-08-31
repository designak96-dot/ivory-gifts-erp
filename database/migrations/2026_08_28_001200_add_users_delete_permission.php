<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

/**
 * Owner-only delete permission for user accounts — same rationale as the
 * existing orders.delete/payments.delete/invoices.delete migration: kept
 * distinct from users.manage (which is what actually gates the Users &
 * Roles page itself, and stays available to whichever roles already had
 * it) so delete access specifically doesn't spread beyond Owner.
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::updateOrCreate(['name' => 'users.delete'], ['label' => 'Delete users']);

        $owner = Role::where('name', 'owner')->first();
        if ($owner) {
            $id = Permission::where('name', 'users.delete')->value('id');
            if ($id) $owner->permissions()->syncWithoutDetaching([$id]);
        }
    }

    public function down(): void
    {
        // Non-destructive, matching the established precedent for this
        // class of migration.
    }
};
