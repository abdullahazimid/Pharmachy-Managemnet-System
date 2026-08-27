import { useEffect, useState } from 'react';
import { api } from '../api/client';
import Modal from '../components/Modal';
import { AlertBadge } from '../components/Badges';

export default function Antibiotics() {
  const [rows, setRows] = useState([]);
  const [medicines, setMedicines] = useState([]);
  const [error, setError] = useState('');
  const [open, setOpen] = useState(false);
  const [editId, setEditId] = useState(null);
  const [form, setForm] = useState({ medicine_id: '', allowed_range_limit: 10, alert_status: 'Normal' });

  async function load() {
    try {
      const [a, m] = await Promise.all([api('/antibiotics'), api('/medicines')]);
      setRows(a.data);
      setMedicines(m.data.filter((x) => x.category === 'Antibiotic'));
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
    setForm({ medicine_id: '', allowed_range_limit: 10, alert_status: 'Normal' });
    setOpen(true);
  }

  function openEdit(row) {
    setEditId(row.antibiotic_id);
    setForm({
      medicine_id: row.medicine_id,
      allowed_range_limit: row.allowed_range_limit,
      alert_status: row.alert_status,
    });
    setOpen(true);
  }

  async function save(e) {
    e.preventDefault();
    try {
      if (editId) {
        await api(`/antibiotics/${editId}`, {
          method: 'PUT',
          body: {
            allowed_range_limit: Number(form.allowed_range_limit),
            alert_status: form.alert_status,
          },
        });
      } else {
        await api('/antibiotics', {
          method: 'POST',
          body: {
            medicine_id: Number(form.medicine_id),
            allowed_range_limit: Number(form.allowed_range_limit),
          },
        });
      }
      setOpen(false);
      load();
    } catch (err) {
      setError(err.message);
    }
  }

  async function remove(id) {
    if (!window.confirm('Remove this antibiotic restriction?')) return;
    try {
      await api(`/antibiotics/${id}`, { method: 'DELETE' });
      load();
    } catch (err) {
      setError(err.message);
    }
  }

  return (
    <>
      <div className="topbar">
        <h1>Antibiotics (Restricted)</h1>
        <button type="button" className="add-btn" onClick={openAdd}>
          + Add Limit
        </button>
      </div>
      {error && <p className="error-text">{error}</p>}
      {rows.length === 0 ? (
        <div className="empty-state">No antibiotic limits configured</div>
      ) : (
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Medicine</th>
              <th>Company</th>
              <th>Sale Limit</th>
              <th>Stock</th>
              <th>Alert</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((r) => (
              <tr key={r.antibiotic_id}>
                <td>{r.antibiotic_id}</td>
                <td>{r.medicine_name}</td>
                <td>{r.company_name}</td>
                <td>{r.allowed_range_limit}</td>
                <td>{r.current_stock_level}</td>
                <td>
                  <AlertBadge status={r.alert_status} />
                </td>
                <td>
                  <button type="button" className="edit" onClick={() => openEdit(r)}>
                    Edit
                  </button>
                  <button type="button" className="delete" onClick={() => remove(r.antibiotic_id)}>
                    Delete
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}

      {open && (
        <Modal title={editId ? 'Edit Antibiotic Limit' : 'Add Antibiotic Limit'} onClose={() => setOpen(false)}>
          <form onSubmit={save}>
            {!editId && (
              <div className="form-group">
                <label>Medicine</label>
                <select
                  value={form.medicine_id}
                  onChange={(e) => setForm({ ...form, medicine_id: e.target.value })}
                  required
                >
                  <option value="">Select antibiotic</option>
                  {medicines.map((m) => (
                    <option key={m.medicine_id} value={m.medicine_id}>
                      {m.medicine_name}
                    </option>
                  ))}
                </select>
              </div>
            )}
            <div className="form-group">
              <label>Allowed Range Limit (per sale)</label>
              <input
                type="number"
                min="1"
                value={form.allowed_range_limit}
                onChange={(e) => setForm({ ...form, allowed_range_limit: e.target.value })}
                required
              />
            </div>
            {editId && (
              <div className="form-group">
                <label>Alert Status</label>
                <select
                  value={form.alert_status}
                  onChange={(e) => setForm({ ...form, alert_status: e.target.value })}
                >
                  <option>Normal</option>
                  <option>Warning</option>
                  <option>Critical</option>
                </select>
              </div>
            )}
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
