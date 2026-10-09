<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Matter;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $openMatters = Matter::whereIn('status', ['Open', 'Pending'])->count();

        $pendingActions = Matter::where('status', 'Pending')->count();
$upcomingDeadlines = Matter::whereIn('status', ['Open', 'Pending'])
    ->whereNotNull('deadline')
    ->whereBetween('deadline', [
        now()->toDateString(),
        now()->addDays(7)->toDateString()
    ])
    ->count();
       
$overdueMatters = Matter::whereIn('status', ['Open', 'Pending'])
    ->whereNotNull('deadline')
    ->where('deadline', '<', now()->toDateString())
    ->count();

$awaitingExecutiveDirection = Matter::whereIn('status', ['Open', 'Pending'])
    ->where('next_action', 'like', '%executive direction%')
    ->count();
        $todayMeetings = 0;

        $priorityMatters = Matter::whereIn('priority', ['High', 'Critical'])
            ->whereIn('status', ['Open', 'Pending'])
            ->orderBy('deadline')
            ->limit(5)
            ->get();

        $documentCount = Document::count();

        return view('home', [
            'openMatters' => $openMatters,
            'pendingActions' => $pendingActions,
            'upcomingDeadlines' => $upcomingDeadlines,
            'overdueMatters' => $overdueMatters,
            'awaitingExecutiveDirection' => $awaitingExecutiveDirection,
            'todayMeetings' => $todayMeetings,
            'priorityMatters' => $priorityMatters,
            'documentCount' => $documentCount,
        ]);
    }

    public function matters(Request $request)
    {
        $query = Matter::whereIn('status', ['Open', 'Pending']);

        if ($request->filled('search')) {
            $search = $request->input('search');

            $query->where(function ($matterQuery) use ($search) {
                $matterQuery->where('matter_reference', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('section', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('section')) {
            $query->where('section', $request->input('section'));
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->input('priority'));
        }

        $matters = $query
            ->orderBy('deadline')
            ->get();

        $sections = Matter::whereIn('status', ['Open', 'Pending'])
            ->orderBy('section')
            ->distinct()
            ->pluck('section');

        return view('matters', compact('matters', 'sections'));
    }

    public function showMatter(Matter $matter)
    {
        return view('matter-show', compact('matter'));
    }
}
