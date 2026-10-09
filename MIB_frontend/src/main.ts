import './assets/main.css'
// main.js or main.ts
import 'bootstrap/dist/css/bootstrap.min.css';
import PrimeVue from 'primevue/config';
import MibPreset from './assets/primevuePreset';
import GoogleLogin from 'vue3-google-login'


import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from './App.vue'
import router from './router'
import 'bootstrap-icons/font/bootstrap-icons.css';
import 'primeicons/primeicons.css';
// Loaded after Bootstrap so the shared design tokens win the cascade.
import './assets/design-system.css';
import Tooltip from 'primevue/tooltip';
import ToastService from 'primevue/toastservice';
import { vStickySidebar } from './directives/stickySidebar';
// import Echo from "laravel-echo";
// import Pusher from "pusher-js";

// declare global {
//   interface Window {
//     Echo: any;
//     Pusher: any;
//   }
// }


const app = createApp(App)
app.use(PrimeVue, {
    // Default theme configuration
    theme: {
        preset: MibPreset,
        options: {
            prefix: 'p',
            darkModeSelector: 'false',
            cssLayer: false
        }
    }
 });


app.use(GoogleLogin, {
  clientId: '370487796688-b74n3f6c2nk86is0777leutm018gge2l.apps.googleusercontent.com',
})

//  window.Pusher = Pusher;

//  window.Echo = new Echo({
//     broadcaster: "reverb",
//     key: import.meta.env.VITE_REVERB_APP_KEY,
//     wsHost: import.meta.env.VITE_REVERB_HOST,
//     wsPort: import.meta.env.VITE_REVERB_PORT,
//     forceTLS: false,
//     enabledTransports: ["ws"],
// });

// A failed API call inside one component (e.g. a timeout in onMounted) is logged instead of
// surfacing as an uncaught error that can break rendering or navigation.
app.config.errorHandler = (error, _instance, info) => {
  console.error(`Unhandled component error (${info}):`, error);
};

app.use(createPinia())
app.use(router)
app.use(PrimeVue);

app.directive('tooltip', Tooltip);
app.directive('sticky-sidebar', vStickySidebar);
app.use(ToastService);

app.mount('#app')
