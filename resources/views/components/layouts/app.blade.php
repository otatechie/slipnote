<!DOCTYPE html>
<html lang="en" class="antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ ($title ?? null) ? $title.' · SlipNote' : 'SlipNote' }}</title>
    @if ($description ?? null)
        <meta name="description" content="{{ $description }}">
    @endif
    {{-- Workspace pages are capability URLs — keep them out of search.
         The marketing page (/) and legal pages opt in to indexing. --}}
    @if ($indexable ?? false)
        <meta name="robots" content="index,follow">
    @else
        <meta name="robots" content="noindex,nofollow">
    @endif
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
    {{ $head ?? '' }}
    {{-- Pliant is self-hosted (see @font-face in app.css): keeps the prod CSP
         happy (font-src 'self') and no visitor IPs go to Google. --}}
    <link rel="preload" href="/fonts/pliant-variable.woff2" as="font" type="font/woff2" crossorigin>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="m-0 bg-base font-sans text-ink">
    <div class="flex min-h-screen flex-col">
        {{ $slot }}

        <footer class="mt-auto border-t border-sky/60 py-8">
            <div class="mx-auto flex max-w-3xl flex-col items-center gap-3 px-5 text-center">
                <p class="flex items-center gap-1.5 text-[13px] font-semibold text-muted">
                    Made with <svg class="size-3.5 fill-none stroke-current text-teal" viewBox="0 0 20 20" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M10 17.25C10 17.25 2 12 2 6.5A4.5 4.5 0 0 1 10 3.914 4.5 4.5 0 0 1 18 6.5C18 12 10 17.25 10 17.25Z"/></svg> SlipNote
                </p>
                <div class="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 text-[13px] text-muted">
                    <a href="{{ route('privacy') }}" class="hover:text-neon">Privacy</a>
                    <span aria-hidden="true" class="text-muted/50">&middot;</span>
                    <a href="{{ route('terms') }}" class="hover:text-neon">Terms</a>
                    <span aria-hidden="true" class="text-muted/50">&middot;</span>
                    <a href="https://github.com/otatechie/slipnote" class="hover:text-neon" target="_blank" rel="noopener noreferrer">GitHub</a>
                    <span aria-hidden="true" class="text-muted/50">&middot;</span>
                    <button type="button"
                            data-theme-toggle
                            aria-label="Switch theme mode"
                            class="group inline-flex cursor-pointer items-center justify-center rounded-full border border-sky bg-surface p-1.5 text-muted transition hover:border-neon hover:text-neon">
                    <svg aria-hidden="true" data-theme-icon="system" class="size-4 group-data-[active-theme=light]:hidden group-data-[active-theme=dark]:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="4" width="20" height="14" rx="2"/><path d="M8 21h8M12 18v3"/>
                    </svg>
                    <svg aria-hidden="true" data-theme-icon="light" class="hidden size-4 group-data-[active-theme=light]:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.5 1.5M17.5 17.5 19 19M19 5l-1.5 1.5M6.5 17.5 5 19"/>
                    </svg>
                    <svg aria-hidden="true" data-theme-icon="dark" class="hidden size-4 group-data-[active-theme=dark]:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 12.8A8 8 0 1 1 11.2 3 6 6 0 0 0 21 12.8z"/>
                    </svg>
                    </button>
                </div>
                <p class="text-[12px] text-muted">&copy; {{ date('Y') }} SlipNote</p>
            </div>
        </footer>
    </div>
</body>
</html>
