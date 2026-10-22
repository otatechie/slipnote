const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'

function focusables(root) {
    return Array.from(root.querySelectorAll(FOCUSABLE))
        .filter(el => el.getClientRects().length > 0)
}

export const vModalFocus = {
    mounted(el) {
        el._modalReturn = document.activeElement
        if (!el.hasAttribute('tabindex')) el.setAttribute('tabindex', '-1')
        const start = el.querySelector('[data-autofocus]') ?? focusables(el)[0] ?? el
        start.focus({ preventScroll: true })

        el._modalTrap = (e) => {
            if (e.key !== 'Tab') return
            const items = focusables(el)
            if (!items.length) { e.preventDefault(); return }
            const first = items[0]
            const last = items[items.length - 1]
            if (e.shiftKey && (document.activeElement === first || document.activeElement === el)) {
                e.preventDefault()
                last.focus()
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault()
                first.focus()
            }
        }
        el.addEventListener('keydown', el._modalTrap)
    },
    beforeUnmount(el) {
        el.removeEventListener('keydown', el._modalTrap)
        const back = el._modalReturn
        const inside = el.contains(document.activeElement) || document.activeElement === document.body
        if (back?.isConnected && inside) {
            // After this element leaves the DOM, or focus is lost with it.
            queueMicrotask(() => back.focus({ preventScroll: true }))
        }
    },
}
