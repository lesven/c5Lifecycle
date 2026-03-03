import { Selector } from 'testcafe';

export async function selectFirstNonEmptyOption(t, selector) {
  const option = Selector(selector)
    .find('option')
    .filter((node) => node.value && node.value.trim() !== '')
    .nth(0);

  await t
    .expect(option.exists)
    .ok(`No selectable option available for ${selector}`)
    .click(selector)
    .click(option);
}

export async function checkCheckbox(t, selector) {
  const isChecked = await Selector(selector).checked;
  if (!isChecked) {
    await t.click(selector);
  }
}
