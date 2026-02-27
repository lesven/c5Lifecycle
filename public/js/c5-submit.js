/**
 * C5 Evidence Tool – Form Submission
 * Data collection, POST to backend, success/error display.
 */
(function () {
  'use strict';

  window.C5 = window.C5 || {};

  /**
   * Collect all form data into a plain object.
   * @param {HTMLFormElement} form
   * @returns {Object}
   */
  C5.collectFormData = function (form) {
    var data = {};
    var elements = form.elements;
    for (var i = 0; i < elements.length; i++) {
      var el = elements[i];
      if (!el.name) continue;
      if (el.type === 'checkbox') {
        if (data[el.name] === undefined) {
          data[el.name] = el.checked;
        }
      } else if (el.type === 'radio') {
        if (el.checked) data[el.name] = el.value;
      } else if (el.type === 'submit') {
        continue;
      } else {
        data[el.name] = el.value;
      }
    }
    return data;
  };

  /**
   * Submit the form data to the backend API.
   * @param {HTMLFormElement} form
   */
  C5.submitForm = function (form) {
    var eventType = form.getAttribute('data-event');
    var data = C5.collectFormData(form);
    var statusEl = document.getElementById('form-status');
    var submitBtn = form.querySelector('.btn-submit');

    submitBtn.disabled = true;
    submitBtn.textContent = 'Sende …';
    statusEl.classList.add('hidden');

    fetch(C5.apiBase + '/submit/' + eventType, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data),
    })
      .then(C5.checkAuth)
      .then(function (res) {
        return res.text().then(function (text) {
          try {
            var body = JSON.parse(text);
            return { status: res.status, body: body };
          } catch (e) {
            throw new Error('Ungültige Server-Antwort (HTTP ' + res.status + ')');
          }
        });
      })
      .then(function (result) {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Evidence senden';

        if (result.body.success) {
          showSuccess(statusEl, result.body);
          C5.showSummary(data, result.body);
        } else {
          showError(statusEl, result.body.error || 'Unbekannter Fehler', result.body.request_id);
        }
      })
      .catch(function (err) {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Evidence senden';
        showError(statusEl, 'Verbindung zum Server fehlgeschlagen: ' + err.message);
      });
  };

  // ── Success / Error feedback ──

  function showSuccess(el, result) {
    el.className = 'submit-status success';
    var text = 'Evidence-Mail versendet.';
    if (result.jira_ticket) {
      text += ' · Jira-Ticket: ' + result.jira_ticket;
    }
    if (result.netbox_synced && result.netbox_status) {
      if (result.netbox_status === 'journal_created') {
        text += ' · NetBox: Journal Entry erstellt';
      } else {
        text += ' · NetBox-Status aktualisiert: ' + result.netbox_status;
      }
    }
    text += ' · Request-ID: ' + result.request_id;
    el.textContent = text;
    el.classList.remove('hidden');

    if (result.netbox_error) {
      var warning = document.createElement('div');
      warning.className = 'submit-status warning';
      warning.innerHTML = '<strong>NetBox-Synchronisation fehlgeschlagen:</strong> ' + C5.escapeHtml(result.netbox_error);
      if (result.netbox_error_trace) {
        var pre = document.createElement('pre');
        pre.className = 'netbox-trace';
        pre.textContent = result.netbox_error_trace;
        warning.appendChild(pre);
      }
      el.parentNode.insertBefore(warning, el.nextSibling);
    }
  }

  function showError(el, message, requestId) {
    el.className = 'submit-status error';
    var text = message;
    if (requestId) text += ' · Request-ID: ' + requestId;
    el.textContent = text;
    el.classList.remove('hidden');
  }
})();
