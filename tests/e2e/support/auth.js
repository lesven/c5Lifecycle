import { Role, ClientFunction } from 'testcafe';
import { getBaseUrl, requiredEnv } from './env.js';

const baseUrl = getBaseUrl();
const getPathname = ClientFunction(() => window.location.pathname);

export const authenticatedUserRole = Role(`${baseUrl}/login`, async (t) => {
  await t
    .typeText('#input-email', requiredEnv('E2E_USER_EMAIL'), { replace: true })
    .typeText('#input-password', requiredEnv('E2E_USER_PASSWORD'), { replace: true })
    .click('button[type="submit"]');
  await t.expect(getPathname()).eql('/');
}, { preserveUrl: false });
