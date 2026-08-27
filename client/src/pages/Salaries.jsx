import { useEffect, useState } from 'react';
import { api } from '../api/client';
import Modal from '../components/Modal';
import { money } from '../components/Badges';

const empty = {
  employee_id: '',
  month: '',
  basic_salary: '',
  sales_linked_bonus: 0,
  payment_date: '',
};

export default function Salaries() {
  const [rows, setRows] = useState([]);
  const [users, setUsers] = useState([]);
  const [error, setError] = useState('');
  const [open, setOpen] = useState(false);
  const [editId, setEditId] = useState(null);
  const [form, setForm] = useState(empty);

  async function load() {
    try {
      const [s, u] = await Promise.all([api('/salaries'), api('/users')]);
      setRows(s.data);
      setUsers(u.data.filter((x) => x.role !== 'Admin'));
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
    setEditId(row.salary_id);
    setForm({
      employee_id: row.employee_id,
      month: row.month,
      basic_salary: row.basic_salary,
      sales_linked_bonus: row.sales_linked_bonus,
      payment_date: row.payment_date,
    });
    setOpen(true);
  }

  async function save(e) {
    e.preventDefault();
    const body = {
      ...form,
      employee_id: Number(form.employee_id),
      basic_salary: Number(form.basic_salary),
      sales_linked_bonus: Number(form.sales_linked_bonus || 0),
    };
    try {
      if (editId) {
        await api(`/salaries/${editId}`, { method: 'PUT', body });
      } else {
        await api('/salaries', { method: 'POST', body });
      }
      setOpen(false);
      load();
    } catch (err) {
      setError(err.message);
    }
  }

  async function remove(id) {
    if (!window.confirm('Delete this salary record?')) return;
    try {
      await api(`/salaries/${id}`, { method: 'DELETE' });
      load();
    } catch (err) {
      setError(err.message);
    }
  }

  return (
    <>
      <div className="topbar">
        <h1>Salaries</h1>
        <button type="button" className="add-btn" onClick={openAdd}>
          + Add Salary
        </button>
      </div>
      {error && <p className="error-text">{error}</p>}
      {rows.length === 0 ? (
        <div className="empty-state">No salary records</div>
      ) : (
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Employee</th>
              <th>Role</th>
              <th>Month</th>
              <th>Basic</th>
              <th>Bonus</th>
              <th>Total</th>
              <th>Payment Date</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((r) => (
              <tr key={r.salary_id}>
                <td>{r.salary_id}</td>
                <td>{r.employee_name}</td>
                <td>{r.role}</td>
                <td>{r.month}</td>
                <td>{money(r.basic_salary)}</td>
                <td>{money(r.sales_linked_bonus)}</td>
                <td>{money(r.total_salary)}</td>
                <td>{r.payment_date}</td>
                <td>
                  <button type="button" className="edit" onClick={() => openEdit(r)}>
                    Edit
                  </button>
                  <button type="button" className="delete" onClick={() => remove(r.salary_id)}>
                    Delete
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}

      {open && (
        <Modal title={editId ? 'Edit Salary' : 'Add Salary'} onClose={() => setOpen(false)}>
          <form onSubmit={save}>
            <div className="form-group">
              <label>Employee</label>
              <select
                value={form.employee_id}
                onChange={(e) => setForm({ ...form, employee_id: e.target.value })}
                required
              >
                <option value="">Select</option>
                {users.map((u) => (
                  <option key={u.user_id} value={u.user_id}>
                    {u.name} ({u.role})
                  </option>
                ))}
              </select>
            </div>
            <div className="form-group">
              <label>Month</label>
              <input
                value={form.month}
                onChange={(e) => setForm({ ...form, month: e.target.value })}
                placeholder="August 2026"
                required
              />
            </div>
            <div className="form-group">
              <label>Basic Salary</label>
              <input
                type="number"
                min="0"
                step="0.01"
                value={form.basic_salary}
                onChange={(e) => setForm({ ...form, basic_salary: e.target.value })}
                required
              />
            </div>
            <div className="form-group">
              <label>Sales Bonus</label>
              <input
                type="number"
                min="0"
                step="0.01"
                value={form.sales_linked_bonus}
                onChange={(e) => setForm({ ...form, sales_linked_bonus: e.target.value })}
              />
            </div>
            <div className="form-group">
              <label>Payment Date</label>
              <input
                type="date"
                value={form.payment_date}
                onChange={(e) => setForm({ ...form, payment_date: e.target.value })}
                required
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
