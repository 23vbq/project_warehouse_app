import './stimulus_bootstrap.js';
import './styles/app.css';
import 'flowbite';
import * as Turbo from '@hotwired/turbo';

document.addEventListener('turbo:before-fetch-response', (event) => {
    const location = event.detail.fetchResponse.response.headers.get('X-Turbo-Redirect');
    if (!location) return;
    event.preventDefault();
    Turbo.visit(location);
});

document.addEventListener('turbo:frame-load', (event) => {
    initFlowbite();
});

document.addEventListener('turbo:load', (event) => {
    initFlowbite();
});