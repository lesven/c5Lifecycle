import { ClientFunction } from 'testcafe';
import { LoginPage } from './pages/login.page.js';
import { loadFixture } from './support/fixture-loader.js';
import { getBaseUrl, requiredEnv } from './support/env.js';

const baseUrl = getBaseUrl();
const loginFixture = loadFixture('login');
const getPathname = ClientFunction(() => window.location.pathname);

fixture('E2E Login').page(`${baseUrl}/login`);

const loginPage = new LoginPage();

test('allows login with valid credentials', async (t) => {
  await loginPage.login(t, requiredEnv(loginFixture.valid.emailEnv), requiredEnv(loginFixture.valid.passwordEnv));

  await t.expect(getPathname()).eql('/');
});

test('shows a helpful error for invalid credentials', async (t) => {
  await loginPage.login(t, loginFixture.invalid.email, loginFixture.invalid.password);

  await t
    .expect(getPathname())
    .eql('/login')
    .expect(loginPage.errorBox.exists)
    .ok();
});
