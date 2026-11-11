import { themeConfirmations } from './confirmations';

const tooltips = new Map();
function labelTables() {
    themeConfirmations();
    tooltips.forEach((instance, el) => {
        if (!el.isConnected) { instance.dispose(); tooltips.delete(el); }
    });
    document.querySelectorAll('.listing-action[title],.order-pencil[title],.review-badge[title]').forEach(el => {
        if (tooltips.has(el)) return;
        const instance = new window.bootstrap.Tooltip(el, {container: 'body', trigger: 'hover focus', delay: {show: 250, hide: 0}});
        el.addEventListener('click', () => instance.hide());
        tooltips.set(el, instance);
    });
    document.querySelectorAll('.main-content .table').forEach(table => {
        const headers = [...table.querySelectorAll('thead th')].map(th => th.textContent.trim());
        if (!headers.length) return;
        table.classList.add('responsive-admin-table');
        table.querySelectorAll('tbody tr').forEach(row => {
            [...row.children].forEach((cell, index) => {
                if (cell.tagName === 'TD' && !cell.hasAttribute('colspan')) {
                    const label = headers[index] || 'Actions';
                    if (cell.dataset.label !== label) cell.dataset.label = label;
                }
            });
        });
    });
    document.querySelectorAll('label[for]').forEach(label => {
        const input = document.getElementById(label.htmlFor);
        label.classList.toggle('is-required', Boolean(input?.required));
    });
}
let frame;
document.addEventListener('DOMContentLoaded', () => {
    labelTables();
    new MutationObserver(() => {
        cancelAnimationFrame(frame); frame = requestAnimationFrame(labelTables);
    }).observe(document.body, {childList: true, subtree: true});
});
