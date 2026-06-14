import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { AUTH_EXPIRED_EVENT, api, clearAccessToken, setAccessToken } from '../api.js';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const navigate = useNavigate();
  const location = useLocation();
  const [user, setUser] = useState(null);
  const [ready, setReady] = useState(false);
  const [loading, setLoading] = useState(true);

  const redirectToLogin = useCallback(() => {
    clearAccessToken();
    setUser(null);
    const next = `${location.pathname || '/'}${location.search || ''}`;
    navigate(`/login?next=${encodeURIComponent(next)}`, { replace: true });
  }, [location.pathname, location.search, navigate]);

  const refresh = useCallback(async () => {
    const me = await api('/api/auth/me');
    setUser(me?.user ?? null);
    return me?.user ?? null;
  }, []);

  useEffect(() => {
    window.addEventListener(AUTH_EXPIRED_EVENT, redirectToLogin);
    return () => window.removeEventListener(AUTH_EXPIRED_EVENT, redirectToLogin);
  }, [redirectToLogin]);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        setLoading(true);
        const me = await api('/api/auth/me');
        if (!cancelled) setUser(me?.user ?? null);
      } catch (e) {
        if (!cancelled && e?.status !== 401) {
          setUser(null);
        }
      } finally {
        if (!cancelled) {
          setReady(true);
          setLoading(false);
        }
      }
    })();
    return () => {
      cancelled = true;
    };
  }, []);

  const login = useCallback(async (loginValue, password) => {
    const data = await api('/api/auth/login', { method: 'POST', body: { login: loginValue, password } });
    if (data?.access_token) setAccessToken(String(data.access_token));
    setUser(data?.user ?? null);
    return data?.user ?? null;
  }, []);

  const logout = useCallback(async () => {
    try {
      await api('/api/auth/logout', { method: 'POST' });
    } catch {
      // ignore
    } finally {
      clearAccessToken();
      setUser(null);
    }
  }, []);

  const value = useMemo(
    () => ({
      user,
      ready,
      loading,
      login,
      logout,
      refresh,
      setUser,
    }),
    [user, ready, loading, login, logout, refresh],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) {
    throw new Error('useAuth must be used within AuthProvider');
  }
  return ctx;
}

export function RequireAuth({ children, adminOnly = false }) {
  const { user, ready } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();

  useEffect(() => {
    if (!ready) return;
    if (!user) {
      const next = `${location.pathname || '/'}${location.search || ''}`;
      navigate(`/login?next=${encodeURIComponent(next)}`, { replace: true });
      return;
    }
    if (adminOnly && user.role !== 'admin') {
      navigate('/dashboard', { replace: true });
    }
  }, [ready, user, adminOnly, navigate, location.pathname, location.search]);

  if (!ready || !user) {
    return <div className="panel">Cargando…</div>;
  }
  if (adminOnly && user.role !== 'admin') {
    return null;
  }

  return children;
}
