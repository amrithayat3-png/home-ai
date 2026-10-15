<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    /**
     * Administrators only. The trail of who did what, newest first. It can be read here but never edited.
     */
    public function index(Request $request)
    {
        $query = AuditLog::query()->orderByDesc('id');

        if ($request->filled('action')) {
            $query->where('action', 'like', $request->input('action').'%');
        }

        if ($request->filled('search')) {
            $search = $request->input('search');

            $query->where(function ($inner) use ($search) {
                $inner->where('user_label', 'like', "%{$search}%")
                    ->orWhere('subject_label', 'like', "%{$search}%")
                    ->orWhere('action', 'like', "%{$search}%");
            });
        }

        $logs = $query->simplePaginate(50)->withQueryString();

        $actions = AuditLog::query()->select('action')->distinct()->orderBy('action')->pluck('action');

        return view('admin.audit.index', compact('logs', 'actions'));
    }
}
