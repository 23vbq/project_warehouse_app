import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["list", "prototype"];

    connect() {
        this.nextIndex = this.element.querySelectorAll("[data-line]").length;
    }

    addLine() {
        const html = this.prototypeTarget.innerHTML.replace(/__name__/g, this.nextIndex);
        this.listTarget.insertAdjacentHTML("beforeend", html);
        this.nextIndex++;
    }

    removeLine(event) {
        event.currentTarget.closest("[data-line]").remove();
    }
}
