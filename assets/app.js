import './stimulus_bootstrap.js';
import './styles/app.css';
import 'flowbite';
import * as Turbo from '@hotwired/turbo';

document.addEventListener('turbo:before-fetch-response', (event) => {
    console.log('turbo:before-fetch-response', event);
    const fetchResponse = event.detail.fetchResponse;
    console.log('fetchResponse', fetchResponse.succeeded, fetchResponse.redirected, fetchResponse.location);

    if (!fetchResponse.succeeded || !fetchResponse.redirected) return;

    const frame = document.querySelector('turbo-frame[busy]');
    console.log('frame', frame, frame.dataset.turboFormRedirect);
    if (!frame || !frame.dataset.turboFormRedirect) return;

    event.preventDefault();
    Turbo.visit(fetchResponse.location);
    console.log('Turbo.visit', fetchResponse.location);
});

document.addEventListener('turbo:frame-load', (event) => {
    console.log('turbo:frame-load');
    initFlowbite();
});