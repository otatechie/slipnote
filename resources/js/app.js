import { createApp, h } from 'vue'
import { createInertiaApp, router } from '@inertiajs/vue3'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'

import.meta.glob(['../fonts/**'])

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

    document.querySelectorAll('[data-theme-set]').forEach((button) => {
        button.setAttribute('aria-pressed', String(button.dataset.themeSet === active))
    })
}

function cycleTheme() {
    const current = storedTheme()
    const index = THEME_OPTIONS.indexOf(current)
    applyTheme(THEME_OPTIONS[(index + 1) % THEME_OPTIONS.length])
}

function initThemeToggle() {
    applyTheme(storedTheme())

    document.querySelectorAll('[data-theme-set]').forEach((button) => {
        if (button.dataset.themeReady === 'true') return
        button.dataset.themeReady = 'true'
        button.addEventListener('click', () => applyTheme(button.dataset.themeSet))
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

function initBladeActions() {
    document.querySelectorAll('dialog[data-dialog-autoopen]').forEach((dialog) => dialog.showModal())

    document.addEventListener('click', (event) => {
        const el = event.target.closest('[data-dialog-open], [data-dialog-close], [data-copy]')
        if (!el) return

        if (el.hasAttribute('data-dialog-open')) {
            // The dialog takes over, so fold the menu it was opened from.
            el.closest('details.op-menu')?.removeAttribute('open')
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

function initMenuDismiss() {
    document.addEventListener('click', (event) => {
        document.querySelectorAll('details.op-menu[open]').forEach((menu) => {
            if (!menu.contains(event.target)) menu.removeAttribute('open')
        })
    })
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return
        document.querySelectorAll('details.op-menu[open]').forEach((menu) => menu.removeAttribute('open'))
    })
}

function initBladeUi() {
    initThemeToggle()
    initBladeActions()
    initMenuDismiss()
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initBladeUi, { once: true })
} else {
    initBladeUi()
}

if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js').catch(() => {})
}

if (document.getElementById('app')) {
    createInertiaApp({
        title: title => title ? `${title} · SlipNote` : 'SlipNote',
        resolve: name => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob('./pages/**/*.vue')),
        setup({ el, App, props, plugin }) {
            createApp({ render: () => h(App, props) })
                .use(plugin)
                .mount(el)

            initThemeToggle()

            router.on('navigate', () => initThemeToggle())
        },
    })
}
