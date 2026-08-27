const API_BASE = import.meta.env.VITE_API_URL || 'http://localhost:5000/api';

function getToken() {
  return localStorage.getItem('kp_token');
}

export async function api(path, options = {}) {
  const headers = {
    'Content-Type': 'application/json',
    ...(options.headers || {}),
  };

  const token = getToken();
  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }

  const res = await fetch(`${API_BASE}${path}`, {
    ...options,
    headers,
    body: options.body ? JSON.stringify(options.body) : undefined,
  });

  let data;
  try {
    data = await res.json();
  } catch {
    data = { status: 'error', message: 'Invalid server response' };
  }

  if (!res.ok) {
    throw new Error(data.message || 'Request failed');
  }

  return data;
}

export { API_BASE };
