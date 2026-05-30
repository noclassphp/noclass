import { createHttpClient } from './http.js';

function getBaseUrl() {
  const el = document.querySelector('meta[name="base-url"]');
  return el ? el.getAttribute('content') : '';
}

function setAlert(container, message) {
  if (!container) return;
  if (!message) {
    container.classList.add('hidden');
    container.textContent = '';
    return;
  }
  container.classList.remove('hidden');
  container.textContent = message;
}

function formToObject(form) {
  const fd = new FormData(form);
  const obj = {};
  for (const [k, v] of fd.entries()) {
    obj[k] = v;
  }
  return obj;
}

document.addEventListener('DOMContentLoaded', () => {
  const base = getBaseUrl().replace(/\/+$/, '');

  window.http = createHttpClient({
    baseURL: base,
    timeoutMs: 15000,
    retries: 0,
    onAuthError: (err) => {
      // If session expired during an AJAX call, send user to login
      if (err && err.status === 401) {
        window.location.href = base + '/login';
      }
    }
  });

  // AJAX forms (login/signup)
  document.querySelectorAll('form[data-ajax]').forEach((form) => {
    form.addEventListener('submit', async (ev) => {
      ev.preventDefault();

      const kind = form.getAttribute('data-ajax'); // 'login' | 'signup'
      const alertBox = form.closest('.card')?.querySelector('[data-alert="error"]');

      setAlert(alertBox, '');

      try {
        const data = formToObject(form);

        // POST to /login or /signup
        const res = await window.http.post('/' + kind, data);
        //console.log(res)

        const redirect = res?.data?.redirect;
        window.location.href = redirect || (base + '/dashboard');
      } catch (e) {
        const msg = e?.payload?.error || e?.error || e?.message || 'Something went wrong';
        setAlert(alertBox, msg);
      }
    });
  });

  // Logout button (AJAX)
  const logoutBtn = document.querySelector('[data-logout]');
  const logoutForm = document.getElementById('logoutForm');

  async function doLogout() {
    try {
      // Use the existing form action; wrapper will attach CSRF header
      await window.http.post('/logout', {});
      window.location.href = base + '/home';
    } catch (e) {
      // Fallback to normal form submit
      if (logoutForm) logoutForm.submit();
    }
  }

  if (logoutBtn) {
    logoutBtn.addEventListener('click', (ev) => {
      ev.preventDefault();
      doLogout();
    });
  }

  // If user clicks the logout form button, intercept and do AJAX
  if (logoutForm) {
    logoutForm.addEventListener('submit', (ev) => {
      ev.preventDefault();
      doLogout();
    });
  }
});
