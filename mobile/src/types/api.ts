export interface ApiErrorBody {
  code: string;
  message: string;
}

export interface ApiEnvelope<T> {
  data: T | null;
  error: ApiErrorBody | null;
  meta: Record<string, unknown> | null;
}

export interface MobileTokens {
  access_token: string;
  refresh_token: string;
  token_type: 'Bearer';
  expires_in: number;
  refresh_expires_in: number;
}

export type AccountType = 'user' | 'institution';
export type UserProfile = 'estudante' | 'professor' | 'instituicao';

export interface Account {
  id: number;
  nome_completo: string;
  email: string;
  username: string;
  perfil: UserProfile;
  perfil_imagem: string;
}

export interface LoginResponse {
  tokens: MobileTokens;
  account_type: AccountType;
  user: Account;
}

export interface RefreshResponse {
  tokens: MobileTokens;
}

export interface MeResponse {
  account_type: AccountType;
  user: Account;
}

export interface LogoutResponse {
  revoked: boolean;
}
