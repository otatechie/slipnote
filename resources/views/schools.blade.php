<x-layouts.app title="For schools" :indexable="true" description="SlipNote for schools: a free board for every class, uploads teachers can lock, and nothing to install.">
@php
    $field = 'block min-h-11 w-full rounded-xl border border-sky bg-surface px-3.5 text-[15px] text-ink shadow-inner placeholder:text-muted/70';
    $label = 'mb-1.5 block text-[13px] font-semibold text-ink';
    $tick = '<svg aria-hidden="true" class="mt-1 size-4 shrink-0 text-muted" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10.5l4 4 8-9"/></svg>';
@endphp
<div class="op mx-auto w-full max-w-2xl flex-1 px-4 pb-10 pt-8 sm:px-5 sm:pt-10">
    <header class="mb-6">
        <a href="{{ route('welcome') }}"
           class="-my-2.5 inline-flex min-h-11 items-center gap-2 text-[15px] font-bold text-ink transition hover:opacity-80">
            <img src="/logo-mark.png" alt="" class="size-6 shrink-0">
            SlipNote
        </a>
        <h1 class="op-title mt-3 text-[2.15rem] font-bold text-ink">For schools</h1>
        <p class="mt-2 text-[16px] leading-relaxed text-muted">
            Every class’s files in one place, on any phone.<br class="hidden sm:inline">
            Free for every class.
        </p>
    </header>

    <ul class="space-y-2 text-[15px] text-ink">
        <li class="flex gap-2.5">{!! $tick !!}<span><strong class="font-semibold">One board per class</strong>, with a course for each subject</span></li>
        <li class="flex gap-2.5">{!! $tick !!}<span><strong class="font-semibold">Uploads you can lock</strong>, so only teachers add files</span></li>
        <li class="flex gap-2.5">{!! $tick !!}<span><strong class="font-semibold">Nothing to install, no accounts</strong>: students just open the class’s link</span></li>
        <li class="flex gap-2.5">{!! $tick !!}<span><strong class="font-semibold">Invisible to search engines</strong>, with no directory to browse</span></li>
    </ul>

    <p class="mt-5 rounded-xl bg-sky/40 px-4 py-3 text-[14px] leading-relaxed text-ink">
        <strong class="font-semibold">Teaching children under 13?</strong>
        Lock uploads on the board, so only you add files and pupils just read and download. Our <a href="{{ route('terms') }}" class="font-medium underline decoration-muted/50 underline-offset-4">terms</a> require it.
    </p>

    <section class="mt-8">
        <h2 class="text-[17px] font-semibold text-ink">Start with one class today</h2>
        <p class="mt-1.5 text-[15px] leading-relaxed text-muted">
            Any teacher can make a board in a minute. Name it after the class, add a course for each subject, and post the link where the class already talks.
        </p>
        <a href="{{ route('start') }}"
           class="op-press mt-4 inline-flex min-h-11 items-center rounded-full bg-neon px-5 text-[15px] font-semibold text-white">
            Create a board
        </a>
    </section>

    <section id="ask" class="op-card mt-8 scroll-mt-6 px-5 py-5 sm:px-6">
        <h2 class="text-[17px] font-semibold text-ink">Become a pilot school</h2>
        <p class="mt-1.5 text-[15px] leading-relaxed text-muted">We’re building a school space with a few pilot schools, and you’d help shape it:</p>
        {{-- Dots, not ticks: none of this exists yet. --}}
        <ul class="mt-2 space-y-1.5 text-[15px] leading-relaxed text-ink">
            @foreach ([
                'Each teacher manages their own class’s board',
                'The school stays in control when staff leave or lose a link',
                'One page shows every class',
                'More storage, and help setting up',
            ] as $planned)
                <li class="flex gap-2.5"><span aria-hidden="true" class="mt-[0.6em] size-1.5 shrink-0 rounded-full bg-muted/60"></span><span>{{ $planned }}</span></li>
            @endforeach
        </ul>
        <p class="mt-3 text-[15px] leading-relaxed text-muted">Pilot schools get a lower price for life. Replying doesn’t commit you to anything.</p>

        @if (session('sent'))
            <p role="status" class="mt-4 rounded-xl bg-sky/40 px-4 py-3 text-[15px] font-medium text-ink">
                Thanks. We’ll be in touch soon.
            </p>
        @else
            <form method="POST" action="{{ route('schools.store') }}" class="mt-5 space-y-4" novalidate>
                @csrf
                <input type="hidden" name="started" value="{{ $started }}">
                {{-- Left empty by people; bots fill every field they find. --}}
                <div aria-hidden="true" class="absolute -left-[9999px] h-px w-px overflow-hidden">
                    <label for="website">Leave this empty</label>
                    <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
                </div>

                <div>
                    <label for="school" class="{{ $label }}">School</label>
                    <input id="school" name="school" value="{{ old('school') }}" required maxlength="120" autocomplete="organization"
                           class="{{ $field }}" @error('school') aria-invalid="true" aria-describedby="school-error" @enderror>
                    @error('school')<span id="school-error" role="alert" class="mt-1.5 block text-[13px] text-danger">{{ $message }}</span>@enderror
                </div>

                <div>
                    <label for="name" class="{{ $label }}">Your name and role <span class="font-normal text-muted">(optional)</span></label>
                    <input id="name" name="name" value="{{ old('name') }}" maxlength="80" autocomplete="name"
                           placeholder="e.g. Mrs Asante, Head of English" class="{{ $field }}">
                </div>

                <div>
                    <label for="contact" class="{{ $label }}">Phone, WhatsApp or email</label>
                    <input id="contact" name="contact" value="{{ old('contact') }}" required maxlength="120"
                           class="{{ $field }}" @error('contact') aria-invalid="true" aria-describedby="contact-error" @enderror>
                    @error('contact')<span id="contact-error" role="alert" class="mt-1.5 block text-[13px] text-danger">{{ $message }}</span>@enderror
                </div>

                <div>
                    <label for="classes" class="{{ $label }}">Number of classes <span class="font-normal text-muted">(optional)</span></label>
                    <input id="classes" name="classes" type="number" inputmode="numeric" min="1" max="500" value="{{ old('classes') }}"
                           class="{{ $field }} sm:max-w-40" @error('classes') aria-invalid="true" aria-describedby="classes-error" @enderror>
                    @error('classes')<span id="classes-error" role="alert" class="mt-1.5 block text-[13px] text-danger">{{ $message }}</span>@enderror
                </div>

                <div>
                    <label for="message" class="{{ $label }}">Anything else <span class="font-normal text-muted">(optional)</span></label>
                    <textarea id="message" name="message" rows="3" maxlength="800"
                              class="{{ $field }} resize-y py-2.5" @error('message') aria-invalid="true" aria-describedby="message-error" @enderror>{{ old('message') }}</textarea>
                    @error('message')<span id="message-error" role="alert" class="mt-1.5 block text-[13px] text-danger">{{ $message }}</span>@enderror
                </div>

                <div class="flex flex-wrap items-center gap-x-4 gap-y-2 pt-1">
                    <button type="submit"
                            class="op-press inline-flex min-h-11 cursor-pointer items-center rounded-full bg-neon px-5 text-[15px] font-semibold text-white">
                        Send
                    </button>
                    <p class="text-[13px] text-muted">Used only to reply to you. See the <a href="{{ route('privacy') }}" class="font-medium text-ink underline decoration-muted/50 underline-offset-4">privacy page</a>.</p>
                </div>
            </form>
        @endif
    </section>
</div>
</x-layouts.app>
