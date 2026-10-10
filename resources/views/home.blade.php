<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>HOME AI — Executive Intelligence</title>

    <style>
        :root {
            --bg: #070b12;
            --bg-soft: #0b111b;
            --panel: rgba(16, 24, 38, 0.72);
            --panel-solid: #101826;
            --border: rgba(255,255,255,0.09);
            --border-hover: rgba(99, 179, 237, 0.35);
            --text: #f4f7fb;
            --muted: #8996aa;
            --muted-2: #5e6b7f;
            --blue: #5ca9ff;
            --cyan: #38d9d1;
            --green: #35d49a;
            --warning: #f5b85b;
            --danger: #ff6b7a;
            --shadow: 0 20px 60px rgba(0,0,0,.28);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family:
                Inter,
                ui-sans-serif,
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;

            background:
                radial-gradient(
                    circle at 75% 5%,
                    rgba(61, 137, 255, .13),
                    transparent 28%
                ),
                radial-gradient(
                    circle at 20% 80%,
                    rgba(0, 210, 190, .07),
                    transparent 30%
                ),
                var(--bg);

            color: var(--text);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Ambient background */

        body::before {
            content: "";
            position: fixed;
            inset: 0;
            pointer-events: none;
            background-image:
                linear-gradient(
                    rgba(255,255,255,.018) 1px,
                    transparent 1px
                ),
                linear-gradient(
                    90deg,
                    rgba(255,255,255,.018) 1px,
                    transparent 1px
                );
            background-size: 42px 42px;
            mask-image: linear-gradient(
                to bottom,
                black,
                transparent 85%
            );
        }

        .app {
            min-height: 100vh;
            display: flex;
        }

        /* =========================
           SIDEBAR
        ========================= */

        .sidebar {
            width: 250px;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            padding: 25px 16px;
            background: rgba(7, 11, 18, .78);
            border-right: 1px solid var(--border);
            backdrop-filter: blur(20px);
            z-index: 20;
        }

        .brand {
            padding: 8px 12px 30px;
        }

        .brand-mark {
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            border-radius: 11px;
            margin-bottom: 14px;

            background:
                linear-gradient(
                    135deg,
                    rgba(92,169,255,.24),
                    rgba(56,217,209,.12)
                );

            border: 1px solid rgba(92,169,255,.28);
            box-shadow:
                0 0 30px rgba(92,169,255,.12);
        }

        .brand-mark span {
            font-size: 17px;
            color: var(--blue);
        }

        .brand h1 {
            font-size: 21px;
            letter-spacing: -.5px;
        }

        .brand p {
            color: var(--muted);
            font-size: 11px;
            margin-top: 4px;
        }

        .nav-title {
            color: var(--muted-2);
            font-size: 9px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            padding: 14px 12px 7px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 11px;

            padding: 10px 12px;
            margin: 3px 0;

            border-radius: 9px;
            border: 1px solid transparent;

            color: #aeb9c9;
            text-decoration: none;
            font-size: 12px;

            transition:
                transform .2s ease,
                background .2s ease,
                border .2s ease,
                color .2s ease;
        }

        .nav-item:hover {
            transform: translateX(3px);
            color: white;
            background: rgba(255,255,255,.045);
            border-color: var(--border);
        }

        .nav-item.active {
            color: white;
            background:
                linear-gradient(
                    90deg,
                    rgba(92,169,255,.14),
                    rgba(92,169,255,.035)
                );
            border-color: rgba(92,169,255,.16);
        }

        .nav-icon {
            width: 17px;
            text-align: center;
            opacity: .8;
        }

        .demo-label {
            position: absolute;
            bottom: 22px;
            left: 20px;
            right: 20px;

            padding: 10px;
            border-radius: 8px;

            background: rgba(245,184,91,.06);
            border: 1px solid rgba(245,184,91,.15);

            color: #c9a96e;
            font-size: 9px;
            line-height: 1.5;
            text-align: center;
        }

        /* =========================
           MAIN
        ========================= */

        .main {
            margin-left: 250px;
            width: calc(100% - 250px);
            padding: 30px 38px 50px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 34px;
        }

        .eyebrow {
            display: flex;
            align-items: center;
            gap: 8px;

            color: var(--muted);
            font-size: 10px;
            letter-spacing: 1.3px;
            text-transform: uppercase;
        }

        .live-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--green);
            box-shadow: 0 0 12px rgba(53,212,154,.7);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% {
                opacity: 1;
                transform: scale(1);
            }

            50% {
                opacity: .45;
                transform: scale(.75);
            }
        }

        .topbar h2 {
            margin-top: 8px;
            font-size: 27px;
            letter-spacing: -1px;
        }

        .top-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .icon-button {
            width: 38px;
            height: 38px;

            display: grid;
            place-items: center;

            border-radius: 10px;
            border: 1px solid var(--border);

            background: rgba(255,255,255,.025);
            color: var(--muted);

            cursor: pointer;

            transition:
                transform .2s ease,
                background .2s ease,
                color .2s ease;
        }

        .icon-button:hover {
            transform: translateY(-2px);
            color: white;
            background: rgba(255,255,255,.07);
        }

        .status {
            display: flex;
            align-items: center;
            gap: 8px;

            padding: 9px 13px;

            border-radius: 10px;
            border: 1px solid rgba(53,212,154,.15);
            background: rgba(53,212,154,.045);

            color: #a8cdbf;
            font-size: 10px;
        }

        /* =========================
           AI COMMAND CENTER
        ========================= */

        .ai-command {
            position: relative;
            overflow: hidden;

            padding: 30px;
            min-height: 265px;

            border-radius: 20px;
            border: 1px solid rgba(92,169,255,.17);

            background:
                radial-gradient(
                    circle at 90% 10%,
                    rgba(92,169,255,.17),
                    transparent 30%
                ),
                radial-gradient(
                    circle at 10% 100%,
                    rgba(56,217,209,.08),
                    transparent 35%
                ),
                rgba(13, 22, 36, .75);

            box-shadow: var(--shadow);
            backdrop-filter: blur(18px);
        }

        .ai-command::after {
            content: "";
            position: absolute;
            width: 300px;
            height: 300px;
            right: -130px;
            bottom: -180px;

            border-radius: 50%;

            border: 1px solid rgba(92,169,255,.12);
            box-shadow:
                0 0 70px rgba(92,169,255,.08);
        }

        .ai-label {
            display: flex;
            align-items: center;
            gap: 9px;

            color: #9cb8d7;
            font-size: 11px;
            letter-spacing: .8px;
            text-transform: uppercase;
        }

        .ai-symbol {
            color: var(--cyan);
            font-size: 17px;
        }

        .ai-command h1 {
            max-width: 750px;
            margin-top: 14px;

            font-size: clamp(27px, 3vw, 43px);
            line-height: 1.05;
            letter-spacing: -1.8px;
        }

        .gradient-text {
            background:
                linear-gradient(
                    100deg,
                    #ffffff,
                    #8ec7ff 50%,
                    #66e2d7
                );

            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .ai-subtitle {
            color: var(--muted);
            font-size: 12px;
            margin-top: 12px;
            max-width: 600px;
            line-height: 1.6;
        }

        .ask-container {
            position: relative;
            z-index: 2;

            display: flex;
            align-items: center;

            margin-top: 25px;

            max-width: 820px;

            background: rgba(4, 8, 14, .72);

            border: 1px solid rgba(255,255,255,.11);
            border-radius: 13px;

            transition:
                border .25s ease,
                box-shadow .25s ease;
        }

        .ask-container:focus-within {
            border-color: rgba(92,169,255,.5);
            box-shadow:
                0 0 0 4px rgba(92,169,255,.06),
                0 12px 40px rgba(0,0,0,.2);
        }

        .ask-icon {
            padding-left: 16px;
            color: var(--cyan);
        }

        .ask-input {
            flex: 1;
            border: 0;
            outline: 0;

            padding: 16px 13px;

            background: transparent;
            color: white;

            font-size: 13px;
        }

        .ask-input::placeholder {
            color: #59677a;
        }

        .ask-button {
            margin: 5px;
            padding: 10px 16px;

            border: 0;
            border-radius: 9px;

            background:
                linear-gradient(
                    135deg,
                    #368fe8,
                    #2476cc
                );

            color: white;
            font-size: 11px;
            font-weight: 600;

            cursor: pointer;

            transition:
                transform .2s ease,
                box-shadow .2s ease;
        }

        .ask-button:hover {
            transform: translateY(-2px);
            box-shadow:
                0 8px 24px rgba(54,143,232,.28);
        }

        .suggestions {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;

            margin-top: 12px;
        }

        .suggestion {
            padding: 7px 10px;

            border-radius: 20px;
            border: 1px solid rgba(255,255,255,.07);

            background: rgba(255,255,255,.025);

            color: #7f8ca0;
            font-size: 9px;

            cursor: pointer;

            transition:
                background .2s ease,
                color .2s ease,
                border .2s ease;
        }

        .suggestion:hover {
            color: white;
            background: rgba(92,169,255,.08);
            border-color: rgba(92,169,255,.2);
        }

        /* =========================
           BENTO
        ========================= */

        .section-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;

            margin: 32px 0 14px;
        }

        .section-heading h3 {
            font-size: 14px;
            letter-spacing: -.2px;
        }

        .section-heading span {
            color: var(--muted-2);
            font-size: 9px;
        }

        .bento {
            display: grid;
            grid-template-columns: 1.35fr .8fr .8fr;
            grid-template-rows: 150px 150px;
            gap: 12px;
        }

        .panel {
            position: relative;
            overflow: hidden;

            padding: 19px;

            border-radius: 15px;
            border: 1px solid var(--border);

            background: var(--panel);

            backdrop-filter: blur(15px);

            transition:
                transform .25s ease,
                border .25s ease,
                box-shadow .25s ease;
        }

        .panel:hover {
            transform: translateY(-3px);
            border-color: var(--border-hover);
            box-shadow:
                0 16px 45px rgba(0,0,0,.22);
        }

        .briefing-panel {
            grid-row: span 2;

            background:
                radial-gradient(
                    circle at 90% 0%,
                    rgba(92,169,255,.10),
                    transparent 45%
                ),
                var(--panel);
        }

        .wide-panel {
            grid-column: span 2;
        }

        .panel-label {
            color: var(--muted);
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .metric {
            margin-top: 15px;
            font-size: 35px;
            letter-spacing: -1.5px;
        }

        .metric-description {
            margin-top: 3px;
            color: var(--muted);
            font-size: 9px;
        }

        .metric-trend {
            position: absolute;
            top: 18px;
            right: 18px;

            padding: 5px 7px;

            border-radius: 6px;
            background: rgba(53,212,154,.07);
            color: var(--green);

            font-size: 8px;
        }

        .briefing-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .ai-mini {
            color: var(--cyan);
            font-size: 16px;
        }

        .briefing-title {
            margin-top: 18px;
            font-size: 18px;
            letter-spacing: -.5px;
        }

        .briefing-text {
            margin-top: 9px;
            color: var(--muted);
            font-size: 10px;
            line-height: 1.6;
        }

        .briefing-link {
            display: inline-block;
            margin-top: 17px;

            color: #8dc5ff;
            text-decoration: none;
            font-size: 9px;
        }

        .briefing-link:hover {
            text-decoration: underline;
        }

        /* =========================
           LOWER SECTION
        ========================= */

        .lower {
            display: grid;
            grid-template-columns: 1.4fr .9fr;
            gap: 12px;

            margin-top: 12px;
        }

        .activity-item {
            display: flex;
            justify-content: space-between;
            gap: 15px;

            padding: 13px 0;
            border-bottom: 1px solid rgba(255,255,255,.055);
        }

        .activity-item:last-child {
            border-bottom: 0;
        }

        .activity-main strong {
            display: block;
            font-size: 10px;
            font-weight: 600;
        }

        .activity-main span {
            display: block;
            margin-top: 4px;

            color: var(--muted);
            font-size: 9px;
        }

        .priority {
            white-space: nowrap;

            padding: 5px 7px;
            height: fit-content;

            border-radius: 6px;

            background: rgba(245,184,91,.07);
            color: var(--warning);

            font-size: 8px;
        }

        .insight {
            padding: 12px 0;

            border-bottom: 1px solid rgba(255,255,255,.055);

            color: #aeb9c9;
            font-size: 10px;
            line-height: 1.55;
        }

        .insight:last-child {
            border-bottom: 0;
        }

        .insight strong {
            display: block;
            margin-bottom: 4px;
            color: white;
        }

        /* =========================
           RESPONSIVE
        ========================= */

        @media (max-width: 1000px) {

            .sidebar {
                width: 210px;
            }

            .main {
                margin-left: 210px;
                width: calc(100% - 210px);
                padding: 25px;
            }

            .bento {
                grid-template-columns: 1fr 1fr;
                grid-template-rows: auto;
            }

            .briefing-panel {
                grid-row: auto;
                grid-column: span 2;
            }

            .wide-panel {
                grid-column: span 2;
            }

            .lower {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 700px) {

            .sidebar {
                display: none;
            }

            .main {
                margin-left: 0;
                width: 100%;
                padding: 20px;
            }

            .topbar h2 {
                font-size: 22px;
            }

            .status {
                display: none;
            }

            .ai-command {
                padding: 22px;
            }

            .ask-container {
                align-items: stretch;
            }

            .ask-button {
                padding: 0 13px;
            }

            .bento {
                grid-template-columns: 1fr;
            }

            .briefing-panel,
            .wide-panel {
                grid-column: span 1;
            }
        }

        /* =========================
           ASK HOME AI ANSWER
        ========================= */

        .ai-answer {
            position: relative;
            z-index: 2;

            max-width: 820px;
            margin-top: 16px;
            padding: 16px 18px;

            border-radius: 13px;
            border: 1px solid rgba(92,169,255,.2);
            background: rgba(4, 8, 14, .6);

            color: #cfd8e6;
            font-size: 12px;
            line-height: 1.7;
            white-space: pre-wrap;
        }

        .ai-answer.error {
            border-color: rgba(255,107,122,.35);
            color: #ffb3bb;
        }

        .ai-refs {
            margin-top: 12px;
            font-size: 10px;
            white-space: normal;
        }

        .ai-refs a {
            display: inline-block;
            margin-right: 12px;
            color: #8dc5ff;
            text-decoration: none;
        }

        .ai-refs a:hover {
            text-decoration: underline;
        }

        .ask-button:disabled {
            opacity: .6;
            cursor: wait;
        }

        /* =========================
           SIGNED-IN USER
        ========================= */

        .user-box {
            position: absolute;
            bottom: 86px;
            left: 20px;
            right: 20px;

            padding: 10px 12px;
            border-radius: 8px;

            background: rgba(255,255,255,.03);
            border: 1px solid var(--border);

            font-size: 10px;
            line-height: 1.5;
        }

        .user-box strong {
            display: block;
            font-size: 11px;
        }

        .user-box span {
            color: var(--muted);
        }

        .user-links {
            display: flex;
            gap: 12px;
            margin-top: 7px;
        }

        .user-links a,
        .user-links button {
            padding: 0;
            border: 0;
            background: none;
            color: #8dc5ff;
            cursor: pointer;
            font: inherit;
            text-decoration: none;
        }

        .user-links a:hover,
        .user-links button:hover {
            text-decoration: underline;
        }

        /* Deadline badges and reminders */

        .dl-badge {
            white-space: nowrap;
            padding: 5px 7px;
            height: fit-content;
            border-radius: 6px;
            font-size: 8px;
            background: rgba(255,255,255,.05);
            color: var(--muted);
        }

        .dl-overdue { background: rgba(255,107,122,.12); color: var(--danger); }
        .dl-today   { background: rgba(255,107,122,.08); color: #ff9aa5; }
        .dl-soon    { background: rgba(245,184,91,.10); color: var(--warning); }
        .dl-week    { background: rgba(92,169,255,.10); color: var(--blue); }

        .rem-actions {
            display: flex;
            gap: 8px;
            margin-top: 10px;
        }

        .rem-btn {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--muted);
            border-radius: 6px;
            padding: 4px 8px;
            font-size: 8px;
            cursor: pointer;
        }

        .rem-btn:hover {
            border-color: var(--border-hover);
            color: var(--text);
        }
    </style>
</head>

<body>

<div class="app">

    <!-- SIDEBAR -->

    <aside class="sidebar">

        <div class="brand">

            <div class="brand-mark">
                <span>✦</span>
            </div>

            <h1>HOME AI</h1>
            <p>Intelligent Executive Assistant</p>

        </div>


        <div class="nav-title">Command</div>

        <a href="{{ route('home') }}" class="nav-item active">
            <span class="nav-icon">⌂</span>
            Dashboard
        </a>

        <a href="#" class="nav-item">
            <span class="nav-icon">✦</span>
            Ask HOME AI
        </a>

        <a href="#" class="nav-item">
            <span class="nav-icon">◈</span>
            Executive Briefing
        </a>


        <div class="nav-title">Operations</div>

        <a href="{{ route('matters') }}" class="nav-item">
            <span class="nav-icon">▣</span>
            Matters & Files
        </a>

        <a href="{{ route('documents.index') }}" class="nav-item">
            <span class="nav-icon">▤</span>
            Documents
        </a>

        <a href="#" class="nav-item">
            <span class="nav-icon">◷</span>
            Meetings
        </a>

        <a href="#" class="nav-item">
            <span class="nav-icon">✓</span>
            Actions & Deadlines
        </a>


        <div class="nav-title">Intelligence</div>

        <a href="#" class="nav-item">
            <span class="nav-icon">◎</span>
            Public Grievances
        </a>

        @if (auth()->user()->canManageWork())
        <a href="{{ route('documents.create') }}" class="nav-item">
            <span class="nav-icon">⌕</span>
            Document Intelligence
        </a>
        @endif


        <div class="user-box">
            <strong>{{ auth()->user()->name }}</strong>
            <span>{{ auth()->user()->roleLabel() }}</span>

            <div class="user-links">
                @if (auth()->user()->isAdmin())
                    <a href="{{ route('admin.users.index') }}">Users</a>
                @endif

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit">Log out</button>
                </form>
            </div>
        </div>


        <div class="demo-label">
            DEMONSTRATION ENVIRONMENT<br>
            Synthetic data only
        </div>

    </aside>


    <!-- MAIN -->

    <main class="main">

        <header class="topbar">

            <div>

                <div class="eyebrow">
                    <span class="live-dot"></span>
                    Intelligence System Online
                </div>

                <h2>Executive Command Center</h2>

            </div>


            <div class="top-actions">

                <button class="icon-button" id="themeButton" title="Theme">
                    ☼
                </button>

                <div class="status">
                    ● AI Ready
                </div>

            </div>

        </header>


        <!-- AI COMMAND CENTER -->

        <section class="ai-command">

            <div class="ai-label">
                <span class="ai-symbol">✦</span>
                HOME AI Command
            </div>


            <h1>
                Intelligence for every
                <span class="gradient-text">
                    executive decision.
                </span>
            </h1>


            <p class="ai-subtitle">
                Ask questions, analyze departmental matters,
                prepare executive briefings, identify pending
                actions, and surface what requires attention.
            </p>


            <div class="ask-container">

                <div class="ask-icon">✦</div>

                <input
                    id="aiInput"
                    class="ask-input"
                    type="text"
                    placeholder="Ask HOME AI anything..."
                >

                <button
                    class="ask-button"
                    onclick="askAI()"
                >
                    Ask AI →
                </button>

            </div>


            <div class="suggestions">

                <button
                    class="suggestion"
                    onclick="fillPrompt('Aaj ka briefing bana do')"
                >
                    Aaj ka briefing bana do
                </button>

                <button
                    class="suggestion"
                    onclick="fillPrompt('What requires my attention today?')"
                >
                    What requires my attention today?
                </button>

                <button
                    class="suggestion"
                    onclick="fillPrompt('What is urgent this week?')"
                >
                    What is urgent this week?
                </button>

                <button
                    class="suggestion"
                    onclick="fillPrompt('Show pending actions')"
                >
                    Show pending actions
                </button>

                <button
                    class="suggestion"
                    onclick="fillPrompt('Prepare my meeting brief')"
                >
                    Prepare my meeting brief
                </button>

            </div>

        </section>


        <!-- BENTO OVERVIEW -->

        <div class="section-heading">

            <h3>Department Intelligence</h3>

            <span>LIVE DEMONSTRATION DATA</span>

        </div>


        <section class="bento">


            <!-- EXECUTIVE BRIEFING -->

            <div class="panel briefing-panel">

                <div class="briefing-top">

                    <div class="panel-label">
                        AI Executive Briefing
                    </div>

                    <div class="ai-mini">
                        ✦
                    </div>

                </div>

                <div class="briefing-title">
                    Today's attention
                </div>

                <div class="briefing-text">

                    {{ $upcomingDeadlines }} matters have deadlines within
                    the next seven days.

                    <br><br>

                    <span style="color: var(--danger);">
                        {{ $overdueMatters }} {{ \Illuminate\Support\Str::plural('matter', $overdueMatters) }}
                        {{ $overdueMatters === 1 ? 'is' : 'are' }} past {{ $overdueMatters === 1 ? 'its' : 'their' }} deadline.
                    </span>

                    <br><br>

                    {{ $awaitingExecutiveDirection }} matters are awaiting executive
                    direction or decision.

                    <br><br>

                    HOME AI can consolidate relevant
                    matters, documents, meetings and
                    actions into one executive brief.

                </div>

                <a href="#" class="briefing-link">
                    Generate full briefing →
                </a>

            </div>


            <!-- OPEN MATTERS -->

            <div class="panel">

                <div class="panel-label">
                    Open Matters
                </div>

                <div class="metric">
                    {{ $openMatters }}
                </div>

                <div class="metric-description">
                    Across departmental sections
                </div>

                <div class="metric-trend">
                    Active
                </div>
<a href="{{ route('matters') }}" style="display: inline-block; margin-top: 18px; padding: 10px 14px; background: #2563eb; color: white; text-decoration: none; border-radius: 6px; font-size: 13px;">
    View All Matters →
</a>
            </div>


            <!-- ACTIONS -->

            <div class="panel">

                <div class="panel-label">
                    Pending Actions
                </div>

                <div class="metric">
                    {{ $pendingActions }}
                </div>

                <div class="metric-description">
                    Require follow-up
                </div>

                <div class="metric-trend" style="color: var(--warning); background: rgba(245,184,91,.07);">
                    {{ $overdueMatters }} overdue
                </div>

            </div>


            <!-- DOCUMENTS -->

            <div class="panel wide-panel">

                <div class="panel-label">
                    Documents on File
                </div>

                <div class="metric">
                    {{ number_format($documentCount) }}
                </div>

                <div class="metric-description">
                    Scanned, classified and filed
                </div>

                <a
                    href="{{ route('documents.index') }}"
                    class="briefing-link"
                    style="position: absolute; right: 18px; bottom: 16px; margin-top: 0;"
                >
                    View filed documents →
                </a>

            </div>

        </section>


        <!-- DEADLINE WATCH AND REMINDERS -->

        <section class="lower">

            <div class="panel">

                <div class="panel-label">
                    Deadline Watch (overdue and next 7 days)
                </div>

                @forelse ($dueSoon as $dueMatter)
                    @php $badge = $dueMatter->deadlineBadge(); @endphp
                    <div class="activity-item">

                        <div class="activity-main">
                            <strong>
                                <a href="{{ route('matters.show', $dueMatter) }}" style="color: inherit; text-decoration: none;">
                                    {{ $dueMatter->title }}
                                </a>
                            </strong>
                            <span>
                                {{ $dueMatter->matter_reference }}
                                | {{ $dueMatter->section }}
                                | {{ $dueMatter->priority }}
                                | {{ \Illuminate\Support\Str::of($dueMatter->deadline)->substr(0, 10) }}
                            </span>
                        </div>

                        @if ($badge)
                            <div class="dl-badge dl-{{ $badge['level'] }}">{{ $badge['label'] }}</div>
                        @endif

                    </div>
                @empty
                    <div class="insight">
                        Nothing is overdue or due in the next seven days.
                    </div>
                @endforelse

            </div>

            <div class="panel">

                <div class="panel-label">
                    Your Reminders ({{ $unreadReminders->count() }} unread)
                </div>

                @forelse ($unreadReminders as $reminder)
                    <div class="insight">
                        <strong>{{ $reminder->created_at->diffForHumans() }}</strong>
                        @if ($reminder->matter)
                            <a href="{{ route('matters.show', $reminder->matter) }}" style="color: inherit; text-decoration: none;">
                                {{ $reminder->message }}
                            </a>
                        @else
                            {{ $reminder->message }}
                        @endif

                        <div class="rem-actions">
                            <form method="POST" action="{{ route('reminders.read', $reminder) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="rem-btn">Mark read</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="insight">
                        No unread reminders.
                    </div>
                @endforelse

                @if ($unreadReminders->count() > 1)
                    <div class="rem-actions">
                        <form method="POST" action="{{ route('reminders.read-all') }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="rem-btn">Mark all read</button>
                        </form>
                    </div>
                @endif

            </div>

        </section>

        <!-- LOWER SECTION -->

        <section class="lower">

            <!-- PRIORITY MATTERS -->

            <div class="panel">

                <div class="panel-label">
                    Priority Matters
                </div>

                @forelse ($priorityMatters as $priorityMatter)
                    <div class="activity-item">

                        <div class="activity-main">
                            <strong>
                                <a href="{{ route('matters.show', $priorityMatter) }}" style="color: inherit; text-decoration: none;">
                                    {{ $priorityMatter->title }}
                                </a>
                            </strong>
                            <span>
                                {{ $priorityMatter->matter_reference }}
                                | {{ $priorityMatter->section }}
                                | Due {{ $priorityMatter->deadline }}
                            </span>
                        </div>

                        <div class="priority">
                            {{ $priorityMatter->priority }}
                        </div>

                    </div>
                @empty
                    <div class="insight">
                        No high-priority matters at the moment.
                    </div>
                @endforelse

            </div>


            <!-- AI INSIGHTS -->

            <div class="panel">

                <div class="panel-label">
                    AI Insights
                </div>

                <div class="insight">
                    <strong>Deadline pressure</strong>
                    @if ($overdueMatters > 0)
                        {{ $overdueMatters }} {{ \Illuminate\Support\Str::plural('matter', $overdueMatters) }}
                        {{ $overdueMatters === 1 ? 'has' : 'have' }} passed {{ $overdueMatters === 1 ? 'its' : 'their' }} deadline and need follow-up.
                    @else
                        No matters are past their deadline.
                    @endif
                </div>

                <div class="insight">
                    <strong>This week</strong>
                    {{ $upcomingDeadlines }} {{ \Illuminate\Support\Str::plural('matter', $upcomingDeadlines) }}
                    {{ $upcomingDeadlines === 1 ? 'is' : 'are' }} due within the next seven days.
                </div>

                <div class="insight">
                    <strong>Executive decisions</strong>
                    {{ $awaitingExecutiveDirection }} {{ \Illuminate\Support\Str::plural('matter', $awaitingExecutiveDirection) }}
                    {{ $awaitingExecutiveDirection === 1 ? 'is' : 'are' }} waiting for executive direction.
                </div>

            </div>

        </section>

    </main>

</div>


<script>
    const aiInput = document.getElementById('aiInput');
    const askButton = document.querySelector('.ask-button');
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    function fillPrompt(text) {
        aiInput.value = text;
        aiInput.focus();
    }

    function answerBox() {
        let box = document.getElementById('aiAnswer');

        if (!box) {
            box = document.createElement('div');
            box.id = 'aiAnswer';
            document.querySelector('.suggestions').after(box);
        }

        return box;
    }

    function showAnswer(text, isError, references, documents) {
        const box = answerBox();
        box.className = 'ai-answer' + (isError ? ' error' : '');
        box.textContent = text;

        const links = [];

        (references || []).forEach(function (reference) {
            links.push({ url: reference.url, label: reference.reference + ' ' + reference.title });
        });

        (documents || []).forEach(function (document) {
            links.push({ url: document.url, label: document.id + ' ' + document.name });
        });

        if (links.length) {
            const refs = document.createElement('div');
            refs.className = 'ai-refs';

            links.forEach(function (item) {
                const link = document.createElement('a');
                link.href = item.url;
                link.textContent = item.label;
                refs.appendChild(link);
            });

            box.appendChild(refs);
        }
    }

    async function askAI() {
        const question = aiInput.value.trim();

        if (!question) {
            aiInput.focus();
            return;
        }

        askButton.disabled = true;
        showAnswer('HOME AI is reading the records...', false);

        try {
            const response = await fetch(@json(route('ask')), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ question: question })
            });

            const data = await response.json();

            if (!response.ok) {
                const validation = data.errors && data.errors.question ? data.errors.question[0] : null;
                showAnswer(data.error || validation || 'Something went wrong. Please try again.', true);
            } else {
                showAnswer(data.answer, false, data.references, data.documents);
            }
        } catch (error) {
            showAnswer('HOME AI could not be reached. Please check that the server is running and try again.', true);
        } finally {
            askButton.disabled = false;
        }
    }

    aiInput.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            askAI();
        }
    });
</script>

</body>
</html>
