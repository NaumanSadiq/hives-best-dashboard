import * as bootstrap from 'bootstrap';
import './controls';
import './listings';
import './admin-tables';
import { toBlob } from 'html-to-image';
import { clipboardCapability, clipboardFailure } from './invoice-clipboard';
window.bootstrap = bootstrap;
window.hiveDialog = () => ({
    previous: null,
    init() { this.previous = document.activeElement; document.body.style.overflow = 'hidden'; this.$nextTick(() => this.$el.focus()); },
    destroy() { document.body.style.overflow = ''; this.previous?.focus(); },
    trap(event) {
        if (this.$el.hasAttribute('data-picker-open')) return;
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
        const width = invoice.offsetWidth;
        const height = Math.max(invoice.offsetHeight, invoice.scrollHeight);
        blob = await toBlob(invoice, {
            width, height, pixelRatio: 2, backgroundColor: '#ffffff', skipFonts: true,
        });
        if (!blob || blob.type !== 'image/png') throw new Error('PNG could not be prepared.');
        const url = URL.createObjectURL(blob);
        document.querySelector('#invoice-preview').src = url;
        download.href = url; download.download = `${invoice.dataset.number}.png`;
        download.classList.remove('disabled'); download.removeAttribute('aria-disabled');
        const capability = clipboardCapability();
        const readyMessage = 'PNG ready. Preview below shows the exact image you can copy or download.';
        status.textContent = capability.available ? readyMessage : `${readyMessage} ${capability.reason}`;
        copy.disabled = !capability.available;
        copy.setAttribute('aria-describedby', 'invoice-status');
        copy.addEventListener('click', async () => {
            copy.disabled = true;
            try {
                await navigator.clipboard.write([new ClipboardItem({ 'image/png': blob })]);
                status.textContent = 'Invoice image copied. You can now paste it into a supported chat.';
            } catch (error) { status.textContent = clipboardFailure(error); }
            finally { copy.disabled = false; }
        });
        download.addEventListener('click', () => { status.textContent = `PNG download started.${capability.available ? '' : ' '+capability.reason}`; });
        window.addEventListener('pagehide', () => URL.revokeObjectURL(url), { once: true });
    } catch (error) { status.textContent = 'Could not prepare the invoice image. Reload this page to retry.'; }
}
document.addEventListener('DOMContentLoaded', prepareInvoice);
