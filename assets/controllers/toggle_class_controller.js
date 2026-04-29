import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['item'];
    static values  = { class: String };

    toggle() {
        this.itemTargets.forEach(el => el.classList.toggle(this.classValue));
    }
}
