/**
 * C5 Evidence Tool – Application Initializer
 * Loads after c5-labels.js, c5-validation.js, c5-asset-lookup.js,
 * c5-submit.js, c5-summary.js and wires everything together.
 */
(function () {
  'use strict';

  window.C5 = window.C5 || {};

  // ── Backend API base URL ──
  C5.apiBase = (function () {
    var meta = document.querySelector('meta[name="api-base"]');
    return meta ? meta.content : '/api';
  })();

  // ── Auth redirect helper ──
  C5.checkAuth = function (res) {
    if (res.status === 401 || res.status === 403) {
      window.location.href = '/login';
      throw new Error('Sitzung abgelaufen');
    }
    return res;
  };

  // ── HTML escape helper ──
  C5.escapeHtml = function (str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  };

  // ── Spinner helpers ──
  // Shows a spinner inside the .field-group of the given element.
  C5.showSpinner = function (el, label) {
    if (!el) return;
    var group = el.closest('.field-group');
    if (!group) return;
    C5.hideSpinner(el);
    var span = document.createElement('span');
    span.className = 'field-spinner';
    span.setAttribute('role', 'status');
    span.setAttribute('aria-label', label || 'Wird geladen');
    span.textContent = label || 'Wird geladen …';
    group.appendChild(span);
  };

  // Removes the spinner from the .field-group of the given element.
  C5.hideSpinner = function (el) {
    if (!el) return;
    var group = el.closest('.field-group');
    if (!group) return;
    var existing = group.querySelector('.field-spinner');
    if (existing) existing.remove();
  };

  // ── Submit-button load tracking ──
  // Disables the submit button while at least one async load is pending.
  C5.beginLoad = function (form) {
    form._pendingLoads = (form._pendingLoads || 0) + 1;
    var btn = form.querySelector('.btn-submit');
    if (btn) btn.disabled = true;
  };

  C5.endLoad = function (form) {
    form._pendingLoads = Math.max(0, (form._pendingLoads || 0) - 1);
    if (form._pendingLoads === 0) {
      var btn = form.querySelector('.btn-submit');
      if (btn) btn.disabled = false;
    }
  };

  // ── Init ──
  document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('evidence-form');
    if (!form) return;

    // Load labels from backend (async, silent fallback)
    if (typeof C5.loadLabelsFromApi === 'function') {
      C5.loadLabelsFromApi();
    }

    // Set today's date as default for all date inputs
    var today = new Date();
    var todayStr = today.getFullYear() + '-'
      + String(today.getMonth() + 1).padStart(2, '0') + '-'
      + String(today.getDate()).padStart(2, '0');
    form.querySelectorAll('input[type="date"]').forEach(function (input) {
      if (!input.value) input.value = todayStr;
    });

    // Update conditional required on change
    form.addEventListener('change', function () {
      C5.evaluateConditionalRequired(form);
    });
    C5.evaluateConditionalRequired(form);

    // Load dropdowns
    C5.loadTenants(form);
    C5.loadContacts(form);
    C5.loadLocations(form);
    C5.loadDeviceTypes(form);

    // Cascade: Region → Standortgruppe → Standort
    var regionSel = form.querySelector('#region_id');
    if (regionSel) {
      regionSel.addEventListener('change', function () {
        C5.filterSiteGroups(form);
        C5.filterSites(form);
      });
    }
    var siteGroupSel = form.querySelector('#site_group_id');
    if (siteGroupSel) {
      siteGroupSel.addEventListener('change', function () {
        C5.filterSites(form);
      });
    }

    // Sync tenant_name when dropdown changes
    var tenantSel = form.querySelector('#tenant_id');
    if (tenantSel) {
      tenantSel.addEventListener('change', function () {
        C5.syncTenantName(form);
      });
    }

    // Sync contact_id when contact dropdown changes
    var contactSel = form.querySelector('#asset_owner, #owner_approval, #owner');
    if (contactSel) {
      contactSel.addEventListener('change', function () {
        C5.syncContactId(form);
      });
    }

    // NetBox asset lookup on asset_id blur
    var assetIdField = form.querySelector('[name="asset_id"]');
    if (assetIdField) {
      assetIdField.addEventListener('blur', function () {
        C5.performAssetLookup(assetIdField.value, form);
      });
    }

    // Handle submit
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (C5.validateForm(form)) {
        C5.submitForm(form);
      }
    });

    // Clear field errors on input
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
