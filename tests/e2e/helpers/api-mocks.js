import { RequestMock } from 'testcafe';

/**
 * Mock-Kontakte fuer contact_select Dropdowns
 * (asset_owner, owner_approval, owner)
 */
const MOCK_CONTACTS = [
    { id: 1, name: 'Max Mustermann' },
    { id: 2, name: 'Erika Musterfrau' },
    { id: 3, name: 'Hans Schmidt' },
];

/**
 * Mock-Mandanten fuer tenant_select Dropdown
 */
const MOCK_TENANTS = [
    { id: 10, name: 'Mandant A' },
    { id: 20, name: 'Mandant B' },
];

/**
 * RequestMock fuer alle API-Endpunkte die auf externe Services
 * (NetBox) zugreifen. /api/submit wird NICHT gemockt – der echte
 * Backend-Flow (inkl. Mailpit) wird getestet.
 */
const apiMocks = RequestMock()
    .onRequestTo(/\/api\/contacts/)
    .respond(JSON.stringify(MOCK_CONTACTS), 200, {
        'content-type': 'application/json',
        'access-control-allow-origin': '*',
    })
    .onRequestTo(/\/api\/tenants/)
    .respond(JSON.stringify(MOCK_TENANTS), 200, {
        'content-type': 'application/json',
        'access-control-allow-origin': '*',
    })
    .onRequestTo(/\/api\/asset-lookup/)
    .respond(JSON.stringify({ found: false }), 200, {
        'content-type': 'application/json',
        'access-control-allow-origin': '*',
    });

export { apiMocks, MOCK_CONTACTS, MOCK_TENANTS };
