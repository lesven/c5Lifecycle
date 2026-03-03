const BASE_URL = process.env.BASE_URL || 'http://localhost:8080';

module.exports = {
    baseUrl: BASE_URL,
    browsers: ['chromium:headless --no-sandbox --disable-gpu'],
    src: ['tests/'],
    reporter: 'spec',
    selectorTimeout: 10000,
    assertionTimeout: 10000,
    pageLoadTimeout: 15000,
    ajaxRequestTimeout: 30000,
    pageRequestTimeout: 30000,
};
