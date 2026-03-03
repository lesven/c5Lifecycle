import { Selector } from 'testcafe';

/** Page Object fuer die Startseite (/) */
class IndexPage {
    constructor() {
        this.heading = Selector('h1').withText('Evidence erfassen');
        this.trackA = Selector('.track-section.track-a');
        this.trackB = Selector('.track-section.track-b');
        this.formCards = Selector('.form-card');
    }

    /** Gibt die Formular-Karte mit dem passenden href-slug zurueck */
    getCardBySlug(slug) {
        return this.formCards.withAttribute('href', new RegExp(slug));
    }

    /** Gibt die Karten einer Track-Sektion zurueck */
    getTrackACards() {
        return this.trackA.find('.form-card');
    }

    getTrackBCards() {
        return this.trackB.find('.form-card');
    }
}

export default new IndexPage();
