let confirmation;
function ask(message, accept, cancel) {
    if (!confirmation) {
        confirmation = document.createElement('dialog');
        confirmation.className = 'admin-confirmation';
        confirmation.setAttribute('aria-labelledby', 'confirm-title');
        confirmation.setAttribute('aria-describedby', 'confirm-message');
        confirmation.innerHTML = '<h2 id="confirm-title">Confirm action</h2><p id="confirm-message"></p><div class="confirmation-actions"><button type="button" class="btn btn-light" data-cancel>Cancel</button><button type="button" class="btn btn-peach" data-confirm>Confirm</button></div>';
        document.body.append(confirmation);
    }
    if (confirmation.open) return;
    const previous = document.activeElement;
    confirmation.querySelector('p').textContent = message;
    let accepted = false;
    confirmation.querySelector('[data-confirm]').onclick = () => { accepted = true; confirmation.close(); };
    confirmation.querySelector('[data-cancel]').onclick = () => confirmation.close();
    confirmation.onclose = () => { previous?.focus(); if (accepted) accept(); else cancel?.(); };
    confirmation.showModal();
    confirmation.querySelector('[data-cancel]').focus();
}
export function themeConfirmations() {
    document.querySelectorAll('[wire\\:confirm]').forEach(el => {
        if (!el.__livewire_confirm || el.__livewire_confirm === el.__hive_confirm) return;
        el.__hive_confirm = (action, instead) => ask(el.getAttribute('wire:confirm'), action, instead);
        el.__livewire_confirm = el.__hive_confirm;
    });
}
