import indexPage from '../pages/index.page';

const BASE_URL = process.env.BASE_URL || 'http://localhost:8080';

fixture`Startseite`
    .page`${BASE_URL}/`;

test('Ueberschrift und Seitentitel werden angezeigt', async (t) => {
    await t
        .expect(indexPage.heading.visible).ok()
        .expect(indexPage.heading.textContent).contains('Evidence erfassen');
});

test('Track A zeigt 3 Formular-Karten', async (t) => {
    await t
        .expect(indexPage.trackA.visible).ok()
        .expect(indexPage.getTrackACards().count).eql(3);
});

test('Track B zeigt 4 Formular-Karten', async (t) => {
    await t
        .expect(indexPage.trackB.visible).ok()
        .expect(indexPage.getTrackBCards().count).eql(4);
});

test('Insgesamt 7 Formular-Karten vorhanden', async (t) => {
    await t.expect(indexPage.formCards.count).eql(7);
});

const expectedForms = [
    { slug: 'rz-provision', title: 'Inbetriebnahme RZ-Asset' },
    { slug: 'rz-retire', title: 'Außerbetriebnahme RZ-Asset' },
    { slug: 'rz-owner-confirm', title: 'Owner-Betriebsbestätigung' },
    { slug: 'admin-provision', title: 'Inbetriebnahme Admin-Endgerät' },
    { slug: 'admin-user-commitment', title: 'Verpflichtung Admin-User' },
    { slug: 'admin-return', title: 'Rückgabe Admin-Endgerät' },
    { slug: 'admin-access-cleanup', title: 'Privileged Access Cleanup' },
];

for (const form of expectedForms) {
    test(`Karte "${form.title}" ist vorhanden und verlinkt korrekt`, async (t) => {
        const card = indexPage.getCardBySlug(form.slug);
        await t
            .expect(card.exists).ok(`Karte fuer ${form.slug} nicht gefunden`)
            .expect(card.visible).ok();
    });
}

for (const form of expectedForms) {
    test(`Klick auf "${form.title}" oeffnet das Formular`, async (t) => {
        const card = indexPage.getCardBySlug(form.slug);
        await t
            .click(card)
            .expect(indexPage.heading.textContent).contains(form.title.split(' ')[0]);
    });
}
