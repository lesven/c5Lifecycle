import { apiMocks } from '../helpers/api-mocks';
import formPage from '../pages/form.page';

import rzProvision from '../fixtures/rz-provision.json';
import rzRetire from '../fixtures/rz-retire.json';
import rzOwnerConfirm from '../fixtures/rz-owner-confirm.json';
import adminProvision from '../fixtures/admin-provision.json';
import adminUserCommitment from '../fixtures/admin-user-commitment.json';
import adminReturn from '../fixtures/admin-return.json';
import adminAccessCleanup from '../fixtures/admin-access-cleanup.json';

const BASE_URL = process.env.BASE_URL || 'http://localhost:8080';

const ALL_FIXTURES = [
    rzProvision,
    rzRetire,
    rzOwnerConfirm,
    adminProvision,
    adminUserCommitment,
    adminReturn,
    adminAccessCleanup,
];

for (const fixtureData of ALL_FIXTURES) {
    fixture`Formular-Submit: ${fixtureData.title}`
        .page`${BASE_URL}/forms/${fixtureData.slug}`
        .requestHooks(apiMocks);

    test(`${fixtureData.title} – Formular wird korrekt geladen`, async (t) => {
        await t
            .expect(formPage.form.exists).ok()
            .expect(formPage.heading.textContent).contains(fixtureData.title)
            .expect(formPage.submitBtn.visible).ok();
    });

    test(`${fixtureData.title} – Happy Path: ausfuellen und absenden`, async (t) => {
        // Kurz warten damit API-Mocks Dropdowns befuellen koennen
        await t.wait(1000);

        // Alle Felder aus Fixture ausfuellen
        await formPage.fillForm(fixtureData.fields);

        // Formular absenden
        await formPage.submit();

        // Erfolg pruefen
        await formPage.expectSuccess();

        // Summary-Overlay pruefen
        await formPage.expectSummaryOverlay();
        await formPage.expectRequestId();

        // Overlay schliessen
        await formPage.closeSummary();
    });
}
