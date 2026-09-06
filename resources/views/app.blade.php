<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- The notification bell talks to a JSON endpoint with fetch() rather than
         through Inertia, so it needs the token the way any other form would. --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title inertia>{{ config('app.name', 'Touchline') }}</title>

    {{-- Resolve the theme before first paint. A provider that only runs after hydration
         shows a white flash to everyone who chose dark, once per navigation. --}}
    <script>
        (function () {
            try {
                var stored = localStorage.getItem('touchline.theme') || 'system'
                var dark = stored === 'dark' || (stored === 'system' &&
                    window.matchMedia('(prefers-color-scheme: dark)').matches)
                if (dark) document.documentElement.classList.add('dark')
            } catch (e) { /* private windows throw on localStorage; light is a fine default */ }
        })()
    </script>

    @routes
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    @inertiaHead
</head>
<body class="min-h-screen bg-background text-foreground antialiased">
    @inertia
</body>
</html>
