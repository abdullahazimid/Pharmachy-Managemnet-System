import { useEffect, useState } from 'react';
import { api } from '../api/client';
import Modal from '../components/Modal';

const empty = { supplier_name: '', contact_no: '', address: '', company_name: '' };

export default function Suppliers() {
  const [rows, setRows] = useState([]);
  const [error, setError] = useState('');
  const [open, setOpen] = useState(false);
  const [editId, setEditId] = useState(null);
  const [form, setForm] = useState(empty);

  async function load() {
    try {
      const res = await api('/suppliers');
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
    setEditId(row.supplier_id);
    setForm({
      supplier_name: row.supplier_name,
      contact_no: row.contact_no,
      address: row.address,
      company_name: row.company_name,
    });
    setOpen(true);
  }

  async function save(e) {
    e.preventDefault();
    try {
      if (editId) {
        await api(`/suppliers/${editId}`, { method: 'PUT', body: form });
      } else {
        await api('/suppliers', { method: 'POST', body: form });
      }
      setOpen(false);
      load();
    } catch (err) {
      setError(err.message);
    }
  }

  async function remove(id) {
    if (!window.confirm('Delete this supplier?')) return;
    try {
      await api(`/suppliers/${id}`, { method: 'DELETE' });
      load();
    } catch (err) {
      setError(err.message);
    }
  }

  return (
    <>
      <div className="topbar">
        <h1>Suppliers</h1>
        <button type="button" className="add-btn" onClick={openAdd}>
          + Add Supplier
        </button>
      </div>
      {error && <p className="error-text">{error}</p>}
      {rows.length === 0 ? (
        <div className="empty-state">No suppliers found</div>
      ) : (
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Supplier</th>
              <th>Company</th>
              <th>Contact</th>
              <th>Address</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((r) => (
              <tr key={r.supplier_id}>
                <td>{r.supplier_id}</td>
                <td>{r.supplier_name}</td>
                <td>{r.company_name}</td>
                <td>{r.contact_no}</td>
                <td>{r.address}</td>
                <td>
                  <button type="button" className="edit" onClick={() => openEdit(r)}>
                    Edit
                  </button>
                  <button type="button" className="delete" onClick={() => remove(r.supplier_id)}>
                    Delete
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}

      {open && (
        <Modal title={editId ? 'Edit Supplier' : 'Add Supplier'} onClose={() => setOpen(false)}>
          <form onSubmit={save}>
            {['supplier_name', 'company_name', 'contact_no'].map((key) => (
              <div className="form-group" key={key}>
                <label>{key.replace('_', ' ')}</label>
                <input
                  value={form[key]}
                  onChange={(e) => setForm({ ...form, [key]: e.target.value })}
                  required
                />
              </div>
            ))}
            <div className="form-group">
              <label>Address</label>
              <textarea
                rows={3}
                value={form.address}
                onChange={(e) => setForm({ ...form, address: e.target.value })}
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
