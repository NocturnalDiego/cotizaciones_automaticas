<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        @if (!empty($appBranding?->logo_url))
            <link rel="icon" href="{{ $appBranding->logo_url }}">
            <link rel="apple-touch-icon" href="{{ $appBranding->logo_url }}">
        @endif

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600;plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="auth-shell antialiased">
        <div class="auth-bg-grid" aria-hidden="true"></div>
        <div class="auth-bg-orbit" aria-hidden="true"></div>
        <div class="auth-bg-shape auth-bg-shape-one" aria-hidden="true"></div>
        <div class="auth-bg-shape auth-bg-shape-two" aria-hidden="true"></div>

        <main class="min-h-screen grid lg:grid-cols-2">
            <section class="auth-side-info hidden lg:flex">
                <div class="auth-side-panel">
                    <h2 class="auth-side-title">Cotizaciones Automaticas</h2>
                    <p class="auth-side-text">
                        Centraliza clientes, productos y precios para responder rapido y con precision en cada oportunidad.
                    </p>
                    <ul class="auth-side-list">
                        <li>Pipeline ordenado para cotizaciones</li>
                        <li>Historial claro de propuestas</li>
                        <li>Operacion lista para escalar</li>
                    </ul>
                </div>
            </section>

            <section class="auth-panel-wrap">
                <div class="auth-panel">
                    {{ $slot }}
                </div>
            </section>
        </main>
    </body>
</html>
