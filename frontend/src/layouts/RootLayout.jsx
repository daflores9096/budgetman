import { Outlet } from 'react-router-dom';
import { AuthProvider } from '../auth/AuthContext.jsx';

export default function RootLayout() {
  return (
    <AuthProvider>
      <Outlet />
    </AuthProvider>
  );
}
