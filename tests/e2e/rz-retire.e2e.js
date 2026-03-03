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
    .click('.btn-submit')
    .expect(Selector('#form-status').innerText)
    .contains('Evidence-Mail versendet.', { timeout: 30000 });
});

test('requires data_handling_ref when data_handling is not "Nicht relevant"', async (t) => {
  await t.useRole(authenticatedUserRole);
  await t.navigateTo(`${baseUrl}/forms/rz-retire`);

  await t
    .typeText('#asset_id', fixtureData.negativeMissingDataHandlingRef.asset_id, { replace: true })
    .typeText('#retire_date', fixtureData.negativeMissingDataHandlingRef.retire_date, { replace: true })
    .click('#reason')
    .click(Selector('#reason option').withText(fixtureData.negativeMissingDataHandlingRef.reason));

  await selectFirstNonEmptyOption(t, '#owner_approval');
  await t
    .click('#followup')
    .click(Selector('#followup option').withText(fixtureData.negativeMissingDataHandlingRef.followup))
    .click('input[name="data_handling"][value="Secure Wipe"]')
    .click('.btn-submit')
    .expect(Selector('#data_handling_ref').hasAttribute('required'))
    .ok()
    .expect(Selector('#data_handling_ref').value)
    .eql('')
    .expect(Selector('.field-error-msg').exists)
    .ok();
});
