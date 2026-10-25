import type { LoginResponse, MeResponse, RefreshResponse } from '../../types/api';
import { apiRequest } from './client';

export interface LoginInput {
  identifier: string;
  password: string;
  account_type: 'user' | 'institution';
  device_name: string;
}

export function login(input: LoginInput): Promise<LoginResponse> {
  return apiRequest<LoginResponse>('auth.php?action=login', {
    method: 'POST',
    body: input,
  });
}

export function refresh(refreshToken: string): Promise<RefreshResponse> {
  return apiRequest<RefreshResponse>('auth.php?action=refresh', {
    method: 'POST',
    body: { refresh_token: refreshToken },
  });
}

export function getCurrentAccount(accessToken: string): Promise<MeResponse> {
  return apiRequest<MeResponse>('auth.php?action=me', {
    accessToken,
  });
}

export function logout(refreshToken: string): Promise<{ revoked: boolean }> {
  return apiRequest('auth.php?action=logout', {
    method: 'POST',
    body: { refresh_token: refreshToken },
  });
}
