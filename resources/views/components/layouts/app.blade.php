<!DOCTYPE html>
<html lang="en" class="antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ ($title ?? null) ? $title.' · SlipNote' : 'SlipNote' }}</title>

    @php
        // Anything that isn't the marketing root is a per-workspace capability
        // URL (the slug IS the credential). Keep crawlers out so workspace
        // pages never end up in Google's index.
        $isPublic = request()->is('/');
    @endphp

    @if ($isPublic)
        <meta name="description" content="Share course notes with classmates. Free, no accounts, just a link.">
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="SlipNote">
        <meta property="og:title" content="SlipNote — Share course notes with classmates">
        <meta property="og:description" content="A lightweight, no-account board for sharing course materials. Free.">
        <meta name="twitter:card" content="summary">
    @else
        <meta name="robots" content="noindex,nofollow">
    @endif

    <link rel="canonical" href="{{ url()->current() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="m-0 bg-base font-sans text-ink">
    {{ $slot }}
    @livewireScripts
</body>
</html>
