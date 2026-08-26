import axios from 'axios';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Session-CSRF fuer die PATCH/POST-Endpunkte.
window.axios.defaults.withCredentials = true;
