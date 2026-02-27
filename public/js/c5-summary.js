/**
 * C5 Evidence Tool – Summary Overlay (FR-07)
 * Displays evidence submission summary after successful submit.
 */
(function () {
  'use strict';

  window.C5 = window.C5 || {};

  /**
   * Show the summary overlay with all submitted data.
   * @param {Object} data - collected form data
   * @param {Object} result - backend response
   */
  C5.showSummary = function (data, result) {
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
    if (result.netbox_synced && result.netbox_status) {
      if (result.netbox_status === 'journal_created') {
        info.textContent += ' · NetBox: Journal Entry erstellt';
      } else {
        info.textContent += ' · NetBox: ' + result.netbox_status;
      }
    }
    panel.appendChild(info);

    // Table of all submitted fields
    var table = document.createElement('table');
    table.className = 'summary-table';
    Object.keys(data).forEach(function (key) {
      var val = data[key];
      if (val === '' || val === undefined) return;
      var tr = document.createElement('tr');
      var th = document.createElement('th');
      th.textContent = C5.getLabel(key);
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

    // NetBox error info (after table)
    if (result.netbox_error) {
      info.textContent += ' · NetBox-Sync fehlgeschlagen';
      var errDiv = document.createElement('div');
      errDiv.className = 'summary-netbox-error';
      errDiv.textContent = result.netbox_error;
      panel.appendChild(errDiv);
      if (result.netbox_error_trace) {
        var tracePre = document.createElement('pre');
        tracePre.className = 'summary-netbox-trace';
        tracePre.textContent = result.netbox_error_trace;
        panel.appendChild(tracePre);
      }
    }

    // Action buttons
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

    overlay.addEventListener('click', function (e) {
      if (e.target === overlay) overlay.remove();
    });
  };
})();
