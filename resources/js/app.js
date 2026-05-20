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
