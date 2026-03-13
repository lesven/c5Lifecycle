## Plan: Nutzungstyp Feld im RZ-Provision Formular

**Ziel**: Im RZ-Provision Formular ein Dropdown-Feld "Nutzungstyp" hinzufügen, das seine Optionen aus dem Custom Field Choice Set `cf_nutzungstyp` in NetBox lädt. Das Feld soll ein Pflichtfeld sein.

### Schritte

1. **API-Endpoint erstellen**
   - Neue Controller-Datei `src/Controller/Api/CustomFieldsController.php`.
   - GET `/api/custom-fields/{fieldName}`
   - Nutzt `Infrastructure\NetBox\NetBoxClient` um das Custom Field aus NetBox zu laden; gibt JSON mit `choices: [{id,label},...]` zurück.
   - Fehlerbehandlung für fehlendes Feld.

2. **Feldlabel registrieren**
   - In `FieldLabelRegistry.php` neuen Eintrag hinzufügen:
     ```php
     'nutzungstyp' => 'Nutzungstyp',
     ```

3. **Formular anpassen**
   - Datei `templates/forms/rz_provision.html.twig` erweitern:
     Feld in Asset-Stammdaten (nach device_type):
     ```twig
     {{ f.dynamic_select('nutzungstyp') }}
     ```
   - Ensure benötigte IDs/namen passen.

4. **Validation konfigurieren**
   - `config/event_definitions.yaml`: Zum `rz_provision` event `required_fields` um `nutzungstyp` ergänzen.

5. **Frontend JavaScript**
   - In `public/js/c5-asset-lookup.js`: Funktion `C5.loadCustomField(fieldName, form)`
     * Ajax GET `/api/custom-fields/${fieldName}`
     * Füllt `select[name=${fieldName}]` mit Optionen
     * Fügt Spinner beim Laden hinzu (wie andere loader).
   - In `public/js/app.js`: nach `C5.loadDeviceTypes(form)` hinzufügen:
     ```js
     C5.loadCustomField('nutzungstyp', form);
     ```

6. **Optional: NetBox Setup aktualisieren**
   - `scripts/netbox-setup.sh`: Choice Set "Nutzungstyp" mit gewünschten Optionen hinzufügen.

7. **Tests**
   - Unit tests für neuen Controller (Mock NetBoxClient), für EventDataValidator (prüft `required_fields`).
   - Integrationstest zur API.
   - Frontend tests? evtl. nur visuell.

8. **Verifizierung**
   - Browser: Formular zeigt Pflichtfeld und lädt Optionen.
   - Backend-Logs: keine Fehler bei API.
   - `make test` grünes Ergebnis.

### Hinweise

- Der Endpoint ist speziell für `cf_nutzungstyp`, keine generische Lösung.
- Custom Field Choice Set muss in NetBox existieren (Setup-Skript optional erweitern).
- Feld gilt immer als Pflicht; später könnte es bedingt werden.


