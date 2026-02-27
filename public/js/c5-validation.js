/**
 * C5 Evidence Tool – Form Validation
 * Conditional required evaluation, field validation, error display.
 */
(function () {
  'use strict';

  window.C5 = window.C5 || {};

  /**
   * Evaluate data-conditional-required attributes and toggle required state.
   * @param {HTMLFormElement} form
   */
  C5.evaluateConditionalRequired = function (form) {
    var fields = form.querySelectorAll('[data-conditional-required]');
    fields.forEach(function (field) {
      var rule = field.getAttribute('data-conditional-required');
      var required = false;

      if (rule.indexOf(':unchecked') !== -1) {
        var checkName = rule.replace(':unchecked', '');
        var checkbox = form.querySelector('[name="' + checkName + '"]');
        required = checkbox && !checkbox.checked;
      } else if (rule.indexOf(':!') !== -1) {
        var parts = rule.split(':!');
        var radioName = parts[0];
        var excludeVal = parts[1];
        var selected = form.querySelector('[name="' + radioName + '"]:checked')
          || form.querySelector('select[name="' + radioName + '"]');
        if (selected) {
          required = selected.value !== excludeVal;
        } else {
          required = false;
        }
      }

      if (required) {
        field.setAttribute('required', '');
        field.closest('.field-group').classList.remove('hidden');
      } else {
        field.removeAttribute('required');
      }
    });
  };

  /**
   * Validate the form and display errors on invalid fields.
   * @param {HTMLFormElement} form
   * @returns {boolean}
   */
  C5.validateForm = function (form) {
    C5.evaluateConditionalRequired(form);

    // Clear previous errors
    form.querySelectorAll('.field-error').forEach(function (el) {
      el.classList.remove('field-error');
      var msg = el.querySelector('.field-error-msg');
      if (msg) msg.remove();
    });

    var firstError = null;
    var valid = true;

    // Text/select/date inputs
    form.querySelectorAll('input[required], select[required]').forEach(function (input) {
      if (input.type === 'checkbox' || input.type === 'radio') return;
      if (!input.value || input.value.trim() === '') {
        markFieldError(input);
        valid = false;
        if (!firstError) firstError = input;
      }
    });

    // Required checkboxes
    form.querySelectorAll('input[type="checkbox"][required]').forEach(function (cb) {
      if (!cb.checked) {
        var wrapper = cb.closest('.field-checkbox');
        if (wrapper) wrapper.classList.add('field-error');
        valid = false;
        if (!firstError) firstError = cb;
      }
    });

    // Required radio groups
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
  };

  // ── Private helpers ──

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
})();
