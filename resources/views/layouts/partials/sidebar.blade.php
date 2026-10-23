@php
    $user = auth()->user();
    $menu = [];
    $menu[] = ['label' => 'Dashboard', 'route' => route('dashboard'), 'icon' => 'dashboard', 'active' => request()->routeIs('dashboard')];
    if (in_array($user->role, [\App\Models\User::ROLE_PEGAWAI, \App\Models\User::ROLE_ATASAN])) {
        $menu[] = ['label' => 'Presensi & Jam Kerja', 'route' => route('presensi.index'), 'icon' => 'clock', 'active' => request()->routeIs('presensi.*')];
    }
    $menu[] = ['label' => 'Log Book', 'route' => route('logbook.index'), 'icon' => 'book', 'active' => request()->routeIs('logbook.*')];
    $menu[] = ['label' => 'Laporan Kinerja', 'route' => route('laporan.index'), 'icon' => 'report', 'active' => request()->routeIs('laporan.*')];
    if (in_array($user->role, [\App\Models\User::ROLE_ATASAN, \App\Models\User::ROLE_ADMIN_UNIT, \App\Models\User::ROLE_SUPERADMIN])) {
        $menu[] = ['label' => 'Persetujuan', 'route' => route('persetujuan.index'), 'icon' => 'check-badge', 'active' => request()->routeIs('persetujuan.*')];
    }
    if (in_array($user->role, [\App\Models\User::ROLE_ADMIN_UNIT, \App\Models\User::ROLE_SUPERADMIN])) {
        $menu[] = ['label' => 'Pegawai', 'route' => route('pegawai.index'), 'icon' => 'users', 'active' => request()->routeIs('pegawai.*')];
    }
    if ($user->role === \App\Models\User::ROLE_SUPERADMIN) {
        $menu[] = ['label' => 'Unit Kerja', 'route' => route('unit-kerja.index'), 'icon' => 'building', 'active' => request()->routeIs('unit-kerja.*')];
        $menu[] = ['label' => 'Manajemen User', 'route' => route('manajemen-user.index'), 'icon' => 'key', 'active' => request()->routeIs('manajemen-user.*')];
    }
    if (in_array($user->role, [\App\Models\User::ROLE_ATASAN, \App\Models\User::ROLE_ADMIN_UNIT, \App\Models\User::ROLE_PIMPINAN, \App\Models\User::ROLE_SUPERADMIN])) {
        $menu[] = ['label' => 'Monitoring', 'route' => route('monitoring.index'), 'icon' => 'chart', 'active' => request()->routeIs('monitoring.*')];
    }
    if ($user->role === \App\Models\User::ROLE_SUPERADMIN) {
        $menu[] = ['label' => 'Pengaturan', 'route' => route('pengaturan.index'), 'icon' => 'settings', 'active' => request()->routeIs('pengaturan.*')];
    }
@endphp

<!-- Overlay mobile -->
<div id="sidebar-backdrop" class="hidden fixed inset-0 bg-navy-950/50 backdrop-blur-sm z-40 lg:hidden"></div>

<aside id="sidebar"
       class="no-print fixed lg:sticky top-0 left-0 z-50 h-screen w-72 bg-navy-900 flex flex-col -translate-x-full lg:translate-x-0 shrink-0">
    <div class="flex">
        <div class="flex-1 flex items-center gap-3 px-4 py-4 sidebar-brand">
            <img src="{{ asset('images/logo.png') }}" alt="Logo Kementerian Transmigrasi" class="w-10 h-10 rounded-lg bg-white/90 p-0.5 shrink-0"/>
            <div class="sidebar-brand-text leading-tight">
                <p class="text-white font-extrabold text-sm tracking-wide">TRANSKINERJA</p>
                <p class="text-navy-200/80 text-[10px]">Kementerian Transmigrasi RI</p>
            </div>
        </div>
        <button id="btn-sidebar-close" class="lg:hidden text-navy-200 hover:text-white px-4" aria-label="Tutup menu">
            <x-icon name="x" class="w-5 h-5"/>
        </button>
    </div>

    <nav class="flex-1 overflow-y-auto px-3 py-3 space-y-1">
        @foreach ($menu as $item)
            <a href="{{ $item['route'] }}" class="sidebar-link {{ $item['active'] ? 'active' : '' }}" title="{{ $item['label'] }}">
                <x-icon :name="$item['icon']" class="icon"/>
                <span class="sidebar-label">{{ $item['label'] }}</span>
            </a>
        @endforeach
    </nav>

    <div class="px-3 pb-4">
        <div class="rounded-xl bg-white/5 border border-white/10 p-3 flex items-center gap-3">
            <div class="w-9 h-9 rounded-full bg-gradient-to-br from-gold-400 to-gold-500 flex items-center justify-center text-navy-900 font-bold text-sm shrink-0">
                {{ strtoupper(substr($user->name, 0, 1)) }}
            </div>
            <div class="sidebar-text min-w-0">
                <p class="text-white text-xs font-semibold truncate">{{ $user->name }}</p>
                <p class="text-navy-300 text-[10px] truncate">{{ $user->roleLabel() }}</p>
            </div>
        </div>
    </div>
</aside>
