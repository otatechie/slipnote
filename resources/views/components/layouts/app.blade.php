<!DOCTYPE html>
<html lang="en" class="antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ ($title ?? null) ? $title.' · SlipNote' : 'SlipNote' }}</title>
    <meta name="robots" content="noindex,nofollow">
    <link rel="preconnect" href="https://api.fontshare.com">
    <link rel="preconnect" href="https://cdn.fontshare.com" crossorigin>
    <link href="https://api.fontshare.com/v2/css?f[]=satoshi@400,500,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="m-0 bg-base font-sans text-ink">
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
</body>
</html>
