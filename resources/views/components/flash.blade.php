{{-- Toast flash notifikasi --}}
@props([])
@if (session('sukses'))
    <div class="toast bg-white border-emerald-200 text-emerald-700 shadow-card">
        <x-icon name="check-badge" class="w-5 h-5 text-emerald-500 shrink-0 mt-0.5"/>
        <span>{{ session('sukses') }}</span>
        <button data-toast-close class="ml-auto text-slate-400 hover:text-slate-600"><x-icon name="x" class="w-4 h-4"/></button>
    </div>
@endif
@if (session('gagal'))
    <div class="toast bg-white border-rose-200 text-rose-700 shadow-card">
        <x-icon name="x" class="w-5 h-5 text-rose-500 shrink-0 mt-0.5"/>
        <span>{{ session('gagal') }}</span>
        <button data-toast-close class="ml-auto text-slate-400 hover:text-slate-600"><x-icon name="x" class="w-4 h-4"/></button>
    </div>
@endif
