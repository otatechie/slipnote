import { createApp, h } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'

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

createInertiaApp({
    title: title => title ? `${title} · SlipNote` : 'SlipNote',
    resolve: name => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob('./pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el)
    },
})
