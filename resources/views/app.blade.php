<!DOCTYPE html>
<html lang="en" class="antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title inertia>SlipNote</title>
    <meta name="robots" content="noindex,nofollow">
    <script>
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
    {{-- Pliant is self-hosted (see @font-face in app.css): keeps the prod CSP
         happy (font-src 'self') and no visitor IPs go to Google. --}}
    <link rel="preload" href="/fonts/pliant-variable.woff2" as="font" type="font/woff2" crossorigin>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body class="m-0 bg-base font-sans text-ink">
    @inertia
</body>
</html>
