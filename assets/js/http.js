/**
 * NoClass™ PHP Procedural Framework
 *
 * Copyright 2024-2026 Danny Mbanginu
 *
 * Licensed under the Apache License, Version 2.0.
 * See the LICENSE file for details.
 */

// assets/js/http.js
// NoClass Fetch Wrapper
// - JSON + FormData support
// - CSRF header support: <meta name="csrf-token"> or <meta name="csrf_token">
// - Query params support for GET
// - Timeout per attempt using AbortController
// - Safe optional retries for idempotent requests by default
// - Consistent error object: { ok:false, status, error, payload, url, method }

function getCsrfToken() {
  const el = document.querySelector('meta[name="csrf-token"], meta[name="csrf_token"]');
  return el ? el.getAttribute('content') : null;
}

function buildUrl(baseURL, endpoint) {
  const raw = String(endpoint || '');

  if (/^https?:\/\//i.test(raw)) {
    return raw;
  }

  const b = String(baseURL || '').replace(/\/+$/, '');
  const e = raw.replace(/^\/+/, '');

  if (!b) return '/' + e;
  return `${b}/${e}`;
}

function appendQuery(url, params = {}) {
  const qs = new URLSearchParams();

  Object.entries(params || {}).forEach(([key, value]) => {
    if (value === undefined || value === null) return;

    if (Array.isArray(value)) {
      value.forEach(item => {
        if (item !== undefined && item !== null) qs.append(key, String(item));
      });
      return;
    }

    qs.append(key, String(value));
  });

  const query = qs.toString();
  if (!query) return url;

  return url + (url.includes('?') ? '&' : '?') + query;
}

async function parseResponseSafe(resp) {
  if (resp.status === 204 || resp.status === 205) return null;

  const text = await resp.text();
  if (!text) return null;

  const contentType = resp.headers.get('content-type') || '';
  if (contentType.toLowerCase().includes('application/json')) {
    try {
      return JSON.parse(text);
    } catch {
      return { raw: text };
    }
  }

  try {
    return JSON.parse(text);
  } catch {
    return { raw: text };
  }
}

function sleep(ms) {
  return new Promise(resolve => setTimeout(resolve, ms));
}

function normalizeError(err, { url, method }) {
  if (err && err.ok === false) return err;

  const isAbort = err && err.name === 'AbortError';

  return {
    ok: false,
    status: isAbort ? 408 : 0,
    error: isAbort ? 'Request timeout' : (err && err.message ? err.message : 'Network error'),
    payload: null,
    url,
    method,
  };
}

function shouldRetryRequest({ attempt, retries, method, status, retryUnsafe }) {
  if (attempt >= retries) return false;
  if ([401, 403, 404, 422].includes(status)) return false;

  const idempotent = ['GET', 'HEAD', 'OPTIONS', 'PUT', 'DELETE'].includes(method);
  return retryUnsafe || idempotent || status === 408 || status === 429 || status >= 500 || status === 0;
}

export function createHttpClient({
  baseURL = '',
  timeoutMs = 15000,
  retries = 0,
  retryDelayMs = 400,
  retryUnsafe = false,
  credentials = 'same-origin',
  onAuthError = null,
  onError = null,
} = {}) {
  async function request(endpoint, options = {}) {
    const method = String(options.method || 'GET').toUpperCase();
    const url = appendQuery(buildUrl(baseURL, endpoint), options.params || {});

    let attempt = 0;

    while (true) {
      const headers = new Headers(options.headers || {});
      const isFormData = options.body instanceof FormData;
      const hasBody = options.body !== undefined && options.body !== null && method !== 'GET' && method !== 'HEAD';

      if (!headers.has('X-Requested-With')) headers.set('X-Requested-With', 'XMLHttpRequest');
      if (!headers.has('Accept')) headers.set('Accept', 'application/json');

      if (hasBody && !isFormData && !headers.has('Content-Type')) {
        headers.set('Content-Type', 'application/json');
      }

      const needsCsrf = ['POST', 'PUT', 'PATCH', 'DELETE'].includes(method);
      const csrf = getCsrfToken();
      if (needsCsrf && csrf && !headers.has('X-CSRF-Token')) {
        headers.set('X-CSRF-Token', csrf);
      }

      const controller = new AbortController();
      const timer = setTimeout(() => controller.abort(), timeoutMs);

      const fetchOptions = {
        ...options,
        method,
        headers,
        signal: controller.signal,
        credentials,
      };

      delete fetchOptions.params;

      if (hasBody && !isFormData) {
        const contentType = headers.get('Content-Type') || '';
        const isJson = contentType.toLowerCase().includes('application/json');

        if (isJson && typeof fetchOptions.body === 'object' && !(fetchOptions.body instanceof Blob)) {
          fetchOptions.body = JSON.stringify(fetchOptions.body);
        }
      }

      try {
        const resp = await fetch(url, fetchOptions);
        const payload = await parseResponseSafe(resp);

        if (!resp.ok) {
          throw {
            ok: false,
            status: resp.status,
            error: payload && payload.error ? payload.error : `HTTP ${resp.status}`,
            payload,
            url,
            method,
          };
        }

        return payload;
      } catch (err) {
        const normalized = normalizeError(err, { url, method });

        if (normalized.status === 401 && typeof onAuthError === 'function') {
          try { onAuthError(normalized); } catch {}
        }

        const canRetry = shouldRetryRequest({
          attempt,
          retries,
          method,
          status: normalized.status,
          retryUnsafe,
        });

        if (!canRetry) {
          if (typeof onError === 'function') {
            try { onError(normalized); } catch {}
          }
          throw normalized;
        }

        attempt++;
        await sleep(retryDelayMs * attempt);
      } finally {
        clearTimeout(timer);
      }
    }
  }

  return {
    request,

    get(endpoint, options = {}) {
      return request(endpoint, { ...options, method: 'GET' });
    },

    post(endpoint, data = {}, options = {}) {
      return request(endpoint, { ...options, method: 'POST', body: data });
    },

    put(endpoint, data = {}, options = {}) {
      return request(endpoint, { ...options, method: 'PUT', body: data });
    },

    patch(endpoint, data = {}, options = {}) {
      return request(endpoint, { ...options, method: 'PATCH', body: data });
    },

    del(endpoint, options = {}) {
      return request(endpoint, { ...options, method: 'DELETE' });
    },

    form(endpoint, formData, options = {}) {
      return request(endpoint, { ...options, method: 'POST', body: formData });
    },

    _utils: {
      appendQuery,
      buildUrl: endpoint => buildUrl(baseURL, endpoint),
    },
  };
}
