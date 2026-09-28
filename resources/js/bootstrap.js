/**
 * Shared browser setup loaded by app.js.
 *
 * Makes axios available as window.axios for HTTP requests. The X-Requested-With
 * header marks each request as AJAX, so Laravel answers errors and validation
 * failures with JSON instead of a redirect.
 *
 * Extending:
 * - Configure other global libraries, such as Laravel Echo, here.
 */
import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
