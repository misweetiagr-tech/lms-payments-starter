import { AuthProvider, useAuth } from './auth/AuthContext';
import { Dashboard } from './pages/Dashboard';
import { Login } from './pages/Login';

function Screen() {
  const { signedIn } = useAuth();
  return signedIn ? <Dashboard /> : <Login />;
}

export default function App() {
  return (
    <AuthProvider>
      <Screen />
    </AuthProvider>
  );
}
