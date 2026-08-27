import { createContext, useContext, useEffect, useState } from 'react';
import { api } from '../api/client';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(() => {
    const raw = localStorage.getItem('kp_user');
    return raw ? JSON.parse(raw) : null;
  });
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const token = localStorage.getItem('kp_token');
    if (!token) {
      setLoading(false);
      return;
    }
    api('/auth/me')
      .then((res) => {
        setUser(res.data);
        localStorage.setItem('kp_user', JSON.stringify(res.data));
      })
      .catch(() => {
        localStorage.removeItem('kp_token');
        localStorage.removeItem('kp_user');
        setUser(null);
      })
      .finally(() => setLoading(false));
  }, []);

  async function login(username, password) {
    const res = await api('/auth/login', {
      method: 'POST',
      body: { username, password },
    });
    localStorage.setItem('kp_token', res.data.token);
    localStorage.setItem('kp_user', JSON.stringify(res.data.user));
    setUser(res.data.user);
    return res.data.user;
  }

  function logout() {
    localStorage.removeItem('kp_token');
    localStorage.removeItem('kp_user');
    setUser(null);
  }

  function hasRole(...roles) {
    return user && roles.includes(user.role);
  }

  return (
    <AuthContext.Provider value={{ user, loading, login, logout, hasRole }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  return useContext(AuthContext);
}
