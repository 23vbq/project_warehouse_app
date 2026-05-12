import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ['quantityInput', 'unitPriceInput', 'totalPriceLabel', 'productSelect'];
    static values = {
        productFetchUrl: String,
    };

    connect() {
        if (
            !this.hasQuantityInputTarget
            || !this.hasUnitPriceInputTarget
            || !this.hasTotalPriceLabelTarget
            || !this.hasProductSelectTarget
        ) {
            console.error('[operation-line] Missing required targets');
            return;
        }

        if (!this.productFetchUrlValue) {
            console.error('[operation-line] Missing required value: productFetchUrl');
            return;
        }

        this.#initializeEvents();
    }

    #initializeEvents() {
        this.quantityInputTarget.addEventListener('input', this.recalculateTotalPrice.bind(this));
        this.unitPriceInputTarget.addEventListener('input', this.recalculateTotalPrice.bind(this));
        this.productSelectTarget.addEventListener('change', this.setDefaultUnitPrice.bind(this));
    }

    recalculateTotalPrice() {
        const quantity = parseFloat(this.quantityInputTarget.value) || 0;
        const unitPrice = parseFloat(this.unitPriceInputTarget.value) || 0;
        const totalPrice = quantity * unitPrice;

        this.totalPriceLabelTarget.textContent = totalPrice.toFixed(2);
    }

    setDefaultUnitPrice() {
        const selectedOptionValue = this.productSelectTarget.value;
        if (!selectedOptionValue) {
            return;
        }

        fetch(`${this.productFetchUrlValue}?id=${selectedOptionValue}`)
            .then(response => response.json())
            .then(data => {
                if (data && data.unitPrice) {
                    this.unitPriceInputTarget.value = data.unitPrice;
                    this.recalculateTotalPrice();
                }
            })
            .catch(error => {
                console.error('Error fetching product data:', error);
            });
    }
}