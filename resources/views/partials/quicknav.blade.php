{{-- Name and seal of the centre, shown at the top of every page. --}}
<div class="brandbar">
    <a href="{{ route('home') }}" class="bb-link" aria-label="Sindh Center For Excellence on Countering Violent Extremism, HOME AI">
        <img src="{{ asset('images/centre-seal.png') }}" width="48" height="48" alt="Seal of the Sindh Center For Excellence on Countering Violent Extremism">
        <span class="bb-text">
            <span class="bb-name">Sindh Center For Excellence on Countering Violent Extremism</span>
            <span class="bb-sub">HOME AI &middot; Home Department Matters</span>
        </span>
    </a>
</div>
{{-- Same strip on every page: the Home button, the main places, and one search box. --}}
<nav class="quicknav" aria-label="Main navigation">
    <a class="qn-home" href="{{ route('home') }}">&#8962; Home</a>
    <a href="{{ route('matters') }}">Matters</a>
    <a href="{{ route('documents.index') }}">Documents</a>
    <a href="{{ route('documents.index', ['view' => 'categories']) }}">Categories</a>
    @if (auth()->user()?->canManageWork())
        <a href="{{ route('documents.create') }}">Upload</a>
    @endif
    <form class="qn-search" method="GET" action="{{ route('search') }}" role="search">
        <input type="search" name="q" value="{{ request()->routeIs('search') ? request('q') : '' }}"
               placeholder="Search letters, matters, reference numbers..." aria-label="Search" maxlength="100" required>
        <button type="submit">Search</button>
    </form>
</nav>
