{{-- Navigasi bawah khusus mobile: 4 ikon pintasan, selalu tampil, hanya di layar kecil (di bawah md).
     Ikon "Menu" membuka drawer sidebar untuk akses menu lainnya. --}}
<nav class="bottom-nav no-print" aria-label="Navigasi mobile">
    <a href="{{ route('dashboard') }}" class="bottom-nav-item {{ request()->routeIs('dashboard') ? 'aktif' : '' }}">
        <x-icon name="dashboard" class="ikon"/>
        <span>Beranda</span>
        <span class="{{ request()->routeIs('dashboard') ? 'titik-aktif' : 'hidden w-1 h-1' }}"></span>
    </a>
    <a href="{{ route('logbook.index') }}" class="bottom-nav-item {{ request()->routeIs('logbook.*') ? 'aktif' : '' }}">
        <x-icon name="book" class="ikon"/>
        <span>Log Book</span>
        <span class="{{ request()->routeIs('logbook.*') ? 'titik-aktif' : 'hidden w-1 h-1' }}"></span>
    </a>
    <a href="{{ route('presensi.index') }}" class="bottom-nav-item {{ request()->routeIs('presensi.*') ? 'aktif' : '' }}">
        <x-icon name="clock" class="ikon"/>
        <span>Presensi</span>
        <span class="{{ request()->routeIs('presensi.*') ? 'titik-aktif' : 'hidden w-1 h-1' }}"></span>
    </a>
    <button type="button" data-drawer-open class="bottom-nav-item w-full cursor-pointer">
        <x-icon name="menu" class="ikon"/>
        <span>Menu</span>
        <span class="w-1 h-1"></span>
    </button>
</nav>
