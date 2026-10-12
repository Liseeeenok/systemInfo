import { createApp } from 'vue';
import Dashboard from './components/Dashboard.vue';

const el = document.getElementById('app');

if (el) {
    let props = {};

    try {
        props = el.dataset.props ? JSON.parse(el.dataset.props) : {};
    } catch (e) {
        console.error('Failed to parse app props', e);
    }

    createApp(Dashboard, props).mount(el);
}
