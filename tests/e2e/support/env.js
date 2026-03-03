export function requiredEnv(name) {
  const value = process.env[name];
  if (!value) {
    throw new Error(`Missing required environment variable: ${name}`);
  }

  return value;
}

export function getBaseUrl() {
  return process.env.E2E_BASE_URL || 'http://localhost:8080';
}
