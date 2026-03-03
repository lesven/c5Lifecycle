import { Selector } from 'testcafe';

export class LoginPage {
  emailInput = Selector('#input-email');
  passwordInput = Selector('#input-password');
  submitButton = Selector('button[type="submit"]');
  errorBox = Selector('.login-error');

  async login(t, email, password) {
    await t
      .typeText(this.emailInput, email, { replace: true })
      .typeText(this.passwordInput, password, { replace: true })
      .click(this.submitButton);
  }
}
