import type { ApiEnvelope } from '../../types/api';

const REQUEST_TIMEOUT_MS = 15_000;
const MAX_RESPONSE_LENGTH = 1_048_576;

export class ApiError extends Error {
  readonly code: string;
  readonly status: number | null;

  constructor(message: string, code: string, status: number | null = null) {
    super(message);
    this.name = 'ApiError';
    this.code = code;
    this.status = status;
  }
}

export type ApiRequestOptions = {
  method?: 'GET' | 'POST';
  accessToken?: string;
  body?: unknown;
  timeoutMs?: number;
};

export interface ApiResult<T> {
  data: T;
  meta: Record<string, unknown> | null;
}

export function parseApiEnvelopeResult<T>(
  payload: unknown,
  status: number,
): ApiResult<T> {
  if (
    typeof payload !== 'object' ||
    payload === null ||
    !('data' in payload) ||
    !('error' in payload) ||
    !('meta' in payload)
  ) {
    throw new ApiError(
      'O servidor retornou uma resposta inválida.',
      'INVALID_API_RESPONSE',
      status,
    );
  }

  const envelope = payload as ApiEnvelope<T>;
  if (status < 200 || status >= 300 || envelope.error !== null) {
    const error =
      envelope.error !== null &&
      typeof envelope.error === 'object' &&
      typeof envelope.error.code === 'string' &&
      typeof envelope.error.message === 'string'
        ? envelope.error
        : {
            code: 'REQUEST_FAILED',
            message: 'Não foi possível concluir a solicitação.',
          };
    throw new ApiError(error.message, error.code, status);
  }

  if (envelope.data === null) {
    throw new ApiError(
      'O servidor retornou uma resposta sem dados.',
      'INVALID_API_RESPONSE',
      status,
    );
  }

  return { data: envelope.data, meta: envelope.meta };
}

export function parseApiEnvelope<T>(payload: unknown, status: number): T {
  return parseApiEnvelopeResult<T>(payload, status).data;
}

function configuredApiBaseUrl(): string {
  const configured = process.env.EXPO_PUBLIC_API_BASE_URL?.trim();
  if (!configured) {
    throw new ApiError(
      'Configure EXPO_PUBLIC_API_BASE_URL para conectar ao backend.',
      'API_NOT_CONFIGURED',
    );
  }

  let parsed: URL;
  try {
    parsed = new URL(configured);
  } catch {
    throw new ApiError('A URL configurada para a API é inválida.', 'API_NOT_CONFIGURED');
  }

  const path = parsed.pathname.replace(/\/+$/, '');
  if (
    !['http:', 'https:'].includes(parsed.protocol) ||
    parsed.username !== '' ||
    parsed.password !== '' ||
    parsed.search !== '' ||
    parsed.hash !== '' ||
    !path.endsWith('/api/v1')
  ) {
    throw new ApiError('Use uma URL válida terminada em /api/v1.', 'API_NOT_CONFIGURED');
  }

  const allowDevelopmentHttp =
    process.env.NODE_ENV !== 'production' &&
    process.env.EXPO_PUBLIC_ALLOW_HTTP_API === 'true' &&
    isPrivateDevelopmentHost(parsed.hostname);
  if (parsed.protocol !== 'https:' && !allowDevelopmentHttp) {
    throw new ApiError(
      'A API deve usar HTTPS fora do desenvolvimento local.',
      'INSECURE_API_URL',
    );
  }

  return configured.replace(/\/+$/, '');
}

function isPrivateDevelopmentHost(hostname: string): boolean {
  const normalized = hostname.toLowerCase().replace(/^\[|\]$/g, '');
  if (
    ['localhost', '127.0.0.1', '::1', '10.0.2.2'].includes(normalized) ||
    normalized.endsWith('.local')
  ) {
    return true;
  }

  const octets = normalized.split('.').map(Number);
  if (
    octets.length !== 4 ||
    octets.some((octet) => !Number.isInteger(octet) || octet < 0 || octet > 255)
  ) {
    return false;
  }

  return (
    octets[0] === 10 ||
    (octets[0] === 192 && octets[1] === 168) ||
    (octets[0] === 172 && octets[1] !== undefined && octets[1] >= 16 && octets[1] <= 31)
  );
}

export async function apiRequest<T>(
  path: string,
  options: ApiRequestOptions = {},
): Promise<T> {
  return (await apiRequestResult<T>(path, options)).data;
}

export async function apiRequestResult<T>(
  path: string,
  options: ApiRequestOptions = {},
): Promise<ApiResult<T>> {
  const baseUrl = configuredApiBaseUrl();
  const controller = new AbortController();
  const timeoutMs = options.timeoutMs ?? REQUEST_TIMEOUT_MS;
  const timeout = setTimeout(() => controller.abort(), timeoutMs);
  const headers: Record<string, string> = {
    Accept: 'application/json',
  };

  if (options.body !== undefined) {
    headers['Content-Type'] = 'application/json';
  }
  if (options.accessToken) {
    headers.Authorization = `Bearer ${options.accessToken}`;
  }

  try {
    const response = await fetch(`${baseUrl}/${path.replace(/^\/+/, '')}`, {
      method: options.method ?? 'GET',
      headers,
      body: options.body === undefined ? undefined : JSON.stringify(options.body),
      signal: controller.signal,
    });
    const responseText = await response.text();

    if (responseText.length > MAX_RESPONSE_LENGTH) {
      throw new ApiError(
        'A resposta do servidor excede o limite permitido.',
        'RESPONSE_TOO_LARGE',
        response.status,
      );
    }

    let payload: unknown;
    try {
      payload = JSON.parse(responseText) as unknown;
    } catch {
      throw new ApiError(
        'O servidor retornou uma resposta inválida.',
        'INVALID_API_RESPONSE',
        response.status,
      );
    }

    return parseApiEnvelopeResult<T>(payload, response.status);
  } catch (error) {
    if (error instanceof ApiError) {
      throw error;
    }
    if (controller.signal.aborted) {
      throw new ApiError(
        'A solicitação expirou. Verifique a conexão e tente novamente.',
        'REQUEST_TIMEOUT',
      );
    }
    throw new ApiError(
      'Não foi possível conectar ao servidor. Verifique a rede e tente novamente.',
      'NETWORK_ERROR',
    );
  } finally {
    clearTimeout(timeout);
  }
}
