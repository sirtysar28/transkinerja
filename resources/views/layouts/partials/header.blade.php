@php($auth = auth()->user())
<header class="no-print sticky top-0 z-30 bg-white/95 backdrop-blur border-b border-slate-200">
    <div class="flex items-center gap-3 px-4 md:px-6 py-3">
        {{-- Toggle sidebar --}}
        <button id="btn-sidebar-toggle" class="btn-ghost !p-2 -ml-1 rounded-lg" aria-label="Buka/tutup menu" title="Buka/tutup menu">
            <x-icon name="menu" class="w-5 h-5 text-navy-800"/>
        </button>

        {{-- Pencarian global --}}
        <form action="{{ route('pencarian') }}" method="GET" class="flex-1 max-w-md hidden sm:block" data-no-loader>
            <div class="relative">
                <x-icon name="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"/>
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari pegawai, kegiatan, unit kerja…"
                       class="input !pl-10 !py-2 !rounded-full !bg-slate-100 !border-transparent focus:!bg-white text-[13px]"/>
            </div>
        </form>
        <a href="{{ route('pencarian') }}" class="sm:hidden btn-ghost !p-2" aria-label="Pencarian"><x-icon name="search" class="w-5 h-5"/></a>

        <div class="flex-1"></div>

        {{-- Notifikasi --}}
        <div class="relative">
            <button data-dropdown-btn="#dropdown-notifikasi" class="btn-ghost !p-2 relative rounded-full" aria-label="Notifikasi">
                <x-icon name="bell" class="w-5 h-5 text-navy-800"/>
                @if ($jumlahBelumBaca > 0)
                    <span class="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] px-1 rounded-full bg-rose-500 text-white text-[10px] font-bold flex items-center justify-center">
                        {{ $jumlahBelumBaca > 9 ? '9+' : $jumlahBelumBaca }}
                    </span>
                @endif
            </button>
            <div id="dropdown-notifikasi" class="dropdown-menu sm:w-80">
                <div class="px-4 py-2.5 border-b border-slate-100 flex items-center justify-between">
                    <p class="text-xs font-bold text-navy-900 uppercase tracking-wide">Notifikasi</p>
                    <form action="{{ route('notifikasi.bacaSemua') }}" method="POST">
                        @csrf
                        <button class="text-[11px] font-semibold text-navy-600 hover:text-navy-800">Tandai terbaca</button>
                    </form>
                </div>
                <div class="max-h-80 overflow-y-auto">
                    @forelse ($notifikasiTerbaru as $n)
                        <a href="{{ $n->terbaca() ? ($n->tautan ?: route('notifikasi.index')) : route('notifikasi.baca', $n) }}" class="dropdown-item">
                            <span class="w-2 h-2 rounded-full mt-1.5 shrink-0 {{ $n->terbaca() ? 'bg-slate-200' : 'bg-navy-500' }}"></span>
                            <span class="min-w-0">
                                <span class="block text-[13px] font-semibold text-slate-800 truncate">{{ $n->judul }}</span>
                                <span class="block text-xs text-slate-500 line-clamp-2">{{ $n->pesan }}</span>
                                <span class="block text-[10px] text-slate-400 mt-0.5">{{ $n->created_at->translatedFormat('d M Y, H:i') }}</span>
                            </span>
                        </a>
                    @empty
                        <p class="px-4 py-8 text-center text-xs text-slate-400">Belum ada notifikasi.</p>
                    @endforelse
                </div>
                <div class="border-t border-slate-100">
                    <a href="{{ route('notifikasi.index') }}" class="dropdown-item justify-center !text-navy-600 font-semibold text-xs">Lihat semua notifikasi</a>
                </div>
            </div>
        </div>

        {{-- Profil --}}
        <div class="relative">
            <button data-dropdown-btn="#dropdown-profil" class="flex items-center gap-2.5 rounded-full pl-1 pr-2 py-1 hover:bg-slate-100 transition">
                <span class="w-8 h-8 rounded-full bg-gradient-to-br from-navy-600 to-navy-800 flex items-center justify-center text-white font-bold text-xs">
                    {{ strtoupper(substr($auth->name, 0, 1)) }}
                </span>
                <span class="hidden md:block text-left leading-tight">
                    <span class="block text-xs font-semibold text-navy-900">{{ \Illuminate\Support\Str::before($auth->name, ',') }}</span>
                    <span class="block text-[10px] text-slate-400">{{ $auth->roleLabel() }}</span>
                </span>
                <x-icon name="chevron-down" class="w-3.5 h-3.5 text-slate-400 hidden md:block"/>
            </button>
            <div id="dropdown-profil" class="dropdown-menu sm:w-60">
                <div class="px-4 py-3 border-b border-slate-100">
                    <p class="text-xs font-bold text-navy-900">{{ $auth->name }}</p>
                    <p class="text-[11px] text-slate-400">{{ $auth->nip ? 'NIP '.$auth->nip : $auth->username }}</p>
                </div>
                <a href="{{ route('pengaturan.index', ['tab' => 'profil']) }}" class="dropdown-item"><x-icon name="settings" class="w-4 h-4 text-slate-400"/> Profil & Kata Sandi</a>
                @if (auth()->user()->role === \App\Models\User::ROLE_SUPERADMIN)
                    <a href="{{ route('pengaturan.index', ['tab' => 'audit']) }}" class="dropdown-item"><x-icon name="file" class="w-4 h-4 text-slate-400"/> Audit Log</a>
                @endif
                <div class="border-t border-slate-100 mt-1.5">
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="dropdown-item w-full text-rose-600 hover:!bg-rose-50">
                            <x-icon name="logout" class="w-4 h-4"/> Keluar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>
