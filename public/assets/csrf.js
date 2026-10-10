/**
 * Menormalisasi alias loopback aplikasi dan menambahkan header CSRF ke fetch() non-GET same-origin.
 * Token dibaca dari <meta name="csrf-token"> yang dirender server di tiap halaman.
 */
(function () {
    var meta = document.querySelector('meta[name="csrf-token"]');
    if (!meta || !window.fetch) {
        return;
    }
    var headerMeta = document.querySelector('meta[name="csrf-header"]');
    var headerName = headerMeta ? headerMeta.content : 'X-CSRF-TOKEN';
    var token = meta.content;
    var originalFetch = window.fetch;

    function normalizeApplicationUrl(url) {
        try {
            var requestUrl = new URL(url, window.location.href);
            var pageUrl = new URL(window.location.href);
            var loopbackHosts = ['localhost', '127.0.0.1', '[::1]'];

            if (loopbackHosts.indexOf(requestUrl.hostname) !== -1
                && loopbackHosts.indexOf(pageUrl.hostname) !== -1
                && requestUrl.port === pageUrl.port) {
                requestUrl.protocol = pageUrl.protocol;
                requestUrl.host = pageUrl.host;
            }

            return requestUrl.href;
        } catch (error) {
            return url;
        }
    }

    window.fetch = function (input, init) {
        init = init || {};
        var method = (init.method || (input && input.method) || 'GET').toUpperCase();
        var url = typeof input === 'string' ? input : (input && input.url) || '';
        var normalizedUrl = normalizeApplicationUrl(url);
        var requestUrl = new URL(normalizedUrl, window.location.href);
        var isSameOrigin = requestUrl.origin === window.location.origin;

        if (normalizedUrl !== url) {
            input = input instanceof Request
                ? new Request(normalizedUrl, input)
                : normalizedUrl;
        }

        if (!/^(GET|HEAD|OPTIONS)$/.test(method) && isSameOrigin) {
            var headers = new Headers(init.headers || (input && input.headers) || {});
            if (!headers.has(headerName)) {
                headers.set(headerName, token);
            }
            init.headers = headers;
        }
        return originalFetch.call(this, input, init);
    };
})();
