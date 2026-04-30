import './stimulus_bootstrap.js';
import './styles/app.css';
import 'flowbite';
import * as Turbo from '@hotwired/turbo';

document.addEventListener('turbo:before-fetch-response', (event) => {
    const fetchResponse = event.detail.fetchResponse;

    if (!fetchResponse.succeeded || !fetchResponse.redirected) return;

    const frame = document.querySelector('turbo-frame[busy]');
    if (!frame || !frame.dataset.turboFormRedirect) return;

    event.preventDefault();
    Turbo.visit(fetchResponse.location);
});
