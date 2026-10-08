import axios, { type AxiosAdapter, type AxiosInstance, type InternalAxiosRequestConfig } from 'axios';

export interface TokenStore {
  getAccess(): string | null;
  getRefresh(): string | null;
  set(access: string, refresh?: string): void;
  clear(): void;
}

export interface Clients {
  /** Talks to the Node auth service (login, refresh). */
  node: AxiosInstance;
  /** Talks to Laravel under /api/app with the same token. */
  laravel: AxiosInstance;
}

type Retryable = InternalAxiosRequestConfig & { _retried?: boolean };

/**
 * Two clients, one identity. The Laravel client adds the access token to every
 * request. On a 401 it asks the Node service for a new access token using the
 * refresh token, then retries the original request once.
 *
 * `adapter` exists so tests can run without a network.
 */
export function createClients(store: TokenStore, adapter?: AxiosAdapter): Clients {
  const node = axios.create({ baseURL: '/node', adapter });
  const laravel = axios.create({ baseURL: '/api/app', adapter });

  laravel.interceptors.request.use((config) => {
    const token = store.getAccess();
    if (token) config.headers.set('Authorization', `Bearer ${token}`);
    return config;
  });

  // Share one refresh between requests that fail at the same moment,
  // so five parallel 401s cause one refresh call, not five.
  let refreshing: Promise<string> | null = null;

  const refreshAccessToken = (): Promise<string> => {
    refreshing ??= (async () => {
      const refresh = store.getRefresh();
      if (!refresh) throw new Error('No refresh token');
      const { data } = await node.post<{ access_token: string }>('/auth/refresh', { refresh_token: refresh });
      store.set(data.access_token);
      return data.access_token;
    })().finally(() => {
      refreshing = null;
    });

    return refreshing;
  };

  laravel.interceptors.response.use(
    (response) => response,
    async (error) => {
      const original = error.config as Retryable | undefined;

      if (error.response?.status !== 401 || !original || original._retried) {
        return Promise.reject(error);
      }

      original._retried = true;

      try {
        const token = await refreshAccessToken();
        original.headers.set('Authorization', `Bearer ${token}`);
        return laravel(original);
      } catch {
        // Refresh failed (expired or revoked): the session is over.
        store.clear();
        return Promise.reject(error);
      }
    },
  );

  return { node, laravel };
}

/** Browser storage implementation. Kept separate so the client is testable. */
export const browserTokenStore: TokenStore = {
  getAccess: () => localStorage.getItem('access_token'),
  getRefresh: () => localStorage.getItem('refresh_token'),
  set(access, refresh) {
    localStorage.setItem('access_token', access);
    if (refresh) localStorage.setItem('refresh_token', refresh);
  },
  clear() {
    localStorage.removeItem('access_token');
    localStorage.removeItem('refresh_token');
  },
};
