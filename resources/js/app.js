import * as bootstrap from 'bootstrap';
import { toBlob } from 'html-to-image';
window.bootstrap = bootstrap;
window.hiveDialog = () => ({
    previous: null,
    init() { this.previous = document.activeElement; document.body.style.overflow = 'hidden'; this.$nextTick(() => this.$el.focus()); },
    destroy() { document.body.style.overflow = ''; this.previous?.focus(); },
    trap(event) {
        const controls = [...this.$el.querySelectorAll('button,a[href],input,select,textarea,[tabindex="0"]')].filter(el => !el.disabled && el.offsetParent !== null);
        const first = controls[0], last = controls.at(-1);
        if (event.shiftKey && (document.activeElement === first || document.activeElement === this.$el)) { event.preventDefault(); last?.focus(); }
        else if (!event.shiftKey && (document.activeElement === last || document.activeElement === this.$el)) { event.preventDefault(); first?.focus(); }
    }
});
async function prepareInvoice() {
    const invoice = document.querySelector('#invoice-sheet');
    if (!invoice) return;
    const copy = document.querySelector('#copy-invoice');
    const download = document.querySelector('#download-invoice');
    const status = document.querySelector('#invoice-status');
    let blob;
    try {
        await document.fonts.ready;
        await Promise.all([...invoice.querySelectorAll('img')].map(img => img.decode()));
        blob = await toBlob(invoice, { pixelRatio: 2, backgroundColor: '#fffaf0', skipFonts: true });
        if (!blob || blob.type !== 'image/png') throw new Error('PNG could not be prepared.');
        const url = URL.createObjectURL(blob);
        document.querySelector('#invoice-preview').src = url;
        download.href = url; download.download = `${invoice.dataset.number}.png`;
        download.classList.remove('disabled'); download.removeAttribute('aria-disabled');
        status.textContent = 'PNG ready. Preview below shows the exact image you can copy or download.';
        copy.disabled = !window.isSecureContext || !navigator.clipboard?.write || !window.ClipboardItem;
        if (copy.disabled) status.textContent += ' Image clipboard is unavailable here; use Download PNG.';
        copy.addEventListener('click', async () => {
            copy.disabled = true;
            try {
                await navigator.clipboard.write([new ClipboardItem({ 'image/png': blob })]);
                status.textContent = 'Invoice image copied. You can now paste it into a supported chat.';
            } catch (error) { status.textContent = 'Clipboard access was denied or is unavailable. Download PNG is ready.'; }
            finally { copy.disabled = false; }
        });
        download.addEventListener('click', () => { status.textContent = 'PNG download started.'; });
        window.addEventListener('pagehide', () => URL.revokeObjectURL(url), { once: true });
    } catch (error) { status.textContent = 'Could not prepare the invoice image. Reload this page to retry.'; }
}
document.addEventListener('DOMContentLoaded', prepareInvoice);
