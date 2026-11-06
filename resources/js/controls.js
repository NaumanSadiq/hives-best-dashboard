import TomSelect from 'tom-select';
import flatpickr from 'flatpickr';

function trackValidity(component, input) {
    const wrapper=component.$el.parentElement;
    const update=()=>input.setAttribute('aria-invalid',String(Boolean(wrapper.querySelector(':scope > .invalid-feedback')?.textContent.trim())));
    update(); const observer=new MutationObserver(update); observer.observe(wrapper,{childList:true,subtree:true,characterData:true}); return observer;
}

function positionMenu(picker) {
    if(!picker.isOpen) return;
    const rect=picker.control.getBoundingClientRect(), below=innerHeight-rect.bottom-12, above=rect.top-12;
    const up=below<180 && above>below;
    picker.dropdown_content.style.maxHeight=Math.max(60,Math.min(220,(up?above:below)-16))+'px';
    const width=Math.min(rect.width,innerWidth-16);
    picker.dropdown.style.width=width+'px';
    picker.dropdown.style.left=(scrollX+Math.max(8,Math.min(rect.left,innerWidth-width-8)))+'px';
    picker.dropdown.style.top=(scrollY+(up?rect.top-picker.dropdown.offsetHeight:rect.bottom))+'px';
}

window.hiveSelect = value => ({
    value, picker: null, observer: null, reposition: null,
    init() {
        const input = this.$refs.control;
        const labelText = input.id ? document.querySelector(`label[for="${CSS.escape(input.id)}"]`)?.textContent.trim() : '';
        this.picker = new TomSelect(input, { create: false, allowEmptyOption: true, maxOptions: null, closeAfterSelect: true,
            dropdownParent: 'body',
            onChange: selected => { this.value = selected; },
            onDropdownOpen: () => { this.$el.closest('.modal')?.setAttribute('data-picker-open','true'); },
            onDropdownClose: () => { this.$el.closest('.modal')?.removeAttribute('data-picker-open'); }
        });
        this.picker.setValue(String(this.value ?? ''), true);
        const position=this.picker.positionDropdown.bind(this.picker);
        this.picker.positionDropdown=()=>{position();positionMenu(this.picker);};
        this.reposition=()=>this.picker.positionDropdown();
        window.addEventListener('scroll',this.reposition,true);
        input.setAttribute('aria-hidden','true'); input.tabIndex=-1;
        this.picker.control_input.setAttribute('aria-label', input.getAttribute('aria-label') || labelText || 'Choose an option');
        this.picker.control_input.setAttribute('aria-describedby', input.getAttribute('aria-describedby') || '');
        this.observer=trackValidity(this,this.picker.control_input);
        this.$watch('value', selected => { if (String(this.picker.getValue()) !== String(selected ?? '')) this.picker.setValue(String(selected ?? ''), true); });
        if(input.disabled) this.picker.disable();
        this.picker.control_input.addEventListener('keydown', event => { if(event.key === 'Escape' && this.picker.isOpen) { event.stopPropagation(); this.picker.close(); } }, true);
    },
    destroy() { window.removeEventListener('scroll',this.reposition,true); this.observer?.disconnect(); this.picker?.destroy(); }
});

window.hiveDate = (value, today) => ({
    value, picker: null, observer: null,
    init() {
        const input = this.$refs.control;
        this.picker = flatpickr(input, { dateFormat: 'Y-m-d', ariaDateFormat: 'F j, Y', now: today, allowInput: true, disableMobile: true, monthSelectorType: 'static',
            defaultDate: this.value || null, maxDate: input.getAttribute('max') || null, minDate: input.getAttribute('min') || null,
            appendTo: document.body,
            onChange: (_dates, date) => { this.value = date; },
            onOpen: () => { this.$el.closest('.modal')?.setAttribute('data-picker-open','true'); },
            onClose: () => { this.$el.closest('.modal')?.removeAttribute('data-picker-open'); input.value = this.value || ''; },
            onReady: (_dates, _text, picker) => {
                const footer = document.createElement('div'); footer.className = 'calendar-footer';
                for (const [label, action] of [['Clear', () => { this.value=''; picker.clear(); picker.close(); }], ['Today', () => { picker.setDate(today,true); picker.close(); }], ['Close', () => picker.close()]]) {
                    const button = document.createElement('button'); button.type='button'; button.textContent=label; button.className='btn btn-light btn-sm'; button.addEventListener('click',action); footer.append(button);
                }
                picker.calendarContainer.append(footer);
                picker.calendarContainer.setAttribute('aria-label', 'Choose a date');
                picker.calendarContainer.addEventListener('keydown', event => { if(event.key==='Escape') { event.stopPropagation(); input.focus(); picker.close(); } if(event.key==='Tab' && !event.shiftKey && event.target===footer.lastElementChild) { event.preventDefault(); input.focus(); picker.close(); } }, true);
            }
        });
        input.value = this.value || '';
        this.observer=trackValidity(this,input);
        input.addEventListener('input', () => { this.value=input.value; });
        const invalidEntry=()=>{
            const text=input.value;
            if(!text) return false;
            if(!/^\d{4}-\d{2}-\d{2}$/.test(text)) return true;
            const date=flatpickr.parseDate(text,'Y-m-d');
            return !date || flatpickr.formatDate(date,'Y-m-d')!==text || (input.max && text>input.max) || (input.min && text<input.min);
        };
        const preserve=event=>{ if(invalidEntry()) { event.stopImmediatePropagation(); this.value=input.value; this.picker.close(); input.value=this.value; } };
        input.addEventListener('blur',preserve,true);
        input.addEventListener('keydown',event=>{if(event.key==='Enter')preserve(event);},true);
        input.addEventListener('keydown', event => { if(event.key === 'Escape' && this.picker.isOpen) { event.stopPropagation(); this.picker.close(); input.focus(); } }, true);
        this.$watch('value', date => { if(input.value !== (date || '')) { this.picker.setDate(date || null, false); input.value=date || ''; } });
    },
    destroy() { this.observer?.disconnect(); this.picker?.destroy(); }
});

document.addEventListener('livewire:init', () => {
    const channel = 'BroadcastChannel' in window ? new BroadcastChannel('hive-inventory') : null;
    document.addEventListener('inventory-changed', () => channel?.postMessage('refresh'));
    if(channel) channel.onmessage = () => window.Livewire.dispatch('inventory-refresh');
    document.addEventListener('visibilitychange', () => { if(!document.hidden) window.Livewire.dispatch('inventory-refresh'); });
    document.addEventListener('focus-adjustment', () => { setTimeout(() => { document.querySelector('#stock-adjustments')?.scrollIntoView({behavior:'smooth'}); document.querySelector('#adjust-direction-ts-control')?.focus(); },50); });
});
