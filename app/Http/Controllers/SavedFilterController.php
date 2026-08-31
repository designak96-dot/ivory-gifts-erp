<?php

namespace App\Http\Controllers;

use App\Models\SavedFilter;
use Illuminate\Http\Request;

class SavedFilterController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'page' => 'required|string|max:50',
            'params' => 'required|array',
        ]);
        SavedFilter::create($data + ['created_by' => auth()->id()]);
        return back()->with('success', 'Filter saved.');
    }

    public function destroy(SavedFilter $savedFilter)
    {
        abort_unless($savedFilter->created_by === auth()->id() || auth()->user()->hasPermission('settings.manage'), 403);
        $savedFilter->delete();
        return back()->with('success', 'Filter removed.');
    }
}
