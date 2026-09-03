<?php
namespace App\Http\Controllers;
use App\Models\{Staff, StaffTicket, Supplier};
use App\Services\{PayrollService, ProofUploadService};
use Illuminate\Http\Request;
class StaffTicketController extends Controller
{
    public function store(Request $request, Staff $staff)
    {
        abort_unless(auth()->user()->hasPermission('staff.tickets.manage'), 403);
        $data = $request->validate([
            'ticket_type' => 'nullable|string|max:60', 'destination' => 'nullable|string|max:120',
            'travel_date' => 'nullable|date', 'return_date' => 'nullable|date',
            'amount' => 'required|numeric|min:0', 'agency_name' => 'nullable|string|max:190',
            'invoice_reference' => 'nullable|string|max:100', 'notes' => 'nullable|string|max:255',
        ]);
        $agencyName = $data['agency_name'] ?? null;
        unset($data['agency_name']);
        // The travel agency may be a real Supplier — the staff member never is.
        if ($agencyName) {
            $supplier = Supplier::firstOrCreate(['name' => $agencyName], ['supplier_code' => 'SUP-'.str_pad((string) (Supplier::max('id') + 1), 5, '0', STR_PAD_LEFT), 'status' => 'active']);
            $data['supplier_id'] = $supplier->id;
        }
        $staff->tickets()->create($data + ['status' => 'planned', 'created_by' => auth()->id()]);
        return back()->with('success', 'Ticket recorded.');
    }

    public function pay(Request $request, StaffTicket $ticket, PayrollService $payroll, ProofUploadService $proofs)
    {
        abort_unless(auth()->user()->hasPermission('staff.tickets.manage'), 403);
        $data = $request->validate(['payment_method' => 'required|in:cash,bank,card', 'payment_date' => 'required|date', 'invoice_reference' => 'nullable|string|max:100', 'proof' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:8192']);
        $update = $data;
        if ($request->hasFile('proof')) {
            $stored = $proofs->store($request->file('proof'), 'staff-ticket-proofs');
            $update['proof_path'] = $stored['proof_path'];
            $update['proof_original_name'] = $stored['proof_original_name'];
        }
        $ticket->update($update);
        $payroll->payTicket($ticket, auth()->id());
        return back()->with('success', 'Ticket marked purchased — expense posted.');
    }
}
