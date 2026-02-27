/**
 * C5 Evidence Tool – Field Labels
 * Single source of truth for field label display names (German).
 * Can optionally load labels from /api/field-labels.
 */
(function () {
  'use strict';

  window.C5 = window.C5 || {};

  // Default labels – kept in sync with backend FieldLabelRegistry
  var FIELD_LABELS = {
    asset_id: 'Asset-ID',
    device_type: 'Gerätetyp',
    manufacturer: 'Hersteller',
    model: 'Modell',
    serial_number: 'Seriennummer',
    location: 'Standort',
    region_id: 'Region',
    region_name: 'Region',
    site_group_id: 'Standortgruppe',
    site_group_name: 'Standortgruppe',
    site_id: 'Standort',
    site_name: 'Standort',
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
    tenant_name: 'Mandant',
  };

  /**
   * Return human-readable label for a field key.
   * @param {string} key
   * @returns {string}
   */
  C5.getLabel = function (key) {
    return FIELD_LABELS[key] || key;
  };

  /**
   * Return all labels (for summary table etc.).
   * @returns {Object}
   */
  C5.getAllLabels = function () {
    return FIELD_LABELS;
  };

  /**
   * Load labels from backend API and merge into local map.
   * Failures are silently ignored (falls back to defaults).
   */
  C5.loadLabelsFromApi = function () {
    fetch(C5.apiBase + '/field-labels')
      .then(function (r) { return r.json(); })
      .then(function (labels) {
        if (labels && typeof labels === 'object') {
          Object.keys(labels).forEach(function (k) {
            FIELD_LABELS[k] = labels[k];
          });
        }
      })
      .catch(function () { /* fallback to defaults */ });
  };
})();
