{{-- Badge status log book: <x-status status="draft"/> --}}
@props(['status'])
@php($label = \App\Models\LogKegiatan::STATUS_LABEL[$status] ?? $status)
<span class="badge-{{ $status }}">{{ $label }}</span>
