@extends('layouts.app')
@section('title', 'Pengaturan')

@section('content')
<div>
    <h1 class="page-title">Pengaturan</h1>
    <p class="page-sub">Profil akun{{ $isSuperadmin ? ', OTP &amp; keamanan, SMTP &amp; email, periode pelaporan, referensi, dan audit log' : ' Anda' }} — semua dalam satu halaman bertab.</p>
</div>

<div class="flex gap-1.5 flex-nowrap overflow-x-auto border-b border-slate-200 -mt-2 mb-6 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" data-tabs role="tablist">
    @foreach ($tabs as $key => $t)
        <button type="button" role="tab" data-tab="{{ $key }}"
                class="tab-btn shrink-0 whitespace-nowrap {{ $tabAktif === $key ? 'tab-aktif' : '' }}">
            <x-icon name="{{ $t['icon'] }}" class="w-4 h-4"/><span class="hidden min-[400px]:inline">{{ $t['label'] }}</span>
        </button>
    @endforeach
</div>

<div data-tab-panes>
    <div id="tab-profil" class="tab-pane {{ $tabAktif === 'profil' ? '' : 'hidden' }}">
        @include('pengaturan.sections.profil')
    </div>

    @if ($isSuperadmin)
        <div id="tab-otp" class="tab-pane {{ $tabAktif === 'otp' ? '' : 'hidden' }}">
            @include('pengaturan.sections.otp')
        </div>
        <div id="tab-smtp" class="tab-pane {{ $tabAktif === 'smtp' ? '' : 'hidden' }}">
            @include('pengaturan.sections.smtp')
        </div>
        <div id="tab-periode" class="tab-pane {{ $tabAktif === 'periode' ? '' : 'hidden' }}">
            @include('pengaturan.sections.periode')
        </div>
        <div id="tab-referensi" class="tab-pane {{ $tabAktif === 'referensi' ? '' : 'hidden' }}">
            @include('pengaturan.sections.referensi')
        </div>
        <div id="tab-audit" class="tab-pane {{ $tabAktif === 'audit' ? '' : 'hidden' }}">
            @include('pengaturan.sections.audit')
        </div>
    @endif
</div>
@endsection
