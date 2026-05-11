import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["list", "prototype"];

    addLine() {
        const html = this.prototypeTarget.innerHTML.replace(/__name__/g, this.#currentIndex());
        this.listTarget.insertAdjacentHTML("beforeend", html);
    }

    removeLine(event) {
        event.currentTarget.closest("[data-line]").remove();
    }

    #currentIndex() {
        return this.element.querySelectorAll("[data-line]").length + 1;
    }
}
