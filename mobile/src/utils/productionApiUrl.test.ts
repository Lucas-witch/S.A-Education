import { validateProductionApiUrl } from './productionApiUrl';

describe('validateProductionApiUrl', () => {
  it('accepts and normalizes a public HTTPS API base URL', () => {
    expect(validateProductionApiUrl('https://api.saeducation.app/api/v1/')).toBe(
      'https://api.saeducation.app/api/v1',
    );
  });

  it.each([
    undefined,
    '',
    'http://api.example.com/api/v1',
    'https://localhost/api/v1',
    'https://dev.local/api/v1',
    'https://192.168.1.20/api/v1',
    'https://10.0.0.4/api/v1',
    'https://172.16.1.4/api/v1',
    'https://[::ffff:192.168.1.2]/api/v1',
    'https://api.example.com/api/v1',
    'https://api.example.com/api',
    'https://user:password@api.example.com/api/v1',
  ])('rejects an unsafe production URL (%s)', (value) => {
    expect(() => validateProductionApiUrl(value)).toThrow();
  });
});
