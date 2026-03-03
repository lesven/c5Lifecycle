import { Selector } from 'testcafe';
import { authenticatedUserRole } from './support/auth.js';
import { loadFixture } from './support/fixture-loader.js';
import { getBaseUrl } from './support/env.js';
import { selectFirstNonEmptyOption } from './support/form-helpers.js';

const baseUrl = getBaseUrl();
const fixtureData = loadFixture('rz_retire');

fixture('E2E rz_retire').page(`${baseUrl}/forms/rz-retire`);

test('submits rz_retire happy path when data handling is not relevant', async (t) => {
  await t.useRole(authenticatedUserRole);
  await t.navigateTo(`${baseUrl}/forms/rz-retire`);

  await t
    .typeText('#asset_id', fixtureData.happyPath.asset_id, { replace: true })
    .typeText('#retire_date', fixtureData.happyPath.retire_date, { replace: true });

  await t.click('#reason').click(Selector('#reason option').withText(fixtureData.happyPath.reason));
  await selectFirstNonEmptyOption(t, '#owner_approval');
  await t.click('#followup').click(Selector('#followup option').withText(fixtureData.happyPath.followup));
  await t.click('input[name="data_handling"][value="Nicht relevant"]');

  await t
    .click('[data-testid="submit-evidence"]')
    .expect(Selector('[data-testid="form-status"]').innerText)
    .contains('Evidence-Mail versendet.', { timeout: 30000 });
});

test('blockiert Submit wenn Pflichtfelder leer sind', async (t) => {
  await t.useRole(authenticatedUserRole);
  await t.navigateTo(`${baseUrl}/forms/rz-retire`);

  // Kein Feld ausf\u00fcllen, direkt Submit klicken
  await t.click('[data-testid="submit-evidence"]');

  await t
    .expect(Selector('.field-error-msg').exists).ok('Pflichtfeld-Fehlermeldung soll angezeigt werden')
    .expect(Selector('[data-testid="form-status"]').hasClass('hidden')).ok('form-status soll hidden bleiben');
});

test('blockiert Submit wenn data_handling_ref fehlt bei data_handling \u2260 Nicht relevant', async (t) => {
  await t.useRole(authenticatedUserRole);
  await t.navigateTo(`${baseUrl}/forms/rz-retire`);

  const fix = fixtureData.negativeConditionalRef;

  await t
    .typeText('#asset_id', fix.asset_id, { replace: true })
    .typeText('#retire_date', fix.retire_date, { replace: true })
    .click('#reason')
    .click(Selector('#reason option').withText(fix.reason));

  await selectFirstNonEmptyOption(t, '#owner_approval');

  await t
    .click('#followup')
    .click(Selector('#followup option').withText(fix.followup))
    .click(`input[name="data_handling"][value="${fix.data_handling}"]`)
    // data_handling_ref absichtlich leer lassen
    .click('[data-testid="submit-evidence"]');

  await t
    .expect(Selector('#data_handling_ref').hasAttribute('required')).ok('data_handling_ref soll required sein')
    .expect(Selector('.field-error-msg').exists).ok('Fehlermeldung soll erscheinen')
    .expect(Selector('[data-testid="form-status"]').hasClass('hidden')).ok('form-status soll hidden bleiben');
});
