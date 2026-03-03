import { Selector } from 'testcafe';
import { apiMocks } from '../helpers/api-mocks';
import formPage from '../pages/form.page';

const BASE_URL = process.env.BASE_URL || 'http://localhost:8080';

// ── Leeres Formular: Pflichtfeld-Validierung ──

fixture`Validierung: rz-provision leer absenden`
    .page`${BASE_URL}/forms/rz-provision`
    .requestHooks(apiMocks);

test('Leeres RZ-Provision-Formular zeigt Pflichtfeld-Fehler', async (t) => {
    // Date-Felder werden automatisch mit heute befuellt, daher leeren
    const dateInput = Selector('input[name="commission_date"]');
    await t.selectText(dateInput).pressKey('delete');

    // Direkt absenden ohne Felder auszufuellen
    await formPage.submit();

    // Validierungsfehler muessen sichtbar sein
    await formPage.expectValidationErrors(1);
});

fixture`Validierung: admin-provision leer absenden`
    .page`${BASE_URL}/forms/admin-provision`
    .requestHooks(apiMocks);

test('Leeres Admin-Provision-Formular zeigt Pflichtfeld-Fehler', async (t) => {
    const dateInput = Selector('input[name="commission_date"]');
    await t.selectText(dateInput).pressKey('delete');

    await formPage.submit();

    await formPage.expectValidationErrors(1);
});

// ── Konditionale Validierung: rz_retire / data_handling_ref ──

fixture`Validierung: rz-retire konditionale Felder`
    .page`${BASE_URL}/forms/rz-retire`
    .requestHooks(apiMocks);

test('data_handling = "Secure Wipe" → data_handling_ref wird Pflichtfeld', async (t) => {
    await t.wait(1000);

    // Pflichtfelder ausfuellen
    await formPage.fillField('asset_id', { type: 'text', value: 'SRV-VAL-001' });
    await formPage.fillField('reason', { type: 'select', value: 'EOL' });
    await formPage.fillField('owner_approval', { type: 'contact_select', value: 'Max Mustermann' });
    await formPage.fillField('followup', { type: 'select', value: 'Entsorgung' });

    // Radio "Secure Wipe" waehlen → data_handling_ref wird required
    await formPage.fillField('data_handling', { type: 'radio', value: 'Secure Wipe' });

    // Absenden OHNE data_handling_ref
    await formPage.submit();

    // Validierungsfehler fuer data_handling_ref erwartet
    const refGroup = Selector('#data_handling_ref_group');
    await t.expect(refGroup.hasClass('field-error')).ok(
        'data_handling_ref sollte einen Fehler haben wenn data_handling != "Nicht relevant"'
    );
});

test('data_handling = "Nicht relevant" → data_handling_ref ist nicht Pflicht', async (t) => {
    await t.wait(1000);

    // Pflichtfelder ausfuellen
    await formPage.fillField('asset_id', { type: 'text', value: 'SRV-VAL-002' });
    await formPage.fillField('reason', { type: 'select', value: 'EOL' });
    await formPage.fillField('owner_approval', { type: 'contact_select', value: 'Max Mustermann' });
    await formPage.fillField('followup', { type: 'select', value: 'Entsorgung' });

    // Radio "Nicht relevant" waehlen → data_handling_ref ist optional
    await formPage.fillField('data_handling', { type: 'radio', value: 'Nicht relevant' });

    // Absenden OHNE data_handling_ref – sollte erfolgreich sein
    await formPage.submit();

    // Kein Fehler bei data_handling_ref erwartet
    const refGroup = Selector('#data_handling_ref_group');
    await t.expect(refGroup.hasClass('field-error')).notOk(
        'data_handling_ref sollte keinen Fehler haben wenn data_handling = "Nicht relevant"'
    );
});

// ── Konditionale Validierung: admin_access_cleanup / ticket_ref ──

fixture`Validierung: admin-access-cleanup konditionale Felder`
    .page`${BASE_URL}/forms/admin-access-cleanup`
    .requestHooks(apiMocks);

test('device_wiped unchecked → ticket_ref wird Pflichtfeld', async (t) => {
    // Pflichtfelder ausfuellen
    await formPage.fillField('asset_id', { type: 'text', value: 'ADM-VAL-001' });
    await formPage.fillField('admin_user', { type: 'text', value: 'val@company.de' });
    await formPage.fillField('account_removed', { type: 'checkbox' });
    await formPage.fillField('keys_revoked', { type: 'checkbox' });

    // device_wiped NICHT anklicken → ticket_ref wird Pflicht

    // Absenden OHNE ticket_ref
    await formPage.submit();

    // Validierungsfehler fuer ticket_ref erwartet
    const refGroup = Selector('#ticket_ref').parent('.field-group');
    await t.expect(refGroup.hasClass('field-error')).ok(
        'ticket_ref sollte Pflicht sein wenn device_wiped nicht gecheckt'
    );
});

test('device_wiped checked → ticket_ref ist nicht Pflicht', async (t) => {
    // Pflichtfelder ausfuellen
    await formPage.fillField('asset_id', { type: 'text', value: 'ADM-VAL-002' });
    await formPage.fillField('admin_user', { type: 'text', value: 'val@company.de' });
    await formPage.fillField('account_removed', { type: 'checkbox' });
    await formPage.fillField('keys_revoked', { type: 'checkbox' });

    // device_wiped anklicken → ticket_ref ist optional
    await formPage.fillField('device_wiped', { type: 'checkbox' });

    // Absenden OHNE ticket_ref – sollte erfolgreich sein
    await formPage.submit();

    // Kein Fehler bei ticket_ref erwartet
    const refGroup = Selector('#ticket_ref').parent('.field-group');
    await t.expect(refGroup.hasClass('field-error')).notOk(
        'ticket_ref sollte optional sein wenn device_wiped gecheckt'
    );
});

// ── Checkbox-Validierung ──

fixture`Validierung: Pflicht-Checkboxen`
    .page`${BASE_URL}/forms/admin-user-commitment`
    .requestHooks(apiMocks);

test('Nicht angehakte Pflicht-Checkboxen erzeugen Fehler', async (t) => {
    // Nur Text-/Datumsfelder ausfuellen, Checkboxen bewusst nicht anklicken
    await formPage.fillField('asset_id', { type: 'text', value: 'ADM-VAL-003' });
    await formPage.fillField('admin_user', { type: 'text', value: 'val@company.de' });

    await formPage.submit();

    // Mindestens die 5 Pflicht-Checkboxen muessen Fehler haben
    const errorCheckboxes = Selector('.field-checkbox.field-error');
    await t.expect(errorCheckboxes.count).gte(5, 'Alle 5 Pflicht-Checkboxen sollten Fehler haben');
});
