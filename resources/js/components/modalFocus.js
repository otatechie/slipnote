// v-modal-focus: what a native <dialog> gives for free, for our plain-div
// dialogs. On open, focus moves into the dialog; Tab wraps at both ends so it
// can't wander the page behind the scrim; on close, focus goes back to whatever
// opened it. Per element, so a confirm opened from inside a sheet traps on its
// own and hands focus back to the sheet's button when it closes.
//
// Put it on the element that carries v-if and role="dialog". First focus goes
// to [data-autofocus] if there is one, else the first visible control. Code that
// focuses a field itself after open (the course sheet, Suggest) still wins:
// this runs on mount, before their nextTick/timeout.
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
        // Not if the opener went with a page change, or if focus has already
        // moved on to something outside this dialog.
        const inside = el.contains(document.activeElement) || document.activeElement === document.body
        if (back?.isConnected && inside) {
            // After this element leaves the DOM, or focus is lost with it.
            queueMicrotask(() => back.focus({ preventScroll: true }))
        }
    },
}
