import { Navigate, Outlet } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import Sidebar from './Sidebar';

export default function AppLayout({ roles }) {
  const { user, loading } = useAuth();

  if (loading) {
    return <div className="login-page">Loading...</div>;
  }

  if (!user) {
    return <Navigate to="/login" replace />;
  }

  if (roles && !roles.includes(user.role)) {
    return <Navigate to="/dashboard" replace />;
  }

  return (
    <div className="app-shell">
      <Sidebar />
      <main className="main">
        <Outlet />
      </main>
    </div>
  );
}
