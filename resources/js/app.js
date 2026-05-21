/**
 * Copy text to the clipboard. Falls back to a hidden-textarea +
 * execCommand path when navigator.clipboard isn't available (e.g. on
 * plain-HTTP origins). Returns a Promise that resolves on success and
 * rejects when both paths fail.
 */
window.copyText = function (text) {
    if (navigator.clipboard && window.isSecureContext) {
        return navigator.clipboard.writeText(text).catch(() => legacyCopy(text));
    }
    return legacyCopy(text);
};

function legacyCopy(text) {
    return new Promise((resolve, reject) => {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        let ok = false;
        try { ok = document.execCommand('copy'); } catch (e) { /* fall through */ }
        document.body.removeChild(ta);
        ok ? resolve() : reject(new Error('copy failed'));
    });
}

/**
 * Owner-link receipt: Alpine component for the one-time owner-link card.
 *
 * Registered here (not as inline x-data) because inline x-data with
 * multi-line template literals is fragile — proxies, minifiers, and even
 * the browser's own attribute normalisation can mangle it. Defining it
 * in JS keeps the source bytes pristine and lets Alpine parse a tiny
 * x-data="ownerReceipt(...)" call instead.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('ownerReceipt', ({ ownerUrl, createdUrl, workspaceName, filename }) => ({
        saved: false,
        copied: false,
        copy() {
            window.copyText(ownerUrl).then(() => {
                this.copied = true;
                this.saved = true;
                setTimeout(() => { this.copied = false; }, 2000);
            }).catch(() => { /* user can still select the link manually */ });
        },
        download() {
            const body =
                'SlipNote owner link for "' + workspaceName + '"\n\n' +
                'OWNER (keep private - controls the workspace):\n' + ownerUrl + '\n\n' +
                'SHARE WITH CLASSMATES:\n' + createdUrl + '\n';
            const a = document.createElement('a');
            const blob = new Blob([body], { type: 'text/plain' });
            a.href = URL.createObjectURL(blob);
            a.download = filename;
            a.click();
            URL.revokeObjectURL(a.href);
            this.saved = true;
        },
    }));
});
