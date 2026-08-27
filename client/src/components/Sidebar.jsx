import { NavLink, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

const ALL_LINKS = [
  { to: '/dashboard', label: 'Dashboard', roles: ['Admin', 'Pharmacist', 'Employee'] },
  { to: '/users', label: 'Users', roles: ['Admin'] },
  { to: '/medicines', label: 'Medicines', roles: ['Admin', 'Pharmacist'] },
  { to: '/suppliers', label: 'Suppliers', roles: ['Admin', 'Pharmacist'] },
  { to: '/antibiotics', label: 'Antibiotics', roles: ['Admin', 'Pharmacist'] },
  { to: '/inventory', label: 'Inventory', roles: ['Admin', 'Pharmacist'] },
  { to: '/sales', label: 'Sales', roles: ['Admin', 'Pharmacist', 'Employee'] },
  { to: '/reports', label: 'Reports', roles: ['Admin', 'Pharmacist'] },
  { to: '/salaries', label: 'Salaries', roles: ['Admin'] },
  { to: '/invoices', label: 'Invoices', roles: ['Admin', 'Pharmacist', 'Employee'] },
  { to: '/customers', label: 'Customers', roles: ['Admin', 'Pharmacist', 'Employee'] },
];

export default function Sidebar() {
  const { user, logout, hasRole } = useAuth();
  const navigate = useNavigate();

  function handleLogout() {
    logout();
    navigate('/login');
  }

  const links = ALL_LINKS.filter((l) => hasRole(...l.roles));

  return (
    <aside className="sidebar">
      <h2>Khan Pharmacy</h2>
      <ul>
        {links.map((link) => (
          <li key={link.to}>
            <NavLink to={link.to} className={({ isActive }) => (isActive ? 'active' : '')}>
              {link.label}
            </NavLink>
          </li>
        ))}
        <li>
          <button type="button" className="nav-link" onClick={handleLogout}>
            Logout ({user?.name})
          </button>
        </li>
      </ul>
    </aside>
  );
}
