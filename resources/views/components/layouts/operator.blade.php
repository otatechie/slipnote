<!DOCTYPE html>
<html lang="en" class="antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ ($title ?? 'Operator') }} · SlipNote</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" type="image/png" href="/favicon-32.png" sizes="32x32">
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
    <link rel="preload" href="/fonts/OpenRunde-Regular.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/OpenRunde-Semibold.woff2" as="font" type="font/woff2" crossorigin>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="m-0 bg-base font-sans text-ink">
    <div class="flex min-h-screen flex-col">
        {{ $slot }}

        <div class="mt-auto px-5 py-6">
            <div class="mx-auto flex max-w-5xl justify-end">
                <button type="button"
                        data-theme-toggle
                        aria-label="Switch theme mode"
                        class="op-press group inline-flex min-h-8 cursor-pointer items-center justify-center gap-1 rounded-full border border-sky/50 bg-surface/80 px-2.5 py-1 text-muted backdrop-blur-md sm:min-h-0 sm:px-2">
                    <svg aria-hidden="true" data-theme-icon="system" class="size-3.5 group-data-[active-theme=light]:hidden group-data-[active-theme=dark]:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="4" width="20" height="14" rx="2"/><path d="M8 21h8M12 18v3"/>
                    </svg>
                    <svg aria-hidden="true" data-theme-icon="light" class="hidden size-3.5 group-data-[active-theme=light]:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.5 1.5M17.5 17.5 19 19M19 5l-1.5 1.5M6.5 17.5 5 19"/>
                    </svg>
                    <svg aria-hidden="true" data-theme-icon="dark" class="hidden size-3.5 group-data-[active-theme=dark]:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 12.8A8 8 0 1 1 11.2 3 6 6 0 0 0 21 12.8z"/>
                    </svg>
                    <span data-theme-label class="text-[11px] font-semibold">Theme: System</span>
                </button>
            </div>
        </div>
    </div>
</body>
</html>
