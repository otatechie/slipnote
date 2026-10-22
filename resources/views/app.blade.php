<!DOCTYPE html>
<html lang="en" class="antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title inertia>SlipNote</title>
    <meta name="robots" content="noindex,nofollow">
    @if (app(\App\Tenancy\Tenancy::class)->has())
        @php($ogWorkspace = app(\App\Tenancy\Tenancy::class)->current())
        @php($ogTitle = isset($ogCourse) ? $ogCourse->code.' · '.$ogWorkspace->name : $ogWorkspace->name)
        @php($ogUrl = isset($ogCourse) ? url('/'.$ogWorkspace->slug.'/c/'.$ogCourse->slug) : url('/'.$ogWorkspace->slug))
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="SlipNote">
        <meta property="og:title" content="{{ $ogTitle }} · SlipNote">
        <meta property="og:description" content="{{ isset($ogCourse) ? $ogCourse->title.' — notes, slides and past papers. Free, no accounts.' : 'A shared board for course notes, slides and past papers. Free, no accounts, no setup.' }}">
        <meta property="og:url" content="{{ $ogUrl }}">
        <meta property="og:image" content="{{ url('/og.png') }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $ogTitle }} · SlipNote">
        <meta name="twitter:image" content="{{ url('/og.png') }}">
    @endif
    @if (app(\App\Tenancy\Tenancy::class)->has())
        <link rel="manifest" href="{{ route('workspace.manifest', ['workspace' => $ogWorkspace->slug]) }}">
        <meta name="apple-mobile-web-app-title" content="{{ mb_strimwidth($ogWorkspace->name, 0, 12, '…') }}">
    @else
        <link rel="manifest" href="{{ route('manifest') }}">
    @endif
    <meta name="theme-color" content="#f3f5fa" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#1a1c20" media="(prefers-color-scheme: dark)">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" type="image/png" href="/favicon-32.png" sizes="32x32">
    <link rel="icon" type="image/png" href="/favicon-192.png" sizes="192x192">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <script nonce="{{ Vite::cspNonce() }}">
        (() => {
            const key = 'slipnote-theme'
            const options = new Set(['system', 'light', 'dark'])
            const saved = localStorage.getItem(key)
            const theme = options.has(saved) ? saved : 'system'
            const system = matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
            const resolved = theme === 'system' ? system : theme
            document.documentElement.dataset.theme = theme
            document.documentElement.dataset.systemTheme = system
            document.documentElement.style.colorScheme = resolved
        })()
    </script>
    <link rel="preload" href="{{ Vite::asset('resources/fonts/OpenRunde-Regular.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ Vite::asset('resources/fonts/OpenRunde-Semibold.woff2') }}" as="font" type="font/woff2" crossorigin>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body class="m-0 bg-base font-sans text-ink">
    @inertia
</body>
</html>
