import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['panel'];

    connect() {
        if (!this.hasPanelTarget) {
            console.error('Tooltip controller requires a panel target.');
            return;
        }

        this.timer = null;
        this.transitionEndHandler = null;
        this.initializeEvents();
    }

    initializeEvents() {
        this.element.addEventListener('mouseenter', this.show.bind(this));
        this.element.addEventListener('mouseleave', this.hide.bind(this));

        this.panelTarget.addEventListener('mouseenter', this.keepOpen.bind(this));
        this.panelTarget.addEventListener('mouseleave', this.hide.bind(this));
    }

    show(event) {
        clearTimeout(this.timer);

        // Cancel any pending transitionend that would add 'invisible'
        if (this.transitionEndHandler) {
            this.panelTarget.removeEventListener('transitionend', this.transitionEndHandler);
            this.transitionEndHandler = null;
        }

        const rect = event.currentTarget.getBoundingClientRect();
        const panel = this.panelTarget;

        panel.style.left = `${rect.left}px`;
        panel.style.top = `${rect.top - 8}px`;
        panel.style.transform = 'translateY(-100%)';

        panel.classList.remove('invisible', 'opacity-0');
        panel.classList.add('opacity-100');
    }

    hide() {
        this.timer = setTimeout(() => {
            const panel = this.panelTarget;
            panel.classList.add('opacity-0');
            panel.classList.remove('opacity-100');

            this.transitionEndHandler = () => {
                panel.classList.add('invisible');
                this.transitionEndHandler = null;
            };
            panel.addEventListener('transitionend', this.transitionEndHandler, { once: true });
        }, 300);
    }

    keepOpen() {
        clearTimeout(this.timer);
    }
}
