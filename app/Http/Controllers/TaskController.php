<?php

namespace App\Http\Controllers;

use App\Models\{Customer, Invoice, Product, SalesOrder, Supplier, Task};
use Illuminate\Http\Request;

class TaskController extends Controller
{
    private const LINKABLE_TYPES = [
        'sales_order' => SalesOrder::class,
        'customer' => Customer::class,
        'invoice' => Invoice::class,
        'product' => Product::class,
        'supplier' => Supplier::class,
    ];

    public function index(Request $request)
    {
        $q = Task::with('assignee', 'creator', 'linkable');
        if ($request->query('filter') === 'mine') {
            $q->where('assigned_to', auth()->id());
        } elseif ($request->query('filter') === 'overdue') {
            $q->where('due_at', '<', now())->whereNotIn('status', ['done', 'cancelled']);
        } elseif ($status = $request->query('status')) {
            $q->where('status', $status);
        }
        $tasks = $q->orderByRaw("CASE WHEN status='done' OR status='cancelled' THEN 1 ELSE 0 END")->orderBy('due_at')->paginate(30);
        return view('tasks.index', ['tasks' => $tasks, 'users' => \App\Models\User::where('is_active', true)->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'assigned_to' => 'nullable|exists:users,id',
            'due_at' => 'nullable|date',
            'priority' => 'required|in:low,normal,high,urgent',
            'reminder_enabled' => 'sometimes|boolean',
            'linkable_type' => 'nullable|in:'.implode(',', array_keys(self::LINKABLE_TYPES)),
            'linkable_id' => 'nullable|integer',
        ]);

        $linkableType = null;
        $linkableId = null;
        if (!empty($data['linkable_type']) && !empty($data['linkable_id'])) {
            $class = self::LINKABLE_TYPES[$data['linkable_type']];
            if ($class::whereKey($data['linkable_id'])->exists()) {
                $linkableType = $class;
                $linkableId = $data['linkable_id'];
            }
        }

        Task::create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'assigned_to' => $data['assigned_to'] ?? null,
            'created_by' => auth()->id(),
            'due_at' => $data['due_at'] ?? null,
            'priority' => $data['priority'],
            'status' => 'open',
            'reminder_enabled' => $request->boolean('reminder_enabled'),
            'linkable_type' => $linkableType,
            'linkable_id' => $linkableId,
        ]);

        return back()->with('success', 'Task created.');
    }

    public function update(Request $request, Task $task)
    {
        $data = $request->validate(['status' => 'required|in:open,in_progress,done,cancelled']);
        $task->update(['status' => $data['status'], 'completed_at' => $data['status'] === 'done' ? now() : null]);
        return back()->with('success', 'Task updated.');
    }

    public function destroy(Task $task)
    {
        abort_unless($task->created_by === auth()->id() || auth()->user()->hasPermission('orders.delete'), 403);
        $task->delete();
        return back()->with('success', 'Task deleted.');
    }
}
