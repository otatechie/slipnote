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

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initThemeToggle, { once: true })
} else {
    initThemeToggle()
}

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
