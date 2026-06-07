/**
 * noclass.js — NoClass™ HTTP Client
 *
 * Copyright 2024-2026 Danny Mbanguni
 * Licensed under the Apache License, Version 2.0.
 *
 * A lightweight fetch wrapper that understands the NoClass API response shape,
 * handles CSRF tokens automatically, and respects BASE_URI for subfolder deploys.
 *
 * Zero dependencies. Vanilla ES6. ~3kb unminified.
 *
 * ── SETUP ─────────────────────────────────────────────────────────────────────
 *
 * Add to your layout's <head>:
 *
 *   <meta name="base-url"    content="<?= url('') ?>">
 *   <meta name="csrf-token"  content="<?= csrf_token() ?>">
 *
 * Include the script:
 *
 *   <script src="<?= asset('js/noclass.js') ?>"></script>
 *
 * ── USAGE ─────────────────────────────────────────────────────────────────────
 *
 *   // GET
 *   nc.get('dashboard/stats')
 *     .then(data => console.log(data))
 *     .catch(err => nc.flash(err.message, 'danger'));
 *
 *   // POST with form data
 *   nc.post('licenses/store', { name: 'Acme', email: 'a@b.com' })
 *     .then(data => nc.redirect('licenses'))
 *     .catch(err => nc.flash(err.message, 'danger'));
 *
 *   // POST with FormData (file uploads)
 *   nc.post('releases/upload_file/1', new FormData(form))
 *     .then(data => nc.flash('Uploaded.', 'success'))
 *     .catch(err => nc.flash(err.message, 'danger'));
 *
 *   // DELETE
 *   nc.delete('apikeys/revoke/3')
 *     .then(() => nc.reload())
 *     .catch(err => nc.flash(err.message, 'danger'));
 *
 * ── RESPONSE SHAPE ────────────────────────────────────────────────────────────
 *
 * NoClass API responses use the shape produced by api_ok() / api_err():
 *   { ok: true,  data: ... }           → Promise resolves with data
 *   { ok: false, error: 'message' }    → Promise rejects with Error(message)
 *
 * Non-JSON responses and network errors also reject with a descriptive Error.
 *
 * ── HELPERS ───────────────────────────────────────────────────────────────────
 *
 *   nc.url(path)              Build a full URL respecting BASE_URI
 *   nc.flash(msg, type)       Show a flash message without page reload
 *   nc.redirect(path)         Redirect to a NoClass route
 *   nc.reload()               Reload the current page
 *   nc.confirm(msg, fn)       Confirm dialog then call fn() if confirmed
 *   nc.serialize(form)        Serialize a <form> element to a plain object
 */

(function (global) {
    'use strict';

    // ── Configuration ─────────────────────────────────────────────────────────

    /**
     * Read the base URL from <meta name="base-url">.
     * Falls back to window.location.origin + pathname up to the first segment.
     */
    function baseUrl() {
        var meta = document.querySelector('meta[name="base-url"]');
        if (meta) return meta.getAttribute('content').replace(/\/$/, '');
        return window.location.origin;
    }

    /**
     * Read the CSRF token from <meta name="csrf-token">.
     * Falls back to a hidden input named csrf_token on the page.
     */
    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) return meta.getAttribute('content') || '';
        var input = document.querySelector('input[name="csrf_token"]');
        if (input) return input.value || '';
        return '';
    }

    /**
     * Refresh the CSRF token in the meta tag after a successful mutating request.
     * The server includes a new token in X-CSRF-Token response header when rotating.
     */
    function refreshCsrfToken(response) {
        var newToken = response.headers.get('X-CSRF-Token');
        if (newToken) {
            var meta = document.querySelector('meta[name="csrf-token"]');
            if (meta) meta.setAttribute('content', newToken);
        }
    }

    // ── URL builder ───────────────────────────────────────────────────────────

    /**
     * Build a full URL from a relative path.
     * Handles paths that already start with http:// or https://.
     */
    function buildUrl(path) {
        path = String(path || '').replace(/^\/+/, '');
        if (/^https?:\/\//i.test(path)) return path;
        var base = baseUrl();
        return path ? base + '/' + path : base;
    }

    // ── Core fetch ────────────────────────────────────────────────────────────

    /**
     * Core request function. All nc.get/post/put/delete route through here.
     *
     * @param {string} method   HTTP method
     * @param {string} path     Route path or full URL
     * @param {*}      body     Plain object, FormData, or null
     * @param {object} opts     Additional fetch options
     * @returns {Promise}       Resolves with response data, rejects with Error
     */
    function request(method, path, body, opts) {
        var url     = buildUrl(path);
        var headers = {};
        var isMutating = method !== 'GET' && method !== 'HEAD';

        // Inject CSRF token on mutating requests
        if (isMutating) {
            var token = csrfToken();
            if (token) headers['X-CSRF-Token'] = token;
        }

        // Build fetch init
        var init = Object.assign({ method: method, headers: headers }, opts || {});

        if (body !== null && body !== undefined) {
            if (body instanceof FormData) {
                // Let the browser set Content-Type with boundary for multipart
                init.body = body;
                // Still inject CSRF into the FormData itself as a fallback
                if (isMutating && csrfToken() && !body.has('csrf_token')) {
                    body.append('csrf_token', csrfToken());
                }
            } else if (typeof body === 'object') {
                headers['Content-Type'] = 'application/json';
                // Include CSRF in JSON body as well as header
                if (isMutating && csrfToken()) {
                    body = Object.assign({}, body, { csrf_token: csrfToken() });
                }
                init.body = JSON.stringify(body);
            } else {
                init.body = body;
            }
        }

        // Accept JSON responses
        headers['Accept'] = 'application/json';
        headers['X-Requested-With'] = 'XMLHttpRequest';

        return fetch(url, init)
            .then(function (response) {
                refreshCsrfToken(response);

                // Handle 204 No Content
                if (response.status === 204) return null;

                var contentType = response.headers.get('content-type') || '';

                if (contentType.indexOf('application/json') === -1) {
                    // Non-JSON response
                    if (!response.ok) {
                        return Promise.reject(
                            new Error('Server error ' + response.status + ': ' + response.statusText)
                        );
                    }
                    return response.text();
                }

                return response.json().then(function (json) {
                    // NoClass API shape: { ok: bool, data: *, error: string }
                    if (typeof json.ok !== 'undefined') {
                        if (json.ok === true) {
                            return typeof json.data !== 'undefined' ? json.data : json;
                        } else {
                            var err = new Error(json.error || 'Request failed');
                            err.status  = response.status;
                            err.details = json.details || null;
                            err.json    = json;
                            return Promise.reject(err);
                        }
                    }

                    // Non-NoClass JSON — return raw (external APIs etc.)
                    if (!response.ok) {
                        return Promise.reject(
                            new Error('Request failed with status ' + response.status)
                        );
                    }
                    return json;
                });
            })
            .catch(function (err) {
                // Re-throw our own errors unchanged
                if (err instanceof Error && err.status !== undefined) return Promise.reject(err);
                // Network / CORS errors
                if (err instanceof TypeError) {
                    return Promise.reject(new Error('Network error — check your connection.'));
                }
                return Promise.reject(err);
            });
    }

    // ── Flash messages ────────────────────────────────────────────────────────

    /**
     * Show a flash message in the page without a reload.
     * Looks for an element with class 'flash-container' or prepends to <main>.
     * Uses the same .alert CSS classes as the server-rendered flash partial.
     *
     * @param {string} message  Message text (HTML-escaped internally)
     * @param {string} type     'success' | 'danger' | 'warning' | 'info' | 'neutral'
     * @param {number} ttl      Auto-dismiss after ms (default 4000, 0 = never)
     */
    function flash(message, type, ttl) {
        type = type || 'info';
        ttl  = (ttl === undefined) ? 4000 : ttl;

        var container = document.querySelector('.flash-container')
                     || document.querySelector('main')
                     || document.body;

        var el = document.createElement('div');
        el.className = 'alert ' + type;
        el.style.cssText = 'margin-bottom:12px;animation:nc-fadein .2s ease';
        el.textContent = message;   // textContent — never innerHTML — safe against XSS

        // Dismiss on click
        el.style.cursor = 'pointer';
        el.addEventListener('click', function () { remove(el); });

        container.insertBefore(el, container.firstChild);

        if (ttl > 0) {
            setTimeout(function () { remove(el); }, ttl);
        }

        return el;
    }

    function remove(el) {
        if (el && el.parentNode) {
            el.style.opacity = '0';
            el.style.transition = 'opacity .2s ease';
            setTimeout(function () {
                if (el.parentNode) el.parentNode.removeChild(el);
            }, 220);
        }
    }

    // ── Utilities ─────────────────────────────────────────────────────────────

    /**
     * Redirect to a NoClass route or full URL.
     */
    function redirect(path) {
        window.location.href = buildUrl(path);
    }

    /**
     * Reload the current page.
     */
    function reload() {
        window.location.reload();
    }

    /**
     * Show a confirmation dialog then call fn if confirmed.
     * Replaces the pattern: if (confirm('...')) { ... }
     *
     * @param {string}   message  Confirmation text
     * @param {Function} fn       Called if user confirms
     */
    function confirm(message, fn) {
        if (window.confirm(message)) fn();
    }

    /**
     * Serialize a <form> element to a plain object.
     * Handles checkboxes, multi-selects, and file inputs (skipped — use FormData).
     * Useful for nc.post(path, nc.serialize(form)).
     *
     * @param   {HTMLFormElement} form
     * @returns {object}
     */
    function serialize(form) {
        var data = {};
        var elements = form.elements;
        for (var i = 0; i < elements.length; i++) {
            var el = elements[i];
            if (!el.name || el.disabled || el.type === 'file' || el.type === 'submit') continue;
            if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) continue;
            if (el.type === 'select-multiple') {
                data[el.name] = [];
                for (var j = 0; j < el.options.length; j++) {
                    if (el.options[j].selected) data[el.name].push(el.options[j].value);
                }
            } else {
                data[el.name] = el.value;
            }
        }
        return data;
    }

    // ── Inject keyframe for flash animation ───────────────────────────────────

    (function () {
        if (document.getElementById('nc-styles')) return;
        var style = document.createElement('style');
        style.id  = 'nc-styles';
        style.textContent = '@keyframes nc-fadein{from{opacity:0;transform:translateY(-4px)}to{opacity:1;transform:none}}';
        document.head.appendChild(style);
    }());

    // ── Public API ────────────────────────────────────────────────────────────

    var nc = {

        /**
         * GET request.
         * @param  {string} path   Route path or full URL
         * @param  {object} opts   Optional fetch overrides
         * @returns {Promise}
         */
        get: function (path, opts) {
            return request('GET', path, null, opts);
        },

        /**
         * POST request.
         * @param  {string}          path   Route path or full URL
         * @param  {object|FormData} data   Request body
         * @param  {object}          opts   Optional fetch overrides
         * @returns {Promise}
         */
        post: function (path, data, opts) {
            return request('POST', path, data || {}, opts);
        },

        /**
         * PUT request.
         * @param  {string}          path   Route path or full URL
         * @param  {object|FormData} data   Request body
         * @param  {object}          opts   Optional fetch overrides
         * @returns {Promise}
         */
        put: function (path, data, opts) {
            return request('PUT', path, data || {}, opts);
        },

        /**
         * DELETE request.
         * @param  {string} path   Route path or full URL
         * @param  {object} opts   Optional fetch overrides
         * @returns {Promise}
         */
        delete: function (path, opts) {
            return request('DELETE', path, null, opts);
        },

        /**
         * PATCH request.
         * @param  {string}          path   Route path or full URL
         * @param  {object|FormData} data   Request body
         * @param  {object}          opts   Optional fetch overrides
         * @returns {Promise}
         */
        patch: function (path, data, opts) {
            return request('PATCH', path, data || {}, opts);
        },

        /**
         * Build a full URL from a relative route path.
         * Respects BASE_URI for subfolder deployments.
         */
        url: buildUrl,

        /**
         * Show a flash message without a page reload.
         * Uses the same .alert CSS classes as server-rendered flash partials.
         *
         * nc.flash('Saved successfully.', 'success');
         * nc.flash('Something went wrong.', 'danger');
         * nc.flash('Please check your input.', 'warning');
         * nc.flash('Note: support expires soon.', 'info', 0);  // no auto-dismiss
         */
        flash: flash,

        /**
         * Redirect to a NoClass route or full URL.
         * nc.redirect('licenses');          → /licenses
         * nc.redirect('licenses/show/42');  → /licenses/show/42
         */
        redirect: redirect,

        /** Reload the current page. */
        reload: reload,

        /**
         * Confirm then execute.
         * nc.confirm('Delete this item?', () => nc.delete('items/delete/1').then(nc.reload));
         */
        confirm: confirm,

        /**
         * Serialize a <form> element to a plain object for nc.post().
         * nc.post('settings/save', nc.serialize(document.getElementById('settings-form')));
         */
        serialize: serialize,

        /** NoClass.js version */
        version: '1.0.0',
    };

    // ── Export ────────────────────────────────────────────────────────────────

    // CommonJS / Node (for testing)
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = nc;
    }
    // Browser global
    global.nc = nc;

}(typeof window !== 'undefined' ? window : this));
