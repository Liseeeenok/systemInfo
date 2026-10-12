<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ config('app.name', 'SystemInfo') }}</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📊</text></svg>">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-950 text-slate-200 antialiased">
@php
    $appProps = [
        'screenInterval' => $screenInterval,
        'metricsInterval' => $metricsInterval,
        'user' => ['name' => auth()->user()->name, 'email' => auth()->user()->email],
        'logoutUrl' => route('logout'),
        'csrfToken' => csrf_token(),
        'routes' => [
            'current' => route('api.metrics.current'),
            'history' => route('api.metrics.history'),
            'screen' => route('api.screen.shot'),
        ],
    ];
@endphp

<div id="app"
     data-props="{{ json_encode($appProps, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}"></div>
</body>
</html>
