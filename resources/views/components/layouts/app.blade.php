<!DOCTYPE html>
<html lang="en" class="antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ ($title ?? null) ? $title.' · SlipNote' : 'SlipNote' }}</title>
    <meta name="robots" content="noindex,nofollow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Finlandica+Text:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
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
