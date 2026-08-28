import { useEffect, useMemo, useState } from 'react';
import { api } from '../api/client';
import Modal from '../components/Modal';
import { RoleBadge } from '../components/Badges';
import FilterToolbar, { FilterSelect } from '../components/FilterToolbar';
import { matchSearch } from '../utils/tableFilter';

const empty = { name: '', role: 'Employee', username: '', email: '', password: '' };

const ROLES = ['Admin', 'Pharmacist', 'Employee'];

export default function Users() {
  const [rows, setRows] = useState([]);
  const [error, setError] = useState('');
  const [open, setOpen] = useState(false);
  const [editId, setEditId] = useState(null);
  const [form, setForm] = useState(empty);
  const [search, setSearch] = useState('');
  const [filterRole, setFilterRole] = useState('');

  const filteredRows = useMemo(
    () =>
      rows.filter((r) => {
        if (filterRole && r.role !== filterRole) return false;
        return matchSearch(r, search, ['user_id', 'name', 'role', 'username', 'email']);
      }),
    [rows, search, filterRole],
  );

  async function load() {
    try {
      const res = await api('/users');
      setRows(res.data);
      setError('');
    } catch (err) {
      setError(err.message);
    }
  }

  useEffect(() => {
    load();
  }, []);

  function openAdd() {
    setEditId(null);
    setForm(empty);
    setOpen(true);
  }

  function openEdit(row) {
    setEditId(row.user_id);
    setForm({
      name: row.name,
      role: row.role,
      username: row.username,
      email: row.email,
      password: '',
    });
    setOpen(true);
  }

  async function save(e) {
    e.preventDefault();
    try {
      if (editId) {
        await api(`/users/${editId}`, { method: 'PUT', body: form });
      } else {
        await api('/users', { method: 'POST', body: form });
      }
      setOpen(false);
      load();
    } catch (err) {
      setError(err.message);
    }
  }

  async function remove(id) {
    if (!window.confirm('Delete this user?')) return;
    try {
      await api(`/users/${id}`, { method: 'DELETE' });
      load();
    } catch (err) {
      setError(err.message);
    }
  }

  return (
    <>
      <div className="topbar">
        <h1>Users</h1>
        <button type="button" className="add-btn" onClick={openAdd}>
          + Add User
        </button>
      </div>
      {error && <p className="error-text">{error}</p>}
      <FilterToolbar
        search={search}
        onSearchChange={setSearch}
        searchPlaceholder="Search by name, username, email, ID..."
      >
        <FilterSelect
          value={filterRole}
          onChange={setFilterRole}
          allLabel="All Roles"
          options={ROLES.map((r) => ({ value: r, label: r }))}
        />
      </FilterToolbar>
      {rows.length === 0 ? (
        <div className="empty-state">No users found</div>
      ) : filteredRows.length === 0 ? (
        <div className="empty-state">No users match your search or filters</div>
      ) : (
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Name</th>
              <th>Role</th>
              <th>Username</th>
              <th>Email</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            {filteredRows.map((r) => (
              <tr key={r.user_id}>
                <td>{r.user_id}</td>
                <td>{r.name}</td>
                <td>
                  <RoleBadge role={r.role} />
                </td>
                <td>{r.username}</td>
                <td>{r.email}</td>
                <td>
                  <button type="button" className="edit" onClick={() => openEdit(r)}>
                    Edit
                  </button>
                  <button type="button" className="delete" onClick={() => remove(r.user_id)}>
                    Delete
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}

      {open && (
        <Modal title={editId ? 'Edit User' : 'Add User'} onClose={() => setOpen(false)}>
          <form onSubmit={save}>
            <div className="form-group">
              <label>Name</label>
              <input
                value={form.name}
                onChange={(e) => setForm({ ...form, name: e.target.value })}
                required
              />
            </div>
            <div className="form-group">
              <label>Role</label>
              <select
                value={form.role}
                onChange={(e) => setForm({ ...form, role: e.target.value })}
              >
                <option>Admin</option>
                <option>Pharmacist</option>
                <option>Employee</option>
              </select>
            </div>
            <div className="form-group">
              <label>Username</label>
              <input
                value={form.username}
                onChange={(e) => setForm({ ...form, username: e.target.value })}
                required
              />
            </div>
            <div className="form-group">
              <label>Email</label>
              <input
                type="email"
                value={form.email}
                onChange={(e) => setForm({ ...form, email: e.target.value })}
                required
              />
            </div>
            <div className="form-group">
              <label>{editId ? 'New Password (optional)' : 'Password'}</label>
              <input
                type="password"
                value={form.password}
                onChange={(e) => setForm({ ...form, password: e.target.value })}
                required={!editId}
              />
            </div>
            <div className="form-actions">
              <button type="button" className="cancel-btn" onClick={() => setOpen(false)}>
                Cancel
              </button>
              <button type="submit" className="save-btn">
                Save
              </button>
            </div>
          </form>
        </Modal>
      )}
    </>
  );
}
