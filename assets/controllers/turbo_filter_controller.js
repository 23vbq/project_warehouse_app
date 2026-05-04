import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['frame', 'filterField'];
    static values = {
        url: String,
    };

    connect() {
        if (!this.hasFrameTarget) {
            console.error('TurboFilterController: No frame target found.');
            return;
        }

        this.#connectFilterEvents();

        this.filterDebounceTimeout = null;
    }

    disconnect() {
        this.element.removeEventListener('turbo:frame-load', this.frameLoadHandler);
    }

    #connectFilterEvents() {
        this.filterFieldTargets.forEach((field) => {
            if (field.type === 'text') {
                field.addEventListener('input', () => this.debounceFilter());
            } else {
                field.addEventListener('change', () => this.filter());
            }
        });
    }

    debounceFilter() {
        clearTimeout(this.filterDebounceTimeout);

        this.filterDebounceTimeout = setTimeout(() => {
            this.#handleFilter();
        }, 700);
    }

    filter() {
        clearTimeout(this.filterDebounceTimeout);
        this.#handleFilter();
    }

    #handleFilter() {
        const filters = this.#getFilters();

        const url = this.urlValue + (this.urlValue.includes('?') ? '&' : '?') + new URLSearchParams(filters).toString();

        this.frameTarget.src = url;
        console.log('Filtering with URL:', url);
    }

    #getFilters() {
        const filters = {};

        this.filterFieldTargets.forEach((field) => {
            if (field.value) {
                filters[field.name] = field.value;
            }
        });

        return filters;
    }
}