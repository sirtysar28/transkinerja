<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <meta name="description" content="TransKinerja — Aplikasi Pencatatan Kinerja ASN Kementerian Transmigrasi Republik Indonesia"/>
    <meta name="theme-color" content="#163D4F"/>
    <title>@yield('title', 'Dashboard') — {{ config('app.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}"/>
    <link rel="apple-touch-icon" href="{{ asset('images/favicon.png') }}"/>
    <link rel="manifest" href="{{ asset('manifest.json') }}"/>
    <link rel="preconnect" href="https://fonts.googleapis.com"/>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div id="page-loader"></div>

    <div class="min-h-screen flex">
        @include('layouts.partials.sidebar')

        <div class="flex-1 min-w-0 flex flex-col">
            @include('layouts.partials.header')

            <main class="flex-1 p-4 md:p-6 lg:p-8 space-y-6">
                @if ($errors->any())
                    <div class="card border-rose-200 bg-rose-50/60 px-4 sm:px-5 py-4">
                        <div class="flex items-start gap-3">
                            <x-icon name="x" class="w-5 h-5 text-rose-500 shrink-0 mt-0.5"/>
                            <div class="text-sm">
                                <p class="font-semibold text-rose-700">Periksa kembali isian Anda</p>
                                <ul class="mt-1 list-disc list-inside text-rose-600 space-y-0.5">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                @endif

                @yield('content')
            </main>

            {{-- Footer + navigasi bawah mobile (4 ikon) — beri ruang agar tak tertutup bar tetap bawah --}}
            <footer class="no-print px-6 pt-4 pb-20 md:pb-4 border-t border-slate-200 text-center text-xs text-slate-400">
                {{ now()->year }} &copy; TransKinerja — Kementerian Transmigrasi Republik Indonesia
            </footer>
        </div>
    </div>

    @include('layouts.partials.bottom-nav')

    <x-flash/>
    @stack('scripts')
</body>
</html>
