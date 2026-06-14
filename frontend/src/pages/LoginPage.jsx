import { useMemo, useState } from 'react';
import { Eye, EyeOff } from 'lucide-react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext.jsx';

export default function LoginPage() {
  const nav = useNavigate();
  const { login } = useAuth();
  const [params] = useSearchParams();
  const next = useMemo(() => params.get('next') || '/dashboard', [params]);
  const [loginValue, setLoginValue] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  async function onSubmit(e) {
    e.preventDefault();
    setError('');
    try {
      setLoading(true);
      await login(loginValue, password);
      nav(next, { replace: true });
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="auth-content">
      <div className="auth-head">
        <div className="auth-badge">Budgetman</div>
        <div className="auth-title">Bienvenido/a de nuevo</div>
        <div className="auth-sub">Inicia sesión para acceder a tu dashboard, ingresos y gastos.</div>
      </div>
      {error ? <div className="panel error">{error}</div> : null}
      <form className="auth-form" onSubmit={onSubmit}>
        <label className="ui-field">
          <span className="ui-label">Email o usuario</span>
          <input
            className="ui-input"
            value={loginValue}
            onChange={(e) => setLoginValue(e.target.value)}
            autoComplete="username"
            placeholder="tucorreo@ejemplo.com o usuario"
          />
        </label>
        <label className="ui-field">
          <span className="ui-label">Contraseña</span>
          <div className="ui-password-wrap">
            <input
              className="ui-input ui-input--password"
              type={showPassword ? 'text' : 'password'}
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              autoComplete="current-password"
            />
            <button
              type="button"
              className="ui-password-toggle"
              onClick={() => setShowPassword((v) => !v)}
              aria-label={showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'}
              aria-pressed={showPassword}
            >
              {showPassword ? <EyeOff size={18} strokeWidth={2.2} aria-hidden /> : <Eye size={18} strokeWidth={2.2} aria-hidden />}
            </button>
          </div>
        </label>
        <button className="ui-btn ui-btn--primary auth-submit" type="submit" disabled={loading}>
          Ingresar
        </button>
      </form>
      <div className="auth-foot">
        <button className="ui-btn ui-btn--ghost" type="button" onClick={() => nav('/forgot-password')}>
          ¿Olvidaste tu contraseña?
        </button>
      </div>
    </div>
  );
}
