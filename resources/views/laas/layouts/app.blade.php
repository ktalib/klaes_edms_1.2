<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'LAAS Portal')</title>
    <link rel="icon" href="{{ asset('assets/logo/laas-light-logo.jpeg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind = { config: { darkMode: 'class' } };
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        // Applied before first paint so a dark-mode user never sees a white flash.
        (function () {
            const t = localStorage.getItem('laas-theme');
            if (t === 'dark' || (!t && matchMedia('(prefers-color-scheme: dark)').matches))
                document.documentElement.classList.add('dark');
        })();
    </script>
    <script src="https://unpkg.com/lucide@0.429.0/dist/umd/lucide.min.js" crossorigin="anonymous"></script>
    <style>
        /* Palette taken from the LAAS mark: forest green, navy, gold. */
        :root {
            --laas-green: #1a6b3c;
            --laas-green-dark: #155a32;
            --laas-navy: #12305c;
            --laas-gold: #f0a500;
        }
        .laas-btn { background-color: var(--laas-green); }
        .laas-btn:hover { background-color: var(--laas-green-dark); }
    </style>
    @stack('styles')
</head>
<body class="min-h-screen bg-slate-100 dark:bg-gray-900 text-slate-900 dark:text-gray-100 antialiased" style="font-family: Inter, sans-serif;">
    @yield('body')

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (window.lucide) window.lucide.createIcons();
        });

        function laasToggleTheme() {
            const isDark = document.documentElement.classList.toggle('dark');
            localStorage.setItem('laas-theme', isDark ? 'dark' : 'light');
            if (window.lucide) window.lucide.createIcons();
        }
    </script>
    @stack('scripts')
</body>
</html>
