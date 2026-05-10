import { Controller } from "@hotwired/stimulus";
import TomSelect from "tom-select";

export default class extends Controller {
    static values = {
        template: String,
        fetchUrl: String,
    };

    connect() {
        const options = this.#getOptions();

        this.tomSelect = new TomSelect(this.element, options);
    }

    disconnect() {
        this.tomSelect.destroy();
    }

    #getOptions() {
        if (!this.templateValue) {
            return this.#getDefaultOptions();
        }
        
        return this.#getTemplateOptions(this.templateValue);
    }

    #baseOptions() {
        return {
            wrapperClass: 'ts-wrapper full',
            controlClass: 'ts-control',
            dropdownClass: 'ts-dropdown',
            dropdownContentClass: 'ts-dropdown-content',
            optionClass: 'ts-option',
            itemClass: 'item',
            dropdownParent: 'body',
        };
    }

    #getDefaultOptions() {
        return {
            ...this.#baseOptions(),
            allowEmptyOption: true,
        };
    }

    #getTemplateOptions(template) {
        const templates = {
            products: () => this.#productsTemplate(),
        };

        if (!templates[template]) {
            throw new Error(`[tom-select] Unknown template: "${template}"`);
        }

        return templates[template]();
    }

    #productsTemplate() {
        return {
            ...this.#baseOptions(),
            valueField: 'id',
            labelField: 'name',
            searchField: ['sku', 'ean', 'name'],
            preload: true,
            load: (query, callback) => {
                fetch(`${this.fetchUrlValue}?query=${encodeURIComponent(query)}`)
                    .then(response => response.json())
                    .then(data => callback(data))
                    .catch(() => callback());
            },
            render: {
                option: (data, escape) => `
                    <div class="px-2 py-1.5 cursor-pointer">
                        <div class="text-[12.5px] text-content-300 truncate leading-tight">${escape(data.name)}</div>
                        <div class="text-[11px] text-content-700 font-mono mt-0.5 leading-tight">
                            ${escape(data.sku)}${data.ean ? `<span class="text-content-700 opacity-40 mx-1">|</span>${escape(data.ean)}` : ''}
                        </div>
                    </div>
                `,
                item: (data, escape) => `
                    <div class="py-0.5 overflow-hidden flex-1 min-w-0">
                        <div class="text-[12.5px] text-content-50 truncate leading-tight">${escape(data.name)}</div>
                        <div class="text-[11px] text-content-700 font-mono leading-tight">
                            ${escape(data.sku)}${data.ean ? `<span class="opacity-40 mx-1">|</span>${escape(data.ean)}` : ''}
                        </div>
                    </div>
                `,
                no_results: () => `<div class="px-3 py-2.5 text-[12.5px] text-content-500 text-center">Brak wyników</div>`,
                loading: () => `<div class="px-3 py-2.5 text-[12.5px] text-content-500 text-center">Ładowanie…</div>`,
            },
        };
    }
}
