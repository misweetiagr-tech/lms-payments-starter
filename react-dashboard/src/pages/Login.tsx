import { useState, type FormEvent } from 'react';
import { useAuth } from '../auth/AuthContext';

export function Login() {
  const { login } = useAuth();
  const [userId, setUserId] = useState('3');
  const [otp, setOtp] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function submit(e: FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError(null);
    try {
      await login(Number(userId), otp);
    } catch {
      setError('Login failed. Check the user id and the demo code.');
    } finally {
      setBusy(false);
    }
  }

  return (
    <form onSubmit={submit} style={{ maxWidth: 320, margin: '4rem auto', display: 'grid', gap: 12 }}>
      <h1>Sign in</h1>
      <label>
        User id
        <input value={userId} onChange={(e) => setUserId(e.target.value)} inputMode="numeric" required />
      </label>
      <label>
        Code (demo: 123456)
        <input value={otp} onChange={(e) => setOtp(e.target.value)} inputMode="numeric" required />
      </label>
      {error && <p role="alert" style={{ color: '#b3402a' }}>{error}</p>}
      <button disabled={busy}>{busy ? 'Signing in...' : 'Sign in'}</button>
    </form>
  );
}
