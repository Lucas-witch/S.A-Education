import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useRef,
  useState,
  type PropsWithChildren,
} from 'react';
import { ApiError } from '../services/api/client';
import {
  apiRequestResult,
  type ApiRequestOptions,
  type ApiResult,
} from '../services/api/client';
import {
  getCurrentAccount,
  login,
  logout,
  refresh,
  type LoginInput,
} from '../services/api/auth';
import {
  clearRefreshToken,
  readRefreshToken,
  saveRefreshToken,
} from '../services/auth/secureSession';
import type { Account, AccountType } from '../types/api';

interface AuthSession {
  accessToken: string;
  accountType: AccountType;
  account: Account;
}

interface AuthContextValue {
  session: AuthSession | null;
  isLoading: boolean;
  isSubmitting: boolean;
  initializationError: string | null;
  signIn: (input: LoginInput) => Promise<void>;
  signOut: () => Promise<void>;
  retryRestore: () => Promise<void>;
  request: <T>(
    path: string,
    options?: Omit<ApiRequestOptions, 'accessToken'>,
  ) => Promise<ApiResult<T>>;
}

const AuthContext = createContext<AuthContextValue | null>(null);
type Session = AuthContextValue extends { session: infer T } ? Exclude<T, null> : never;

function sessionExpiredError(): ApiError {
  return new ApiError('Sua sessão expirou. Entre novamente.', 'SESSION_EXPIRED', 401);
}

function publicErrorMessage(error: unknown): string {
  if (error instanceof ApiError) {
    return error.message;
  }
  return 'Não foi possível concluir a operação. Tente novamente.';
}

export function AuthProvider({ children }: PropsWithChildren) {
  const [session, setSession] = useState<AuthSession | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [initializationError, setInitializationError] = useState<string | null>(null);
  const restoreStarted = useRef(false);
  const sessionRef = useRef<Session | null>(null);
  const refreshInProgress = useRef<Promise<Session> | null>(null);

  const updateSession = useCallback((nextSession: Session | null) => {
    sessionRef.current = nextSession;
    setSession(nextSession);
  }, []);

  const expireSession = useCallback(async () => {
    updateSession(null);
    setInitializationError('Sua sessão expirou. Entre novamente.');
    try {
      await clearRefreshToken();
    } catch {
      setInitializationError(
        'Sua sessão expirou, mas não foi possível limpar o armazenamento seguro.',
      );
    }
  }, [updateSession]);

  const restoreSession = useCallback(async () => {
    setIsLoading(true);
    try {
      const oldRefreshToken = await readRefreshToken();
      if (!oldRefreshToken) {
        updateSession(null);
        return;
      }

      setInitializationError(null);
      const renewed = await refresh(oldRefreshToken);
      await saveRefreshToken(renewed.tokens.refresh_token);
      const current = await getCurrentAccount(renewed.tokens.access_token);
      updateSession({
        accessToken: renewed.tokens.access_token,
        accountType: current.account_type,
        account: current.user,
      });
    } catch (error) {
      updateSession(null);
      if (error instanceof ApiError && error.status === 401) {
        try {
          await clearRefreshToken();
          setInitializationError('Sua sessão expirou. Entre novamente.');
        } catch {
          setInitializationError(
            'A sessão expirou, mas não foi possível limpar o armazenamento seguro.',
          );
        }
      } else {
        setInitializationError(publicErrorMessage(error));
      }
    } finally {
      setIsLoading(false);
    }
  }, [updateSession]);

  useEffect(() => {
    if (restoreStarted.current) {
      return;
    }
    restoreStarted.current = true;
    void restoreSession();
  }, [restoreSession]);

  const signIn = useCallback(
    async (input: LoginInput) => {
      setIsSubmitting(true);
      setInitializationError(null);
      try {
        const result = await login(input);
        await saveRefreshToken(result.tokens.refresh_token);
        updateSession({
          accessToken: result.tokens.access_token,
          accountType: result.account_type,
          account: result.user,
        });
      } finally {
        setIsSubmitting(false);
      }
    },
    [updateSession],
  );

  const signOut = useCallback(async () => {
    let warning: string | null = null;
    try {
      if (refreshInProgress.current) {
        await refreshInProgress.current;
      }
      const refreshToken = await readRefreshToken();
      if (refreshToken) {
        await logout(refreshToken);
      }
    } catch {
      warning =
        'A sessão foi removida deste aparelho, mas o servidor não confirmou a revogação.';
    }

    try {
      await clearRefreshToken();
    } catch {
      warning =
        'Não foi possível limpar a credencial do armazenamento seguro. Revise o estado deste aparelho.';
    } finally {
      updateSession(null);
    }

    if (warning) {
      throw new Error(warning);
    }
  }, [updateSession]);

  const renewSession = useCallback(async (): Promise<Session> => {
    if (refreshInProgress.current) {
      return refreshInProgress.current;
    }

    const currentSession = sessionRef.current;
    if (!currentSession) {
      throw sessionExpiredError();
    }

    const renewal = (async () => {
      const refreshToken = await readRefreshToken();
      if (!refreshToken) {
        await expireSession();
        throw sessionExpiredError();
      }

      try {
        const result = await refresh(refreshToken);
        await saveRefreshToken(result.tokens.refresh_token);
        const renewedSession = {
          ...currentSession,
          accessToken: result.tokens.access_token,
        };
        updateSession(renewedSession);
        return renewedSession;
      } catch (error) {
        if (error instanceof ApiError && error.status === 401) {
          await expireSession();
          throw sessionExpiredError();
        }
        throw error;
      }
    })();

    refreshInProgress.current = renewal;
    try {
      return await renewal;
    } finally {
      refreshInProgress.current = null;
    }
  }, [expireSession, updateSession]);

  const request = useCallback(
    async <T,>(
      path: string,
      options: Omit<ApiRequestOptions, 'accessToken'> = {},
    ): Promise<ApiResult<T>> => {
      const currentSession = sessionRef.current;
      if (!currentSession) {
        throw sessionExpiredError();
      }

      try {
        return await apiRequestResult<T>(path, {
          ...options,
          accessToken: currentSession.accessToken,
        });
      } catch (error) {
        if (!(error instanceof ApiError) || error.status !== 401) {
          throw error;
        }
      }

      const latestSession = sessionRef.current;
      const renewedSession =
        latestSession && latestSession.accessToken !== currentSession.accessToken
          ? latestSession
          : await renewSession();
      try {
        return await apiRequestResult<T>(path, {
          ...options,
          accessToken: renewedSession.accessToken,
        });
      } catch (error) {
        if (error instanceof ApiError && error.status === 401) {
          await expireSession();
          throw sessionExpiredError();
        }
        throw error;
      }
    },
    [expireSession, renewSession],
  );

  const value: AuthContextValue = {
    session,
    isLoading,
    isSubmitting,
    initializationError,
    signIn,
    signOut,
    retryRestore: restoreSession,
    request,
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth precisa ser usado dentro de AuthProvider.');
  }
  return context;
}
