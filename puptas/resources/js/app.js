import './bootstrap';
import '../css/app.css';
// import '../css/step1-form.css';
import 'font-awesome/css/font-awesome.min.css';

import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import VueTheMask from 'vue-the-mask'


const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        // Update axios CSRF token on initial page load
        if (props.initialPage.props.csrf_token && window.axios) {
            window.axios.defaults.headers.common['X-CSRF-TOKEN'] = props.initialPage.props.csrf_token;
        }
        
        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(VueTheMask)
            .mount(el);
            
        return app;
    },
    progress: {
        color: '#4B5563',
    },
});

// Update axios CSRF token on every Inertia navigation
router.on('navigate', (event) => {
    const csrfToken = event.detail.page.props.csrf_token;
    if (csrfToken && window.axios) {
        window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
    }
});
