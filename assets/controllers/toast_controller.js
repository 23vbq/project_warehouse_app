import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        duration: { type: Number, default: 4000 },
    };

    connect() {
        this.timer = setTimeout(() => this.#remove(), this.durationValue);
    }

    disconnect() {
        clearTimeout(this.timer);
    }

    dismiss() {
        clearTimeout(this.timer);
        this.#remove();
    }

    #remove() {
        this.element.style.transition = 'opacity 200ms ease, transform 200ms ease';
        this.element.style.opacity = '0';
        this.element.style.transform = 'translateX(12px)';
        setTimeout(() => this.element.remove(), 200);
    }
}
