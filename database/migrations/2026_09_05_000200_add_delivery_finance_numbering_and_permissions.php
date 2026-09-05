<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        foreach ([
            ['courier_bill', 'CB-{YYYY}-', 'yearly'],
            ['driver_settlement', 'DS-{YYYY}-', 'yearly'],
        ] as [$type, $prefix, $policy]) {
            DB::table('numbering_sequences')->updateOrInsert(
                ['document_type' => $type],
                ['prefix' => $prefix, 'reset_policy' => $policy, 'padding' => 5, 'current_value' => 0, 'year' => $now->year, 'month' => $now->month, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        // Seed the initial editable defaults from the spec, effective from today —
        // an Owner/Admin can change these later without altering historical deliveries.
        foreach ([
            ['domestic_customer_charge', 40],
            ['domestic_courier_estimated_cost', 25],
            ['own_driver_fee', 10],
            ['own_driver_daily_allowance', 5],
        ] as [$key, $value]) {
            if (!DB::table('delivery_finance_settings')->where('setting_key', $key)->exists()) {
                DB::table('delivery_finance_settings')->insert(['setting_key' => $key, 'value' => $value, 'effective_date' => $now->toDateString(), 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        $newPermissions = [
            'deliveries.view.finance' => 'View delivery financials',
            'deliveries.edit.charge' => 'Edit customer delivery charge',
            'deliveries.edit.cost' => 'Edit actual delivery cost',
            'courier-bills.manage' => 'Manage courier bills',
            'courier-bills.approve' => 'Approve courier bills',
            'courier-bills.pay' => 'Pay courier bills',
            'driver-settlements.manage' => 'Manage driver settlements',
            'driver-settlements.pay' => 'Pay driver settlements',
            'vehicle-expenses.manage' => 'Manage vehicle expenses',
            'deliveries.view.profit' => 'View delivery profit',
            'deliveries.export' => 'Export delivery reports',
        ];
        foreach ($newPermissions as $name => $label) {
            DB::table('permissions')->updateOrInsert(['name' => $name], ['label' => $label, 'created_at' => $now, 'updated_at' => $now]);
        }
        $ownerRoleId = DB::table('roles')->where('name', 'owner')->value('id');
        if ($ownerRoleId) {
            $permissionIds = DB::table('permissions')->whereIn('name', array_keys($newPermissions))->pluck('id');
            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->updateOrInsert(['role_id' => $ownerRoleId, 'permission_id' => $permissionId]);
            }
        }
    }

    public function down(): void
    {
        // Non-destructive, matching this project's established convention.
    }
};
