import { useEffect, useState } from 'react';
import { useAuth } from '../auth/AuthContext';

interface Me {
  id: number;
  name: string;
}

interface EnrollmentRow {
  id: number;
  course: string;
  status: 'pending' | 'active';
}

type Load<T> = { state: 'loading' } | { state: 'error' } | { state: 'ready'; data: T };

export function Dashboard() {
  const { clients, logout } = useAuth();
  const [me, setMe] = useState<Load<Me>>({ state: 'loading' });
  const [enrollments, setEnrollments] = useState<Load<EnrollmentRow[]>>({ state: 'loading' });

  useEffect(() => {
    let cancelled = false;

    clients.laravel
      .get<Me>('/me')
      .then((r) => !cancelled && setMe({ state: 'ready', data: r.data }))
      .catch(() => !cancelled && setMe({ state: 'error' }));

    clients.laravel
      .get<EnrollmentRow[]>('/enrollments')
      .then((r) => !cancelled && setEnrollments({ state: 'ready', data: r.data }))
      .catch(() => !cancelled && setEnrollments({ state: 'error' }));

    return () => {
      cancelled = true;
    };
  }, [clients]);

  return (
    <main style={{ maxWidth: 640, margin: '2rem auto', padding: '0 1rem' }}>
      <header style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <h1>{me.state === 'ready' ? `Hello, ${me.data.name}` : 'Dashboard'}</h1>
        <button onClick={logout}>Sign out</button>
      </header>

      <h2>My courses</h2>
      {enrollments.state === 'loading' && <p>Loading...</p>}
      {enrollments.state === 'error' && <p role="alert">Could not load your courses.</p>}
      {enrollments.state === 'ready' && enrollments.data.length === 0 && <p>No courses yet.</p>}
      {enrollments.state === 'ready' && (
        <ul>
          {enrollments.data.map((e) => (
            <li key={e.id}>
              {e.course} ({e.status})
            </li>
          ))}
        </ul>
      )}
    </main>
  );
}
