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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Finlandica+Text:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="m-0 bg-base font-sans text-ink">
    {{-- Layout owns the page chrome: full-height flex column + shared
         footer. The page slot fills the top; footer pins to the bottom. --}}
    <div class="flex min-h-screen flex-col">
        {{ $slot }}

        <footer class="mt-auto border-t border-sky/60 py-8">
            <div class="mx-auto max-w-3xl px-5 flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
                <p class="text-[13px] font-semibold text-muted">SlipNote</p>
                <p class="text-[13px] text-muted">
                    <a href="{{ route('privacy') }}" class="hover:text-neon">Privacy</a>
                    &middot;
                    <a href="{{ route('terms') }}" class="hover:text-neon">Terms</a>
                    &middot;
                    {{ date('Y') }}
                </p>
            </div>
        </footer>
    </div>

    @livewireScripts
</body>
</html>
