import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    open(event) {
        const { turboFrameId, url } = event.currentTarget.dataset;
        document.getElementById(turboFrameId).src = url;
    }
}
