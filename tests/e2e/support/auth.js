import { Role } from 'testcafe';
import { getBaseUrl, requiredEnv } from './env.js';

const baseUrl = getBaseUrl();

export const authenticatedUserRole = Role(`${baseUrl}/login`, async (t) => {
  await t
    .typeText('#input-email', requiredEnv('E2E_USER_EMAIL'), { replace: true })
    .typeText('#input-password', requiredEnv('E2E_USER_PASSWORD'), { replace: true })
    .click('button[type="submit"]')
    .expect(t.eval(() => window.location.pathname))
    .eql('/');
}, { preserveUrl: true });
