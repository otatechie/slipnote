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
    {{ $head ?? '' }}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet">
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
                <p class="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 text-[13px] text-muted">
                    <a href="{{ route('privacy') }}" class="hover:text-neon">Privacy</a>
                    <span aria-hidden="true" class="text-muted/50">&middot;</span>
                    <a href="{{ route('terms') }}" class="hover:text-neon">Terms</a>
                    <span aria-hidden="true" class="text-muted/50">&middot;</span>
                    <a href="https://github.com/otatechie/slipnote" class="hover:text-neon" target="_blank" rel="noopener noreferrer">GitHub</a>
                </p>
                <p class="text-[12px] text-muted/70">&copy; {{ date('Y') }} SlipNote</p>
            </div>
        </footer>
    </div>
</body>
</html>
