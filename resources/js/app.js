import { createApp, h } from 'vue'
import { createInertiaApp, router } from '@inertiajs/vue3'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'

const THEME_KEY = 'slipnote-theme'
const THEME_OPTIONS = ['system', 'light', 'dark']

function systemTheme() {
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
}

function storedTheme() {
    const saved = window.localStorage.getItem(THEME_KEY)
    return THEME_OPTIONS.includes(saved) ? saved : 'system'
}

function applyTheme(theme) {
    const root = document.documentElement
    const active = THEME_OPTIONS.includes(theme) ? theme : 'system'
    const resolved = active === 'system' ? systemTheme() : active

    root.dataset.theme = active
    root.dataset.systemTheme = systemTheme()
    root.style.colorScheme = resolved
    window.localStorage.setItem(THEME_KEY, active)

    document.querySelectorAll('[data-theme-toggle]').forEach((toggle) => {
        const label = toggle.querySelector('[data-theme-label]')
        if (label) label.textContent = `Theme: ${active[0].toUpperCase()}${active.slice(1)}`
        toggle.dataset.activeTheme = active
        toggle.setAttribute('aria-label', `Switch theme mode. Current mode: ${active}.`)
        toggle.setAttribute('title', `Theme: ${active}`)
    })
}

function cycleTheme() {
    const current = storedTheme()
    const index = THEME_OPTIONS.indexOf(current)
    applyTheme(THEME_OPTIONS[(index + 1) % THEME_OPTIONS.length])
}

function initThemeToggle() {
    applyTheme(storedTheme())

    document.querySelectorAll('[data-theme-toggle]').forEach((toggle) => {
        if (toggle.dataset.themeReady === 'true') return
        toggle.dataset.themeReady = 'true'
        toggle.addEventListener('click', cycleTheme)
    })
}

function initLandingMotion() {
    const items = document.querySelectorAll('.lp [data-reveal]')
    if (!items.length || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return

    document.documentElement.classList.add('lp-motion-ready')

    if (!('IntersectionObserver' in window)) {
        items.forEach((item) => item.classList.add('is-visible'))
        return
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return
            entry.target.classList.add('is-visible')
            observer.unobserve(entry.target)
        })
    }, { threshold: 0.12, rootMargin: '0px 0px -8% 0px' })

    items.forEach((item) => observer.observe(item))
}

window.SlipNoteTheme = {
    apply: applyTheme,
    cycle: cycleTheme,
    init: initThemeToggle,
}

window.copyText = function (text) {
    if (navigator.clipboard && window.isSecureContext) {
        return navigator.clipboard.writeText(text).catch(() => legacyCopy(text))
    }
    return legacyCopy(text)
}

function legacyCopy(text) {
    return new Promise((resolve, reject) => {
        const ta = document.createElement('textarea')
        ta.value = text
        ta.style.position = 'fixed'
        ta.style.opacity = '0'
        document.body.appendChild(ta)
        ta.select()
        let ok = false
        try { ok = document.execCommand('copy') } catch (e) {}
        document.body.removeChild(ta)
        ok ? resolve() : reject(new Error('copy failed'))
    })
}

const themeMedia = window.matchMedia('(prefers-color-scheme: dark)')
if (themeMedia.addEventListener) {
    themeMedia.addEventListener('change', () => applyTheme(storedTheme()))
} else {
    themeMedia.addListener(() => applyTheme(storedTheme()))
}

// Blade pages can't use inline onclick= under the production CSP (script-src
// is 'self' plus a nonce; there is no 'unsafe-inline' and there must not
// be). One delegated listener reads data- attributes instead:
//   data-dialog-open="id"  opens <dialog id>
//   data-dialog-close      closes the enclosing <dialog>
//   data-copy="text"       copies text; the button reads "Copied" for 2s
function initBladeActions() {
    document.addEventListener('click', (event) => {
        const el = event.target.closest('[data-dialog-open], [data-dialog-close], [data-copy]')
        if (!el) return

        if (el.hasAttribute('data-dialog-open')) {
            document.getElementById(el.getAttribute('data-dialog-open'))?.showModal()
        } else if (el.hasAttribute('data-dialog-close')) {
            el.closest('dialog')?.close()
        } else {
            const label = el.textContent
            window.copyText(el.getAttribute('data-copy')).then(() => {
                el.textContent = 'Copied ✓'
                setTimeout(() => { el.textContent = label }, 2000)
            }).catch(() => {})
        }
    })
}

function initBladeUi() {
    initThemeToggle()
    initLandingMotion()
    initBladeActions()
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initBladeUi, { once: true })
} else {
    initBladeUi()
}

// Plain blade pages (welcome, operator, legal) have no Inertia root — only the
// app pages render one. Mounting Inertia without it throws (null el). Guard on
// the root's presence so this script is a no-op on blade pages.
if (document.getElementById('app')) {
    createInertiaApp({
        title: title => title ? `${title} · SlipNote` : 'SlipNote',
        resolve: name => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob('./pages/**/*.vue')),
        setup({ el, App, props, plugin }) {
            createApp({ render: () => h(App, props) })
                .use(plugin)
                .mount(el)

            initThemeToggle()

            // Re-wire the toggle after every Inertia navigation — the SPA swaps
            // the DOM so the new footer button needs a fresh listener.
            router.on('navigate', () => initThemeToggle())
        },
    })
}
