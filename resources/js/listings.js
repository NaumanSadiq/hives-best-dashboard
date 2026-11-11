window.hiveListing = () => ({
    observer: null, frame: null, resize: null,
    init() {
        this.resize = () => {
            cancelAnimationFrame(this.frame);
            this.frame = requestAnimationFrame(() => {
                const records = this.$refs.records;
                if (!records || !records.getClientRects().length) return;
                const footer = this.$refs.footer?.getBoundingClientRect().height || 0;
                const top = records.getBoundingClientRect().top;
                const cap = this.$el.dataset.recent === 'true' ? 400 : Infinity;
                const height = Math.max(180, Math.min(cap, window.innerHeight - top - footer - 26));
                const next = `${Math.floor(height)}px`;
                if (records.style.maxHeight !== next) records.style.maxHeight = next;
            });
        };
        this.observer = new ResizeObserver(this.resize);
        this.observer.observe(document.querySelector('.main-content'));
        this.observer.observe(this.$el);
        window.addEventListener('resize', this.resize);
        this.$nextTick(this.resize);
    },
    destroy() {
        this.observer?.disconnect();
        cancelAnimationFrame(this.frame);
        window.removeEventListener('resize', this.resize);
    }
});
