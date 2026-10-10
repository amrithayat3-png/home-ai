<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Matter;
use App\Models\Reminder;
use App\Reminders\DeadlineStage;
use App\Reminders\ReminderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function index()
    {
        $today = DeadlineStage::today(config('reminders.timezone'));
        $weekEnd = date('Y-m-d', strtotime($today.' +7 days UTC'));

        // Makes sure today's reminders exist even if the scheduler is not running. Runs once per day.
        if (Cache::add('reminders:ran:'.$today, true, now()->addDay())) {
            app(ReminderService::class)->run($today);
        }

        $openMatters = Matter::whereIn('status', ['Open', 'Pending'])->count();

        $pendingActions = Matter::where('status', 'Pending')->count();

        $upcomingDeadlines = Matter::whereIn('status', ['Open', 'Pending'])
            ->whereNotNull('deadline')
            ->whereBetween('deadline', [$today, $weekEnd])
            ->count();

        $overdueMatters = Matter::whereIn('status', ['Open', 'Pending'])
            ->whereNotNull('deadline')
            ->where('deadline', '<', $today)
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

        // Overdue and due within 7 days: most important priority first, then earliest deadline.
        $dueSoon = Matter::whereIn('status', ['Open', 'Pending'])
            ->whereNotNull('deadline')
            ->where('deadline', '<=', $weekEnd)
            ->orderByRaw("CASE priority WHEN 'Critical' THEN 0 WHEN 'High' THEN 1 WHEN 'Normal' THEN 2 WHEN 'Low' THEN 3 ELSE 4 END")
            ->orderBy('deadline')
            ->limit(15)
            ->get();

        $unreadReminders = Reminder::with('matter')
            ->where('user_id', auth()->id())
            ->whereNull('read_at')
            ->latest('id')
            ->limit(15)
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
            'dueSoon' => $dueSoon,
            'unreadReminders' => $unreadReminders,
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
