<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        foreach ([
            ['staff', 'STF-', 'none'],
            ['payroll_payment', 'PAY-STF-{YYYY}-', 'yearly'],
        ] as [$type, $prefix, $policy]) {
            DB::table('numbering_sequences')->updateOrInsert(
                ['document_type' => $type],
                ['prefix' => $prefix, 'reset_policy' => $policy, 'padding' => 5, 'current_value' => 0, 'year' => $now->year, 'month' => $now->month, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        $newPermissions = [
            'staff.view' => 'View staff', 'staff.create' => 'Create staff', 'staff.edit' => 'Edit staff',
            'staff.salary.view' => 'View staff salary', 'payroll.manage' => 'Manage payroll', 'payroll.pay' => 'Pay salary',
            'staff.attendance.manage' => 'Manage attendance', 'staff.leave.manage' => 'Manage leave',
            'staff.overtime.approve' => 'Approve overtime', 'staff.tickets.manage' => 'Manage staff tickets',
            'staff.gratuity.view' => 'View gratuity', 'staff.gratuity.approve' => 'Approve gratuity',
            'payroll.cancel' => 'Cancel payroll payments',
        ];
        foreach ($newPermissions as $name => $label) {
            DB::table('permissions')->updateOrInsert(['name' => $name], ['label' => $label, 'created_at' => $now, 'updated_at' => $now]);
        }

        // Owner gets every new permission automatically, matching the seeder's own convention.
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
