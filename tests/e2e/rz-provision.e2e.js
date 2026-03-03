import { Selector } from 'testcafe';
import { authenticatedUserRole } from './support/auth.js';
import { loadFixture } from './support/fixture-loader.js';
import { getBaseUrl } from './support/env.js';
import { checkCheckbox, selectFirstNonEmptyOption } from './support/form-helpers.js';

const baseUrl = getBaseUrl();
const fixtureData = loadFixture('rz_provision').happyPath;

fixture('E2E rz_provision').page(`${baseUrl}/forms/rz-provision`);

test('submits rz_provision successfully with integration-backed fields', async (t) => {
  await t.useRole(authenticatedUserRole);
  await t.navigateTo(`${baseUrl}/forms/rz-provision`);

  await t
    .typeText('#asset_id', fixtureData.asset_id, { replace: true })
    .typeText('#manufacturer', fixtureData.manufacturer, { replace: true })
    .typeText('#model', fixtureData.model, { replace: true })
    .typeText('#serial_number', fixtureData.serial_number, { replace: true })
    .typeText('#commission_date', fixtureData.commission_date, { replace: true })
    .typeText('#service', fixtureData.service, { replace: true })
    .typeText('#change_ref', fixtureData.change_ref, { replace: true });

  await selectFirstNonEmptyOption(t, '#device_type');
  await selectFirstNonEmptyOption(t, '#region_id');
  await selectFirstNonEmptyOption(t, '#site_group_id');
  await selectFirstNonEmptyOption(t, '#site_id');
  await selectFirstNonEmptyOption(t, '#asset_owner');
  await t.click('#criticality').click(Selector('#criticality option').withText(fixtureData.criticality));

  await checkCheckbox(t, 'input[name="monitoring_active"]');
  await checkCheckbox(t, 'input[name="patch_process"]');
  await checkCheckbox(t, 'input[name="access_controlled"]');

  await t
    .click('[data-testid="submit-evidence"]')
    .expect(Selector('[data-testid="form-status"]').innerText)
    .contains('Evidence-Mail versendet.', { timeout: 30000 });
});

test('blockiert Submit wenn Pflichtfelder leer sind', async (t) => {
  await t.useRole(authenticatedUserRole);
  await t.navigateTo(`${baseUrl}/forms/rz-provision`);

  // Kein Feld ausf\u00fcllen, direkt Submit klicken
  await t.click('[data-testid="submit-evidence"]');

  await t
    .expect(Selector('.field-error-msg').exists).ok('Pflichtfeld-Fehlermeldung soll angezeigt werden')
    .expect(Selector('[data-testid="form-status"]').hasClass('hidden')).ok('form-status soll hidden bleiben');
});
