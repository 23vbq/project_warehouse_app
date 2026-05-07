import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static values = {
        autoClose: { type: Boolean, default: false },
    }

    toggle({ params: { rowId, url } }) {
        const row = document.getElementById(rowId)
        const isHidden = row.classList.contains("hidden")

        if (isHidden) {
            if (this.autoCloseValue) {
                this.#closeAll()
            }
            row.querySelector("turbo-frame").src = url
            row.classList.remove("hidden")
            this.#findToggleButton(rowId)?.classList.add("rotate-180")
        } else {
            this.#closeRow(rowId)
        }
    }

    #closeAll() {
        this.element.querySelectorAll("button[data-accordion-row-id-param]").forEach(btn => {
            const rowId = btn.dataset.accordionRowIdParam
            const row = document.getElementById(rowId)
            if (row && !row.classList.contains("hidden")) {
                this.#closeRow(rowId)
            }
        })
    }

    #closeRow(rowId) {
        const row = document.getElementById(rowId)
        if (!row) return
        row.querySelector("turbo-frame").src = ""
        row.classList.add("hidden")
        this.#findToggleButton(rowId)?.classList.remove("rotate-180")
    }

    #findToggleButton(rowId) {
        return this.element.querySelector(`button[data-accordion-row-id-param="${rowId}"]`)
    }
}
