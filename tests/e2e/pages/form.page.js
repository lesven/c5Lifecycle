import { Selector, t } from 'testcafe';

/**
 * Generischer Page Object fuer alle 7 Evidence-Formulare.
 * Erkennt Feldtypen und befuellt sie automatisch aus Fixture-Daten.
 */
class FormPage {
    constructor() {
        this.form = Selector('#evidence-form');
        this.submitBtn = Selector('.btn-submit');
        this.formStatus = Selector('#form-status');
        this.summaryOverlay = Selector('.summary-overlay');
        this.summaryPanel = Selector('.summary-panel');
        this.summaryTable = Selector('.summary-table');
        this.summaryClose = Selector('.summary-panel .btn-secondary').withText('Schließen');
        this.fieldErrors = Selector('.field-error');
        this.fieldErrorMessages = Selector('.field-error-msg');
        this.heading = Selector('h1');
    }

    /**
     * Befuellt ein einzelnes Formularfeld anhand seines Typs.
     *
     * @param {string} name  - Feldname (HTML name-Attribut)
     * @param {object} field - { type, value }
     */
    async fillField(name, field) {
        switch (field.type) {
            case 'text': {
                const input = Selector(`input[name="${name}"]`);
                await t.selectText(input).pressKey('delete').typeText(input, field.value);
                break;
            }
            case 'date': {
                const input = Selector(`input[name="${name}"]`);
                // Date inputs are pre-filled with today; overwrite with fixture value
                await t.selectText(input).pressKey('delete').typeText(input, field.value);
                break;
            }
            case 'select': {
                const select = Selector(`select[name="${name}"]`);
                const option = select.find('option').withText(field.value);
                await t.click(select).click(option);
                break;
            }
            case 'checkbox': {
                const checkbox = Selector(`input[name="${name}"][type="checkbox"]`);
                const isChecked = await checkbox.checked;
                if (!isChecked) {
                    await t.click(checkbox);
                }
                break;
            }
            case 'radio': {
                const radio = Selector(`input[name="${name}"][type="radio"][value="${field.value}"]`);
                await t.click(radio);
                break;
            }
            case 'contact_select': {
                // Contact selects werden per API-Mock befuellt.
                // Warten bis Optionen geladen sind, dann auswaehlen.
                const select = Selector(`select[name="${name}"]`);
                const option = select.find('option').withText(field.value);
                await t.expect(option.exists).ok(`Kontakt-Option "${field.value}" nicht gefunden in ${name}`, { timeout: 5000 });
                await t.click(select).click(option);
                break;
            }
            default:
                throw new Error(`Unbekannter Feldtyp: ${field.type} fuer Feld ${name}`);
        }
    }

    /**
     * Befuellt alle Felder aus einer Fixture-Definition.
     *
     * @param {object} fields - { feldname: { type, value }, ... }
     */
    async fillForm(fields) {
        for (const [name, field] of Object.entries(fields)) {
            await this.fillField(name, field);
        }
    }

    /** Klickt den "Evidence senden"-Button */
    async submit() {
        await t.click(this.submitBtn);
    }

    /** Prueft ob die Erfolgsmeldung sichtbar ist */
    async expectSuccess() {
        await t
            .expect(this.formStatus.visible).ok('Statusmeldung nicht sichtbar', { timeout: 15000 })
            .expect(this.formStatus.hasClass('success')).ok('Statusmeldung ist kein Erfolg')
            .expect(this.formStatus.textContent).contains('Evidence-Mail versendet');
    }

    /** Prueft ob das Summary-Overlay angezeigt wird */
    async expectSummaryOverlay() {
        await t
            .expect(this.summaryOverlay.visible).ok('Summary-Overlay nicht sichtbar', { timeout: 5000 })
            .expect(this.summaryPanel.find('h2').withText('Evidence-Zusammenfassung').visible).ok();
    }

    /** Prueft ob die Request-ID im Summary-Overlay vorhanden ist */
    async expectRequestId() {
        const infoText = await this.summaryPanel.find('p').textContent;
        await t.expect(infoText).contains('Request-ID:');
    }

    /** Schliesst das Summary-Overlay */
    async closeSummary() {
        await t.click(this.summaryClose);
        await t.expect(this.summaryOverlay.exists).notOk({ timeout: 3000 });
    }

    /** Prueft ob Validierungsfehler angezeigt werden */
    async expectValidationErrors(minCount = 1) {
        await t.expect(this.fieldErrors.count).gte(minCount, `Mindestens ${minCount} Validierungsfehler erwartet`);
    }

    /** Prueft ob KEINE Validierungsfehler angezeigt werden */
    async expectNoValidationErrors() {
        await t.expect(this.fieldErrors.count).eql(0, 'Keine Validierungsfehler erwartet');
    }

    /** Prueft ob ein bestimmtes Feld einen Fehler hat */
    async expectFieldError(name) {
        const fieldGroup = Selector(`[name="${name}"]`).parent('.field-group');
        const fieldCheckbox = Selector(`[name="${name}"]`).parent('.field-checkbox');
        const groupHasError = await fieldGroup.hasClass('field-error').catch(() => false);
        const checkboxHasError = await fieldCheckbox.hasClass('field-error').catch(() => false);
        await t.expect(groupHasError || checkboxHasError).ok(`Feld "${name}" sollte einen Fehler haben`);
    }

    /** Prueft ob ein bestimmtes Feld KEINEN Fehler hat */
    async expectNoFieldError(name) {
        const fieldGroup = Selector(`[name="${name}"]`).parent('.field-group');
        if (await fieldGroup.exists) {
            await t.expect(fieldGroup.hasClass('field-error')).notOk(`Feld "${name}" sollte keinen Fehler haben`);
        }
    }
}

export default new FormPage();
