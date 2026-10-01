<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <script>
        (() => {
            try {
                const savedTheme = localStorage.getItem('tbs-theme');
                document.documentElement.dataset.theme = savedTheme === 'dark' ? 'dark' : 'light';
            } catch {
                document.documentElement.dataset.theme = 'light';
            }
        })();
    </script>

    <title inertia>{{ config('app.name', 'TB Sumber Baru') }}</title>

    <!-- Font Plus Jakarta Sans di-self-host lewat @font-face di resources/css/app.css
             (Design System v1.2 §3.2) -- link Figtree/bunny.net lama sudah tidak dipakai. -->

    <!-- Scripts -->
    @routes
    @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
    @inertiaHead
</head>

<body class="font-sans antialiased">
    @inertia
</body>

</html>