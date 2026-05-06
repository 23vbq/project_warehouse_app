import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ["toggle"]
    static values = {
        autoClose: { type: Boolean, default: false },
        group: { type: String, default: "" },
    }

    #currentRowId = null
    #onOtherOpened = null

    connect() {
        if (this.autoCloseValue) {
            this.#onOtherOpened = this.#handleOtherOpened.bind(this)
            document.addEventListener("accordion:opened", this.#onOtherOpened)
        }
    }

    disconnect() {
        if (this.#onOtherOpened) {
            document.removeEventListener("accordion:opened", this.#onOtherOpened)
        }
    }

    toggle({ params: { rowId, url } }) {
        const row = document.getElementById(rowId)
        const frame = row.querySelector("turbo-frame")
        const isHidden = row.classList.contains("hidden")

        if (isHidden) {
            frame.src = url
            row.classList.remove("hidden")
            this.toggleTarget.classList.add("rotate-180")
            this.#currentRowId = rowId

            if (this.autoCloseValue) {
                document.dispatchEvent(new CustomEvent("accordion:opened", {
                    detail: { rowId, group: this.groupValue },
                }))
            }
        } else {
            this.#closeRow(rowId)
        }
    }

    #closeRow(rowId) {
        const row = document.getElementById(rowId)
        if (!row) return
        row.querySelector("turbo-frame").src = ""
        row.classList.add("hidden")
        this.toggleTarget.classList.remove("rotate-180")
        this.#currentRowId = null
    }

    #handleOtherOpened(event) {
        if (!this.#currentRowId) return
        if (this.#currentRowId === event.detail.rowId) return
        if (this.groupValue && this.groupValue !== event.detail.group) return

        this.#closeRow(this.#currentRowId)
    }
}
