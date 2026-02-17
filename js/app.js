/**
 * C5 Evidence Tool – Shared Form Logic
 * Handles validation, conditional required, submit, summary display
 */
(function () {
  'use strict';

  // ── Field label map for summary display ──
  const FIELD_LABELS = {
    asset_id: 'Asset-ID',
    device_type: 'Gerätetyp',
    manufacturer: 'Hersteller',
    model: 'Modell',
    serial_number: 'Seriennummer',
    location: 'Standort',
    commission_date: 'Datum Inbetriebnahme',
    asset_owner: 'Asset Owner',
    service: 'Service/Plattform',
    criticality: 'Kritikalität',
    change_ref: 'Change-/Ticket-Referenz',
    monitoring_active: 'Monitoring aktiv',
    patch_process: 'Patch/Firmware-Prozess definiert',
    access_controlled: 'Zugriff über berechtigte Admin-Gruppen',
    retire_date: 'Datum Stilllegung',
    reason: 'Grund',
    owner_approval: 'Freigabe durch Owner',
    followup: 'Folgeweg',
    data_handling: 'Data Handling',
    data_handling_ref: 'Nachweisreferenz',
    owner: 'Owner',
    confirm_date: 'Datum',
    purpose_bound: 'Zweckgebundener Betrieb',
    change_process: 'Changes nur via Change-Prozess',
    admin_access_controlled: 'Admin-Zugriff kontrolliert',
    lifecycle_managed: 'Lifecycle aktiv gemanagt',
    admin_user: 'Admin-User',
    security_owner: 'Security Owner',
    purpose: 'Zweck / Privileged Role',
    disk_encryption: 'Disk Encryption aktiv',
    mfa_active: 'MFA aktiv',
    edr_active: 'EDR/AV aktiv',
    no_private_use: 'Keine private Nutzung',
    commitment_date: 'Datum',
    admin_tasks_only: 'Nur Admin-Tätigkeiten',
    no_mail_office: 'Kein Mail/Office/Surfing',
    no_credential_sharing: 'Keine Weitergabe von Credentials',
    report_loss: 'Verlust sofort melden',
    return_on_change: 'Rückgabe bei Rollenwechsel/Austritt',
    return_date: 'Rückgabedatum',
    return_reason: 'Rückgabegrund',
    condition: 'Zustand',
    accessories_complete: 'Zubehör vollständig',
    cleanup_date: 'Datum',
    account_removed: 'Admin-Account entfernt/angepasst',
    keys_revoked: 'Keys/Zertifikate revoked',
    device_wiped: 'Gerät wiped oder reprovisioniert',
    ticket_ref: 'Ticket-Referenz',
  };

  // ── Backend API base URL ──
  const API_BASE = getApiBase();

  function getApiBase() {
    // If served via PHP dev server, use same origin
    // Otherwise use configurable base (default: backend/public)
    var meta = document.querySelector('meta[name="api-base"]');
    if (meta) return meta.content;
    return '/api';
  }

  // ── Conditional required logic ──
  function evaluateConditionalRequired(form) {
    var fields = form.querySelectorAll('[data-conditional-required]');
    fields.forEach(function (field) {
      var rule = field.getAttribute('data-conditional-required');
      var required = false;

      if (rule.indexOf(':unchecked') !== -1) {
        // field is required when a checkbox is NOT checked
        var checkName = rule.replace(':unchecked', '');
        var checkbox = form.querySelector('[name="' + checkName + '"]');
        required = checkbox && !checkbox.checked;
      } else if (rule.indexOf(':!') !== -1) {
        // field is required when radio/select != value
        var parts = rule.split(':!');
        var radioName = parts[0];
        var excludeVal = parts[1];
        var selected = form.querySelector('[name="' + radioName + '"]:checked')
          || form.querySelector('select[name="' + radioName + '"]');
        if (selected) {
          required = selected.value !== excludeVal;
        } else {
          // nothing selected yet — not required
          required = false;
        }
      }

      if (required) {
        field.setAttribute('required', '');
        field.closest('.field-group').classList.remove('hidden');
      } else {
        field.removeAttribute('required');
        // Don't hide — just remove required
      }
    });
  }

  // ── Validation ──
  function validateForm(form) {
    // First evaluate conditional required fields
    evaluateConditionalRequired(form);

    // Clear previous errors
    form.querySelectorAll('.field-error').forEach(function (el) {
      el.classList.remove('field-error');
      var msg = el.querySelector('.field-error-msg');
      if (msg) msg.remove();
    });

    var firstError = null;
    var valid = true;

    // Validate text/select/date inputs
    var inputs = form.querySelectorAll('input[required], select[required]');
    inputs.forEach(function (input) {
      if (input.type === 'checkbox') return; // handled below
      if (input.type === 'radio') return; // handled below
      if (!input.value || input.value.trim() === '') {
        markFieldError(input);
        valid = false;
        if (!firstError) firstError = input;
      }
    });

    // Validate required checkboxes
    var checkboxes = form.querySelectorAll('input[type="checkbox"][required]');
    checkboxes.forEach(function (cb) {
      if (!cb.checked) {
        var wrapper = cb.closest('.field-checkbox');
        if (wrapper) wrapper.classList.add('field-error');
        valid = false;
        if (!firstError) firstError = cb;
      }
    });

    // Validate required radio groups
    var radioGroups = {};
    form.querySelectorAll('input[type="radio"][required]').forEach(function (r) {
      radioGroups[r.name] = true;
    });
    Object.keys(radioGroups).forEach(function (name) {
      var checked = form.querySelector('input[name="' + name + '"]:checked');
      if (!checked) {
        var radios = form.querySelectorAll('input[name="' + name + '"]');
        radios.forEach(function (r) {
          var wrapper = r.closest('.field-checkbox');
          if (wrapper) wrapper.classList.add('field-error');
        });
        valid = false;
        if (!firstError) firstError = radios[0];
      }
    });

    if (firstError) {
      firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
      firstError.focus();
    }

    return valid;
  }

  function markFieldError(input) {
    var group = input.closest('.field-group');
    if (group) {
      group.classList.add('field-error');
      if (!group.querySelector('.field-error-msg')) {
        var msg = document.createElement('div');
        msg.className = 'field-error-msg';
        msg.textContent = 'Pflichtfeld';
        group.appendChild(msg);
      }
    }
  }

  // ── Collect form data ──
  function collectFormData(form) {
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
  }

  // ── Submit to backend ──
  function submitForm(form) {
    var eventType = form.getAttribute('data-event');
    var data = collectFormData(form);
    var statusEl = document.getElementById('form-status');
    var submitBtn = form.querySelector('.btn-submit');

    // Disable button during submit
    submitBtn.disabled = true;
    submitBtn.textContent = 'Sende …';
    statusEl.classList.add('hidden');

    fetch(API_BASE + '/submit/' + eventType, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data),
    })
      .then(function (res) {
        return res.json().then(function (body) {
          return { status: res.status, body: body };
        });
      })
      .then(function (result) {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Evidence senden';

        if (result.body.success) {
          showSuccess(statusEl, result.body);
          showSummary(data, result.body);
        } else {
          showError(statusEl, result.body.error || 'Unbekannter Fehler', result.body.request_id);
        }
      })
      .catch(function (err) {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Evidence senden';
        showError(statusEl, 'Verbindung zum Server fehlgeschlagen: ' + err.message);
      });
  }

  // ── Success / Error feedback ──
  function showSuccess(el, result) {
    el.className = 'submit-status success';
    var text = 'Evidence-Mail versendet.';
    if (result.jira_ticket) {
      text += ' · Jira-Ticket: ' + result.jira_ticket;
    }
    text += ' · Request-ID: ' + result.request_id;
    el.textContent = text;
    el.classList.remove('hidden');
  }

  function showError(el, message, requestId) {
    el.className = 'submit-status error';
    var text = message;
    if (requestId) text += ' · Request-ID: ' + requestId;
    el.textContent = text;
    el.classList.remove('hidden');
  }

  // ── Summary overlay (FR-07) ──
  function showSummary(data, result) {
    // Remove existing overlay
    var existing = document.querySelector('.summary-overlay');
    if (existing) existing.remove();

    var overlay = document.createElement('div');
    overlay.className = 'summary-overlay';

    var panel = document.createElement('div');
    panel.className = 'summary-panel';

    var h2 = document.createElement('h2');
    h2.textContent = 'Evidence-Zusammenfassung';
    panel.appendChild(h2);

    // Request-ID info
    var info = document.createElement('p');
    info.style.cssText = 'font-size:.8125rem;color:#5f6672;margin-bottom:1rem;';
    info.textContent = 'Request-ID: ' + result.request_id;
    if (result.jira_ticket) info.textContent += ' · Jira: ' + result.jira_ticket;
    panel.appendChild(info);

    // Table of all submitted fields
    var table = document.createElement('table');
    table.className = 'summary-table';
    Object.keys(data).forEach(function (key) {
      var val = data[key];
      if (val === '' || val === undefined) return;
      var tr = document.createElement('tr');
      var th = document.createElement('th');
      th.textContent = FIELD_LABELS[key] || key;
      var td = document.createElement('td');
      if (typeof val === 'boolean') {
        td.textContent = val ? 'Ja' : 'Nein';
      } else {
        td.textContent = val;
      }
      tr.appendChild(th);
      tr.appendChild(td);
      table.appendChild(tr);
    });
    panel.appendChild(table);

    // Close button
    var actions = document.createElement('div');
    actions.className = 'summary-actions';
    var closeBtn = document.createElement('button');
    closeBtn.className = 'btn-secondary';
    closeBtn.textContent = 'Schließen';
    closeBtn.type = 'button';
    closeBtn.addEventListener('click', function () {
      overlay.remove();
    });
    var newBtn = document.createElement('button');
    newBtn.className = 'btn-secondary';
    newBtn.textContent = 'Neues Formular';
    newBtn.type = 'button';
    newBtn.addEventListener('click', function () {
      overlay.remove();
      document.getElementById('evidence-form').reset();
      document.getElementById('form-status').classList.add('hidden');
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
    actions.appendChild(newBtn);
    actions.appendChild(closeBtn);
    panel.appendChild(actions);

    overlay.appendChild(panel);
    document.body.appendChild(overlay);

    // Close on overlay click
    overlay.addEventListener('click', function (e) {
      if (e.target === overlay) overlay.remove();
    });
  }

  // ── Init ──
  document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('evidence-form');
    if (!form) return;

    // Set today's date as default for all date inputs
    var today = new Date();
    var year = today.getFullYear();
    var month = String(today.getMonth() + 1).padStart(2, '0');
    var day = String(today.getDate()).padStart(2, '0');
    var todayStr = year + '-' + month + '-' + day;
    var dateInputs = form.querySelectorAll('input[type="date"]');
    dateInputs.forEach(function (input) {
      if (!input.value) {
        input.value = todayStr;
      }
    });

    // Update conditional required on change
    form.addEventListener('change', function () {
      evaluateConditionalRequired(form);
    });

    // Initial evaluation
    evaluateConditionalRequired(form);

    // Handle submit
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (validateForm(form)) {
        submitForm(form);
      }
    });

    // Clear field error on input
    form.addEventListener('input', function (e) {
      var group = e.target.closest('.field-group');
      if (group) {
        group.classList.remove('field-error');
        var msg = group.querySelector('.field-error-msg');
        if (msg) msg.remove();
      }
    });

    form.addEventListener('change', function (e) {
      if (e.target.type === 'checkbox') {
        var wrapper = e.target.closest('.field-checkbox');
        if (wrapper) wrapper.classList.remove('field-error');
      }
      if (e.target.type === 'radio') {
        var name = e.target.name;
        form.querySelectorAll('input[name="' + name + '"]').forEach(function (r) {
          var w = r.closest('.field-checkbox');
          if (w) w.classList.remove('field-error');
        });
      }
    });
  });
})();
