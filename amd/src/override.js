define([], function() {
    let initialised = false;

    const replaceInfoParam = (url) => {
        try {
            const parsed = new URL(url, window.location.origin);
            if (!parsed.pathname.endsWith('/lib/ajax/service.php')) {
                return url;
            }
            if (parsed.searchParams.get('info') !== 'core_enrol_get_potential_users') {
                return url;
            }
            parsed.searchParams.set('info', 'local_restrictedenrol_get_potential_users');
            return parsed.toString();
        } catch (e) {
            return url;
        }
    };

    const shouldLog = (config) => !!(config && config.debug && window.console);

    const replaceMethodName = (body) => {
        if (typeof body !== 'string' || body.indexOf('core_enrol_get_potential_users') === -1) {
            return body;
        }

        try {
            const requests = JSON.parse(body);
            if (!Array.isArray(requests)) {
                return body;
            }

            let changed = false;
            requests.forEach((request) => {
                if (request && request.methodname === 'core_enrol_get_potential_users') {
                    request.methodname = 'local_restrictedenrol_get_potential_users';
                    changed = true;
                }
            });

            return changed ? JSON.stringify(requests) : body;
        } catch (e) {
            return body;
        }
    };

    const patchFetch = (config) => {
        if (!window.fetch || window.fetch.__restrictedenrolpatched) {
            return;
        }

        const originalFetch = window.fetch.bind(window);
        const wrappedFetch = function(resource, init) {
            let newresource = resource;
            let newinit = init;
            if (typeof resource === 'string') {
                const rewritten = replaceInfoParam(resource);
                if (rewritten !== resource && shouldLog(config)) {
                    console.log('[local_restrictedenrol] Rewrote fetch URL to custom external function.');
                }
                newresource = rewritten;
            } else if (resource && resource.url) {
                const rewritten = replaceInfoParam(resource.url);
                if (rewritten !== resource.url) {
                    if (shouldLog(config)) {
                        console.log('[local_restrictedenrol] Rewrote Request URL to custom external function.');
                    }
                    newresource = new Request(rewritten, resource);
                }
            }
            if (init && typeof init.body === 'string') {
                const rewrittenbody = replaceMethodName(init.body);
                if (rewrittenbody !== init.body) {
                    if (shouldLog(config)) {
                        console.log('[local_restrictedenrol] Rewrote fetch body to custom external function.');
                    }
                    newinit = Object.assign({}, init, {body: rewrittenbody});
                }
            }
            return originalFetch(newresource, newinit);
        };
        wrappedFetch.__restrictedenrolpatched = true;
        window.fetch = wrappedFetch;
    };

    const patchXHR = (config) => {
        if (!window.XMLHttpRequest || window.XMLHttpRequest.prototype.__restrictedenrolpatched) {
            return;
        }

        const originalOpen = window.XMLHttpRequest.prototype.open;
        window.XMLHttpRequest.prototype.open = function(method, url) {
            const rewritten = replaceInfoParam(url);
            if (rewritten !== url && shouldLog(config)) {
                console.log('[local_restrictedenrol] Rewrote XHR URL to custom external function.');
            }
            return originalOpen.apply(this, [method, rewritten].concat([].slice.call(arguments, 2)));
        };

        const originalSend = window.XMLHttpRequest.prototype.send;
        window.XMLHttpRequest.prototype.send = function(body) {
            const rewrittenbody = replaceMethodName(body);
            if (rewrittenbody !== body && shouldLog(config)) {
                console.log('[local_restrictedenrol] Rewrote XHR body to custom external function.');
            }
            return originalSend.call(this, rewrittenbody);
        };

        window.XMLHttpRequest.prototype.__restrictedenrolpatched = true;
    };

    return {
        init: function(config) {
            if (initialised) {
                return;
            }
            initialised = true;
            patchFetch(config || {});
            patchXHR(config || {});
        }
    };
});
