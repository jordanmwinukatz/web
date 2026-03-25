// js/csrf_interceptor.js
// Automatically injects X-CSRF-TOKEN header into all fetch() and XMLHttpRequest 
// calls that mutate state (POST/PUT/DELETE/PATCH).

(function() {
    function getCsrfToken() {
        const match = document.cookie.match(/(^| )X-CSRF-TOKEN=([^;]+)/);
        return match ? decodeURIComponent(match[2]) : null;
    }

    // Intercept Fetch
    const originalFetch = window.fetch;
    window.fetch = async function() {
        let [resource, config] = arguments;
        
        const method = (config && config.method) ? config.method.toUpperCase() : 'GET';
        if (['POST', 'PUT', 'DELETE', 'PATCH'].includes(method)) {
            const token = getCsrfToken();
            if (token) {
                config = config || {};
                
                // Ensure headers exist
                if (!config.headers) {
                    config.headers = {};
                }
                
                // If headers is a Headers object
                if (config.headers instanceof Headers) {
                    config.headers.append('X-CSRF-TOKEN', token);
                } 
                // If headers is an array
                else if (Array.isArray(config.headers)) {
                    config.headers.push(['X-CSRF-TOKEN', token]);
                } 
                // If headers is a plain object
                else {
                    config.headers['X-CSRF-TOKEN'] = token;
                }
                arguments[1] = config;
            }
        }
        return originalFetch.apply(this, arguments);
    };

    // Intercept XMLHttpRequest
    const originalOpen = XMLHttpRequest.prototype.open;
    const originalSend = XMLHttpRequest.prototype.send;
    
    XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
        this._method = method.toUpperCase();
        return originalOpen.apply(this, arguments);
    };
    
    XMLHttpRequest.prototype.send = function(body) {
        if (this._method && ['POST', 'PUT', 'DELETE', 'PATCH'].includes(this._method)) {
            const token = getCsrfToken();
            if (token) {
                this.setRequestHeader('X-CSRF-TOKEN', token);
            }
        }
        return originalSend.apply(this, arguments);
    };
})();
