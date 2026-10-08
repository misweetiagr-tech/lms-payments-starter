import { createContext, useContext, useMemo, useState, type ReactNode } from 'react';
import { browserTokenStore, createClients, type Clients } from '../api/client';

interface AuthValue {
  clients: Clients;
  signedIn: boolean;
  login(userId: number, otp: string): Promise<void>;
  logout(): void;
}

const AuthContext = createContext<AuthValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [signedIn, setSignedIn] = useState(() => browserTokenStore.getAccess() !== null);

  // If a refresh fails the store is cleared; reflect that in the UI on the next failed call.
  const clients = useMemo(() => {
    const store = {
      ...browserTokenStore,
      clear() {
        browserTokenStore.clear();
        setSignedIn(false);
      },
    };
    return createClients(store);
  }, []);

  const value: AuthValue = {
    clients,
    signedIn,
    async login(userId, otp) {
      const { data } = await clients.node.post<{ access_token: string; refresh_token: string }>('/auth/login', {
        user_id: userId,
        otp,
      });
      browserTokenStore.set(data.access_token, data.refresh_token);
      setSignedIn(true);
    },
    logout() {
      browserTokenStore.clear();
      setSignedIn(false);
    },
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthValue {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used inside AuthProvider');
  return ctx;
}
