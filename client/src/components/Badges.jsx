export function RoleBadge({ role }) {
  const cls =
    role === 'Admin' ? 'badge-admin' : role === 'Pharmacist' ? 'badge-pharmacist' : 'badge-employee';
  return <span className={`badge ${cls}`}>{role}</span>;
}

export function CategoryBadge({ category }) {
  const cls = category === 'Antibiotic' ? 'badge-antibiotic' : 'badge-general';
  return <span className={`badge ${cls}`}>{category}</span>;
}

export function AlertBadge({ status }) {
  const map = {
    Normal: 'badge-normal',
    Warning: 'badge-warning',
    Critical: 'badge-critical',
  };
  return <span className={`badge ${map[status] || 'badge-general'}`}>{status}</span>;
}

export function money(n) {
  return `৳${Number(n || 0).toFixed(2)}`;
}
