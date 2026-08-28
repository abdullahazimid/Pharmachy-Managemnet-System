import { useEffect, useMemo, useState } from 'react';
import { api } from '../api/client';
import Modal from '../components/Modal';
import { useAuth } from '../context/AuthContext';
import FilterToolbar from '../components/FilterToolbar';
import { matchSearch } from '../utils/tableFilter';

const empty = { customer_name: '', contact_no: '', purchase_history: '' };

export default function Customers() {
  const { hasRole } = useAuth();
  const [rows, setRows] = useState([]);
  const [error, setError] = useState('');
  const [open, setOpen] = useState(false);
  const [editId, setEditId] = useState(null);
  const [form, setForm] = useState(empty);
  const [search, setSearch] = useState('');

  const filteredRows = useMemo(
    () =>
      rows.filter((r) =>
        matchSearch(r, search, ['customer_id', 'customer_name', 'contact_no', 'purchase_history']),
      ),
    [rows, search],
  );

  async function load() {
    try {
      const res = await api('/customers');
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
    setEditId(row.customer_id);
    setForm({
      customer_name: row.customer_name,
      contact_no: row.contact_no,
      purchase_history: row.purchase_history || '',
    });
    setOpen(true);
  }

  async function save(e) {
    e.preventDefault();
    try {
      if (editId) {
        await api(`/customers/${editId}`, { method: 'PUT', body: form });
      } else {
        await api('/customers', { method: 'POST', body: form });
      }
      setOpen(false);
      load();
    } catch (err) {
      setError(err.message);
    }
  }

  async function remove(id) {
    if (!window.confirm('Delete this customer?')) return;
    try {
      await api(`/customers/${id}`, { method: 'DELETE' });
      load();
    } catch (err) {
      setError(err.message);
    }
  }

  return (
    <>
      <div className="topbar">
        <h1>Customers</h1>
        <button type="button" className="add-btn" onClick={openAdd}>
          + Add Customer
        </button>
      </div>
      {error && <p className="error-text">{error}</p>}
      <FilterToolbar
        search={search}
        onSearchChange={setSearch}
        searchPlaceholder="Search by name, contact, ID..."
      />
      {rows.length === 0 ? (
        <div className="empty-state">No customers found</div>
      ) : filteredRows.length === 0 ? (
        <div className="empty-state">No customers match your search</div>
      ) : (
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Name</th>
              <th>Contact</th>
              <th>Purchase History</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            {filteredRows.map((r) => (
              <tr key={r.customer_id}>
                <td>{r.customer_id}</td>
                <td>{r.customer_name}</td>
                <td>{r.contact_no}</td>
                <td style={{ textAlign: 'left', maxWidth: 360 }}>{r.purchase_history || '-'}</td>
                <td>
                  <button type="button" className="edit" onClick={() => openEdit(r)}>
                    Edit
                  </button>
                  {hasRole('Admin', 'Pharmacist') && (
                    <button type="button" className="delete" onClick={() => remove(r.customer_id)}>
                      Delete
                    </button>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}

      {open && (
        <Modal title={editId ? 'Edit Customer' : 'Add Customer'} onClose={() => setOpen(false)}>
          <form onSubmit={save}>
            <div className="form-group">
              <label>Name</label>
              <input
                value={form.customer_name}
                onChange={(e) => setForm({ ...form, customer_name: e.target.value })}
                required
              />
            </div>
            <div className="form-group">
              <label>Contact</label>
              <input
                value={form.contact_no}
                onChange={(e) => setForm({ ...form, contact_no: e.target.value })}
                required
              />
            </div>
            <div className="form-group">
              <label>Purchase History</label>
              <textarea
                rows={3}
                value={form.purchase_history}
                onChange={(e) => setForm({ ...form, purchase_history: e.target.value })}
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
