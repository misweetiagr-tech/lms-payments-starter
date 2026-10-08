import { describe, expect, it } from 'vitest';
import { AxiosError, type AxiosAdapter, type InternalAxiosRequestConfig } from 'axios';
import { createClients, type TokenStore } from './client';

function memoryStore(access: string | null, refresh: string | null): TokenStore & { access: string | null; refresh: string | null } {
  const s = {
    access,
    refresh,
    getAccess: () => s.access,
    getRefresh: () => s.refresh,
    set(a: string, r?: string) {
      s.access = a;
      if (r) s.refresh = r;
    },
    clear() {
      s.access = null;
      s.refresh = null;
    },
  };
  return s;
}

const ok = (config: InternalAxiosRequestConfig, data: unknown) => ({ data, status: 200, statusText: 'OK', headers: {}, config });

const unauthorized = (config: InternalAxiosRequestConfig) =>
  Promise.reject(new AxiosError('Unauthorized', '401', config, null, { data: {}, status: 401, statusText: 'Unauthorized', headers: {}, config }));

describe('createClients', () => {
  it('adds the access token to Laravel requests', async () => {
    let seen: unknown;
    const adapter: AxiosAdapter = async (config) => {
      seen = config.headers.get('Authorization');
      return ok(config, { id: 1 });
    };

    const { laravel } = createClients(memoryStore('good', 'r1'), adapter);
    await laravel.get('/me');

    expect(seen).toBe('Bearer good');
  });

  it('refreshes once on 401 and retries the original request', async () => {
    let refreshCalls = 0;
    const adapter: AxiosAdapter = async (config) => {
      if (config.url === '/auth/refresh') {
        refreshCalls++;
        return ok(config, { access_token: 'fresh' });
      }
      return config.headers.get('Authorization') === 'Bearer fresh' ? ok(config, { id: 1 }) : unauthorized(config);
    };

    const store = memoryStore('expired', 'r1');
    const { laravel } = createClients(store, adapter);
    const response = await laravel.get('/me');

    expect(response.data).toEqual({ id: 1 });
    expect(refreshCalls).toBe(1);
    expect(store.access).toBe('fresh');
  });

  it('shares one refresh between simultaneous 401s', async () => {
    let refreshCalls = 0;
    const adapter: AxiosAdapter = async (config) => {
      if (config.url === '/auth/refresh') {
        refreshCalls++;
        await new Promise((r) => setTimeout(r, 10));
        return ok(config, { access_token: 'fresh' });
      }
      return config.headers.get('Authorization') === 'Bearer fresh' ? ok(config, {}) : unauthorized(config);
    };

    const { laravel } = createClients(memoryStore('expired', 'r1'), adapter);
    await Promise.all([laravel.get('/a'), laravel.get('/b'), laravel.get('/c')]);

    expect(refreshCalls).toBe(1);
  });

  it('ends the session when the refresh token is rejected', async () => {
    const adapter: AxiosAdapter = async (config) => unauthorized(config);

    const store = memoryStore('expired', 'bad');
    const { laravel } = createClients(store, adapter);

    await expect(laravel.get('/me')).rejects.toBeTruthy();
    expect(store.access).toBeNull();
    expect(store.refresh).toBeNull();
  });

  it('does not loop forever when the retried request is still 401', async () => {
    let laravelCalls = 0;
    const adapter: AxiosAdapter = async (config) => {
      if (config.url === '/auth/refresh') return ok(config, { access_token: 'fresh' });
      laravelCalls++;
      return unauthorized(config);
    };

    const { laravel } = createClients(memoryStore('expired', 'r1'), adapter);

    await expect(laravel.get('/me')).rejects.toBeTruthy();
    expect(laravelCalls).toBe(2); // original + one retry, then stop
  });
});
