{{-- Same strip on every page: the Home button, the main places, and one search box. --}}
<nav class="quicknav" aria-label="Main navigation">
    <a class="qn-home" href="{{ route('home') }}">&#8962; Home</a>
    <a href="{{ route('matters') }}">Matters</a>
    <a href="{{ route('documents.index') }}">Documents</a>
    @if (auth()->user()?->canManageWork())
        <a href="{{ route('documents.create') }}">Upload</a>
    @endif
    <form class="qn-search" method="GET" action="{{ route('search') }}" role="search">
        <input type="search" name="q" value="{{ request()->routeIs('search') ? request('q') : '' }}"
               placeholder="Search letters, matters, reference numbers..." aria-label="Search" maxlength="100" required>
        <button type="submit">Search</button>
    </form>
</nav>
