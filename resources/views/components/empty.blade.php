{{-- Empty state: <x-empty teks="..." icon="book"/> --}}
@props(['teks' => 'Belum ada data.', 'sub' => null, 'icon' => 'search'])
<div class="flex flex-col items-center justify-center py-14 px-6 text-center">
    <div class="w-16 h-16 rounded-2xl bg-navy-50 flex items-center justify-center mb-4">
        <x-icon :name="$icon" class="w-8 h-8 text-navy-300"/>
    </div>
    <p class="text-sm font-semibold text-slate-600">{{ $teks }}</p>
    @if ($sub)
        <p class="text-xs text-slate-400 mt-1 max-w-sm">{{ $sub }}</p>
    @endif
    {{ $slot }}
</div>
