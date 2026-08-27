import { useEffect, useState } from 'react';
import { api } from '../api/client';
import { useAuth } from '../context/AuthContext';
import { money } from '../components/Badges';

export default function Dashboard() {
  const { user, hasRole } = useAuth();
  const [stats, setStats] = useState(null);
  const [alerts, setAlerts] = useState(null);
  const [error, setError] = useState('');

  useEffect(() => {
    let cancelled = false;
    async function load() {
      try {
        const dash = await api('/reports/dashboard');
        if (!cancelled) setStats(dash.data);
        if (user?.role === 'Admin' || user?.role === 'Pharmacist') {
          const a = await api('/alerts');
          if (!cancelled) setAlerts(a.data);
        }
      } catch (err) {
        if (!cancelled) setError(err.message);
      }
    }
    load();
    return () => {
      cancelled = true;
    };
  }, [user?.role]);

  return (
    <>
      <div className="topbar">
        <h1>Dashboard</h1>
        <span className="user-chip">
          Welcome, {user?.name} ({user?.role})
        </span>
      </div>

      {error && <p className="error-text">{error}</p>}

      {alerts && (
        <>
          {alerts.low_stock?.length > 0 && (
            <div className="alert-box danger">
              <h3>Low Stock Alerts (below {alerts.low_stock_threshold})</h3>
              <ul>
                {alerts.low_stock.map((m) => (
                  <li key={m.medicine_id}>
                    {m.medicine_name} — {m.quantity_in_stock} left
                  </li>
                ))}
              </ul>
            </div>
          )}
          {alerts.near_expiry?.length > 0 && (
            <div className="alert-box">
              <h3>Expiry Alerts (within {alerts.expiry_alert_days} days)</h3>
              <ul>
                {alerts.near_expiry.map((m) => (
                  <li key={m.medicine_id}>
                    {m.medicine_name} — expires {m.expiry_date}
                  </li>
                ))}
              </ul>
            </div>
          )}
        </>
      )}

      {stats && (
        <div className="cards">
          {hasRole('Admin') && (
            <div className="card">
              <h3>Users</h3>
              <p>{stats.users}</p>
            </div>
          )}
          {hasRole('Admin', 'Pharmacist') && (
            <>
              <div className="card">
                <h3>Medicines</h3>
                <p>{stats.medicines}</p>
              </div>
              <div className="card">
                <h3>Stock Units</h3>
                <p>{stats.stock_units}</p>
              </div>
            </>
          )}
          <div className="card">
            <h3>Total Sales</h3>
            <p>{money(stats.total_sales)}</p>
          </div>
          <div className="card">
            <h3>Invoices</h3>
            <p>{stats.invoices}</p>
          </div>
          <div className="card">
            <h3>Customers</h3>
            <p>{stats.customers}</p>
          </div>
          {hasRole('Admin', 'Pharmacist') && (
            <div className="card">
              <h3>Profit / Loss</h3>
              <p>{money(stats.profit_loss)}</p>
            </div>
          )}
        </div>
      )}
    </>
  );
}
