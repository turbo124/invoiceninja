/**
 * Axios
 *
 * Promise based HTTP client for the browser and node.js
 * https://github.com/axios/axios
 */
import axios from 'axios';
window.axios = axios;

/**
 * card-validator
 *
 * Validate credit cards as users type.
 * https://github.com/braintree/card-validator
 */
import valid from 'card-validator';
window.valid = valid;


/**
 * Remove flashing message div after 3 seconds.
 */
document.querySelectorAll('.disposable-alert').forEach((element) => {
    setTimeout(() => {
        element.remove();
    }, 5000);
});
