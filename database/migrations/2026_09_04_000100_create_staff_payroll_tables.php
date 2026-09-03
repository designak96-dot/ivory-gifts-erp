<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Staff & Payroll module — a complete, separate system. Payroll stays
 * deliberately simple: Current Salary + Overtime Extra = Total to Pay,
 * with no automatic deductions for absence/leave/advances. Every
 * automatic Expense created by this module carries source_type+
 * source_id with a unique constraint, so a payment can never create
 * two Expense records regardless of double-clicks or repeated saves. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->string('staff_number')->unique();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('nationality')->nullable();
            $table->string('job_title')->nullable();
            $table->date('joining_date')->nullable();
            $table->decimal('current_salary', 12, 2)->default(0);
            $table->string('employment_status')->default('active')->index(); // active | on_leave | resigned | terminated
            // Bank/payment details
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_iban')->nullable();
            // Emergency contact
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->string('emergency_contact_relation')->nullable();
            // Passport / Visa / Emirates ID
            $table->string('passport_number')->nullable();
            $table->date('passport_expiry')->nullable();
            $table->string('visa_number')->nullable();
            $table->string('visa_type')->nullable();
            $table->date('visa_expiry')->nullable();
            $table->string('emirates_id_number')->nullable();
            $table->date('emirates_id_expiry')->nullable();
            $table->text('notes')->nullable();
            $table->string('photo_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('staff_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained()->cascadeOnDelete();
            $table->string('category')->nullable();
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('staff_salary_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained()->cascadeOnDelete();
            $table->decimal('previous_salary', 12, 2);
            $table->decimal('new_salary', 12, 2);
            $table->date('effective_date');
            $table->string('reason')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('payroll_payments', function (Blueprint $table) {
            $table->id();
            $table->string('payroll_number')->unique();
            $table->foreignId('staff_id')->constrained()->restrictOnDelete();
            $table->date('payroll_month')->index(); // always stored as the 1st of the month
            // Snapshot at time of payroll — never recomputed from the staff's current_salary later.
            $table->decimal('current_salary', 12, 2);
            $table->decimal('overtime_extra', 12, 2)->default(0);
            $table->decimal('total_to_pay', 12, 2);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->decimal('remaining_amount', 12, 2)->default(0);
            $table->date('payment_date')->nullable();
            $table->string('payment_method')->nullable(); // cash | bank | card
            $table->string('payment_reference')->nullable();
            $table->string('proof_path')->nullable();
            $table->string('proof_original_name')->nullable();
            $table->string('proof_mime')->nullable();
            $table->unsignedBigInteger('proof_size')->nullable();
            $table->string('status')->default('draft')->index(); // draft|unpaid|partially_paid|paid|cancelled
            $table->boolean('linked_from_existing_expense')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['staff_id', 'payroll_month']); // one payroll record per staff per month
        });

        Schema::create('staff_overtime', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('hours', 8, 2)->nullable();
            $table->decimal('rate', 12, 2)->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('reason')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending')->index(); // pending|approved|rejected|paid
            // Set once this overtime is included in a payroll payment — prevents double-inclusion.
            $table->foreignId('payroll_payment_id')->nullable()->constrained('payroll_payments')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('staff_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('status'); // present|absent|half_day|on_leave|sick_leave|weekly_off|public_holiday
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['staff_id', 'date']);
        });

        Schema::create('staff_leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained()->cascadeOnDelete();
            $table->string('leave_type'); // annual|sick|unpaid|emergency|other
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('days', 6, 1);
            $table->string('reason')->nullable();
            $table->string('approval_status')->default('pending')->index(); // pending|approved|rejected|completed|cancelled
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('document_path')->nullable();
            $table->date('return_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('staff_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained()->cascadeOnDelete();
            $table->string('ticket_type')->nullable();
            $table->string('destination')->nullable();
            $table->date('travel_date')->nullable();
            $table->date('return_date')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            // The airline/agency may be a real Supplier — the employee never is.
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('invoice_reference')->nullable();
            $table->string('payment_method')->nullable();
            $table->date('payment_date')->nullable();
            $table->string('proof_path')->nullable();
            $table->string('proof_original_name')->nullable();
            $table->string('status')->default('planned')->index(); // planned|approved|purchased|cancelled
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('staff_gratuity', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained()->cascadeOnDelete();
            $table->date('joining_date')->nullable();
            $table->date('last_working_date')->nullable();
            $table->string('service_period')->nullable();
            $table->decimal('estimated_amount', 12, 2)->nullable();
            $table->decimal('approved_amount', 12, 2)->nullable();
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->decimal('remaining_amount', 12, 2)->default(0);
            $table->date('payment_date')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->string('proof_path')->nullable();
            $table->string('status')->default('estimate')->index(); // estimate|approved|partially_paid|paid|cancelled
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Idempotency: every automatic Expense created by this module carries
        // source_type+source_id, uniquely — a payment can never create two
        // Expense records no matter how many times Save is pressed.
        Schema::table('expenses', function (Blueprint $table) {
            $table->string('source_type')->nullable()->after('invoice_size');
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            $table->foreignId('staff_id')->nullable()->after('source_id')->constrained('staff')->nullOnDelete();
            $table->string('payroll_period')->nullable()->after('staff_id');
            $table->unique(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        // Non-destructive, matching this project's established convention.
    }
};
