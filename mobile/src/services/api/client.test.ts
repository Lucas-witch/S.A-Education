import { ApiError, parseApiEnvelope, parseApiEnvelopeResult } from './client';

describe('parseApiEnvelope', () => {
  it('returns data from a successful API envelope', () => {
    const data = { revoked: true };

    expect(parseApiEnvelope({ data, error: null, meta: null }, 200)).toEqual(data);
  });

  it('preserves response metadata for paginated endpoints', () => {
    expect(
      parseApiEnvelopeResult(
        { data: ['room'], error: null, meta: { page: 2, pages: 4 } },
        200,
      ),
    ).toEqual({ data: ['room'], meta: { page: 2, pages: 4 } });
  });

  it('preserves the public error code and HTTP status', () => {
    expect(() =>
      parseApiEnvelope(
        {
          data: null,
          error: { code: 'INVALID_CREDENTIALS', message: 'Credenciais inválidas.' },
          meta: null,
        },
        401,
      ),
    ).toThrow(
      expect.objectContaining({
        code: 'INVALID_CREDENTIALS',
        message: 'Credenciais inválidas.',
        status: 401,
      }),
    );
  });

  it('rejects responses that do not follow the documented envelope', () => {
    expect(() => parseApiEnvelope({ data: [] }, 200)).toThrow(
      expect.objectContaining({
        code: 'INVALID_API_RESPONSE',
        status: 200,
      }),
    );
  });

  it('rejects success responses without data', () => {
    expect(() => parseApiEnvelope({ data: null, error: null, meta: null }, 204)).toThrow(
      ApiError,
    );
  });
});
