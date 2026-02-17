#!/usr/bin/env bash
# =============================================================================
# NetBox Setup Script
# Legt Custom Fields, Choice Sets und Device Roles in NetBox an.
# Voraussetzung: NetBox v3.5+, API-Token mit Admin-Rechten
# =============================================================================

set -euo pipefail

# Konfiguration
NETBOX_URL="${NETBOX_URL:-https://netbox.company.de}"
NETBOX_TOKEN="${NETBOX_TOKEN:-}"

if [ -z "$NETBOX_TOKEN" ]; then
  echo "Fehler: NETBOX_TOKEN nicht gesetzt."
  echo "Verwendung: NETBOX_URL=https://netbox.example.com NETBOX_TOKEN=abc123 $0"
  exit 1
fi

API="${NETBOX_URL}/api"
AUTH="Authorization: Token ${NETBOX_TOKEN}"
CT="Content-Type: application/json"

# Helper: POST mit Fehlerbehandlung
nb_post() {
  local endpoint="$1"
  local data="$2"
  local label="${3:-}"
  local response
  response=$(curl -s -w "\n%{http_code}" -X POST "${API}${endpoint}" \
    -H "$AUTH" -H "$CT" -d "$data")
  local http_code
  http_code=$(echo "$response" | tail -1)
  local body
  body=$(echo "$response" | sed '$d')
  if [ "$http_code" -ge 200 ] && [ "$http_code" -lt 300 ]; then
    echo "  OK: ${label:-$endpoint}"
  elif [ "$http_code" -eq 400 ] && echo "$body" | grep -q "already exists"; then
    echo "  SKIP: ${label:-$endpoint} (existiert bereits)"
  else
    echo "  FEHLER (HTTP $http_code): ${label:-$endpoint}"
    echo "  $body"
  fi
}

echo "=== NetBox Setup fuer C5 Evidence Tool ==="
echo "NetBox: $NETBOX_URL"
echo ""

# ----- 1. Choice Sets -----
echo "--- Choice Sets anlegen ---"

nb_post "/extras/custom-field-choice-sets/" '{
  "name": "Criticality",
  "extra_choices": [["hoch","hoch"],["mittel","mittel"],["niedrig","niedrig"]]
}' "Choice Set: Criticality"

nb_post "/extras/custom-field-choice-sets/" '{
  "name": "Retire Reason",
  "extra_choices": [["EOL","EOL"],["Defekt","Defekt"],["Migration","Migration"],["Sonstiges","Sonstiges"]]
}' "Choice Set: Retire Reason"

nb_post "/extras/custom-field-choice-sets/" '{
  "name": "Data Handling",
  "extra_choices": [["Secure Wipe","Secure Wipe"],["Physische Zerstoerung","Physische Zerstoerung"],["Loeschzertifikat Dienstleister","Loeschzertifikat Dienstleister"],["Nicht relevant","Nicht relevant"]]
}' "Choice Set: Data Handling"

nb_post "/extras/custom-field-choice-sets/" '{
  "name": "Followup",
  "extra_choices": [["Entsorgung","Entsorgung"],["Leasing-Rueckgabe","Leasing-Rueckgabe"],["Ersatzteilspender","Ersatzteilspender"]]
}' "Choice Set: Followup"

echo ""

# ----- 2. Custom Fields -----
echo "--- Custom Fields anlegen ---"

# Text-Felder
for field in cf_asset_owner cf_service cf_change_ref cf_data_handling_ref cf_admin_user cf_security_owner cf_purpose; do
  nb_post "/extras/custom-fields/" "{
    \"name\": \"${field}\",
    \"type\": \"text\",
    \"object_types\": [\"dcim.device\"],
    \"required\": false
  }" "Custom Field: ${field}"
done

# Boolean-Felder
for field in cf_monitoring_active cf_patch_process cf_access_controlled cf_disk_encryption cf_mfa_active cf_edr_active cf_no_private_use; do
  nb_post "/extras/custom-fields/" "{
    \"name\": \"${field}\",
    \"type\": \"boolean\",
    \"object_types\": [\"dcim.device\"],
    \"required\": false
  }" "Custom Field: ${field}"
done

# Date-Felder
for field in cf_commission_date cf_retire_date; do
  nb_post "/extras/custom-fields/" "{
    \"name\": \"${field}\",
    \"type\": \"date\",
    \"object_types\": [\"dcim.device\"],
    \"required\": false
  }" "Custom Field: ${field}"
done

# Select-Felder (benoetigen vorher angelegte Choice Sets)
echo ""
echo "HINWEIS: Select-Felder (cf_criticality, cf_retire_reason, cf_data_handling, cf_followup)"
echo "muessen manuell in der NetBox-UI mit den entsprechenden Choice Sets verknuepft werden,"
echo "da die API die Choice-Set-Verknuepfung per ID erfordert."
echo ""

# ----- 3. Device Roles -----
echo "--- Device Roles anlegen ---"

nb_post "/dcim/device-roles/" '{
  "name": "Server",
  "slug": "server",
  "vm_role": false
}' "Device Role: Server"

nb_post "/dcim/device-roles/" '{
  "name": "Storage",
  "slug": "storage",
  "vm_role": false
}' "Device Role: Storage"

nb_post "/dcim/device-roles/" '{
  "name": "Switch",
  "slug": "switch",
  "vm_role": false
}' "Device Role: Switch"

nb_post "/dcim/device-roles/" '{
  "name": "Firewall",
  "slug": "firewall",
  "vm_role": false
}' "Device Role: Firewall"

nb_post "/dcim/device-roles/" '{
  "name": "Admin Laptop",
  "slug": "admin-laptop",
  "vm_role": false
}' "Device Role: Admin Laptop"

nb_post "/dcim/device-roles/" '{
  "name": "Jump Host",
  "slug": "jump-host",
  "vm_role": false
}' "Device Role: Jump Host"

nb_post "/dcim/device-roles/" '{
  "name": "Break-Glass",
  "slug": "break-glass",
  "vm_role": false
}' "Device Role: Break-Glass"

echo ""
echo "=== Setup abgeschlossen ==="
echo "Bitte pruefen Sie die Ausgabe auf Fehler."
