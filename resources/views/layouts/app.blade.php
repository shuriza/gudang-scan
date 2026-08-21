<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, maximum-scale=1.0, user-scalable=no">
        <meta name="color-scheme" content="light">

        <title>{{ $title ?? config('app.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
    </head>
    <body class="h-full bg-slate-100 text-slate-900 antialiased select-none">
        <div class="mx-auto flex min-h-full max-w-md flex-col">
            <header class="sticky top-0 z-10 bg-slate-900 px-4 pb-3 text-white" style="padding-top: max(0.75rem, env(safe-area-inset-top))">
                <h1 class="text-lg font-semibold tracking-tight">{{ config('app.name') }}</h1>
                <p class="text-xs text-slate-400">Inventaris gudang berbasis scan</p>
            </header>

            <main class="flex-1 px-4 py-4">
                {{ $slot }}
            </main>

            <nav class="sticky bottom-0 z-10 overflow-x-auto border-t border-slate-200 bg-white text-center text-xs"
                 style="padding-bottom: env(safe-area-inset-bottom)">
                @php
                    $tabs = [
                        ['route' => 'dashboard', 'label' => 'Ringkas'],
                        ['route' => 'scan', 'label' => 'Scan'],
                        ['route' => 'products', 'label' => 'Produk'],
                        ['route' => 'history', 'label' => 'Riwayat'],
                        ['route' => 'locations', 'label' => 'Lokasi'],
                        ['route' => 'documents', 'label' => 'Dokumen'],
                        ['route' => 'opnames', 'label' => 'Opname'],
                        ['route' => 'reports', 'label' => 'Laporan'],
                        ['route' => 'alerts', 'label' => 'Alert'],
                        ['route' => 'settings', 'label' => 'Setelan'],
                    ];
                @endphp
            <div class="flex min-w-max">
                @foreach ($tabs as $tab)
                    <a href="{{ route($tab['route']) }}"
                       @class([
                           'min-w-[4.5rem] py-3 font-medium transition',
                           'text-slate-900' => request()->routeIs($tab['route']),
                           'text-slate-400' => ! request()->routeIs($tab['route']),
                       ])>
                        {{ $tab['label'] }}
                    </a>
                @endforeach
            </div>
            </nav>
        </div>

        @livewireScripts
    </body>
</html>
