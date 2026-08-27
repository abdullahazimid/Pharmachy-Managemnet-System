import { useEffect, useState } from 'react';
import { api } from '../api/client';
import Modal from '../components/Modal';
import { CategoryBadge, money } from '../components/Badges';

const empty = {
  medicine_name: '',
  company_name: '',
  category: 'General',
  unit_price: '',
  purchase_price: '',
  quantity_in_stock: 0,
  expiry_date: '',
  manufacture_date: '',
  supplier_id: '',
};

export default function Medicines() {
  const [rows, setRows] = useState([]);
  const [suppliers, setSuppliers] = useState([]);
  const [error, setError] = useState('');
  const [open, setOpen] = useState(false);
  const [editId, setEditId] = useState(null);
  const [form, setForm] = useState(empty);

  async function load() {
    try {
      const [m, s] = await Promise.all([api('/medicines'), api('/suppliers')]);
      setRows(m.data);
      setSuppliers(s.data);
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
    setEditId(row.medicine_id);
    setForm({
      medicine_name: row.medicine_name,
      company_name: row.company_name,
      category: row.category,
      unit_price: row.unit_price,
      purchase_price: row.purchase_price,
      quantity_in_stock: row.quantity_in_stock,
      expiry_date: row.expiry_date,
      manufacture_date: row.manufacture_date,
      supplier_id: row.supplier_id || '',
    });
    setOpen(true);
  }

  async function save(e) {
    e.preventDefault();
    const body = {
      ...form,
      unit_price: Number(form.unit_price),
      purchase_price: Number(form.purchase_price || 0),
      quantity_in_stock: Number(form.quantity_in_stock || 0),
      supplier_id: form.supplier_id ? Number(form.supplier_id) : null,
    };
    try {
      if (editId) {
        await api(`/medicines/${editId}`, { method: 'PUT', body });
      } else {
        await api('/medicines', { method: 'POST', body });
      }
      setOpen(false);
      load();
    } catch (err) {
      setError(err.message);
    }
  }

  async function remove(id) {
    if (!window.confirm('Delete this medicine?')) return;
    try {
      await api(`/medicines/${id}`, { method: 'DELETE' });
      load();
    } catch (err) {
      setError(err.message);
    }
  }

  return (
    <>
      <div className="topbar">
        <h1>Medicines</h1>
        <button type="button" className="add-btn" onClick={openAdd}>
          + Add Medicine
        </button>
      </div>
      {error && <p className="error-text">{error}</p>}
      {rows.length === 0 ? (
        <div className="empty-state">No medicines found</div>
      ) : (
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Name</th>
              <th>Company</th>
              <th>Category</th>
              <th>Sell</th>
              <th>Buy</th>
              <th>Stock</th>
              <th>Expiry</th>
              <th>Supplier</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((r) => (
              <tr key={r.medicine_id}>
                <td>{r.medicine_id}</td>
                <td>{r.medicine_name}</td>
                <td>{r.company_name}</td>
                <td>
                  <CategoryBadge category={r.category} />
                </td>
                <td>{money(r.unit_price)}</td>
                <td>{money(r.purchase_price)}</td>
                <td>{r.quantity_in_stock}</td>
                <td>{r.expiry_date}</td>
                <td>{r.supplier_name || '-'}</td>
                <td>
                  <button type="button" className="edit" onClick={() => openEdit(r)}>
                    Edit
                  </button>
                  <button type="button" className="delete" onClick={() => remove(r.medicine_id)}>
                    Delete
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}

      {open && (
        <Modal title={editId ? 'Edit Medicine' : 'Add Medicine'} onClose={() => setOpen(false)} wide>
          <form onSubmit={save}>
            <div className="form-group">
              <label>Medicine Name</label>
              <input
                value={form.medicine_name}
                onChange={(e) => setForm({ ...form, medicine_name: e.target.value })}
                required
              />
            </div>
            <div className="form-group">
              <label>Company</label>
              <input
                value={form.company_name}
                onChange={(e) => setForm({ ...form, company_name: e.target.value })}
                required
              />
            </div>
            <div className="form-group">
              <label>Category</label>
              <select
                value={form.category}
                onChange={(e) => setForm({ ...form, category: e.target.value })}
              >
                <option>General</option>
                <option>Antibiotic</option>
              </select>
            </div>
            <div className="form-group">
              <label>Unit Price (sell)</label>
              <input
                type="number"
                step="0.01"
                min="0"
                value={form.unit_price}
                onChange={(e) => setForm({ ...form, unit_price: e.target.value })}
                required
              />
            </div>
            <div className="form-group">
              <label>Purchase Price</label>
              <input
                type="number"
                step="0.01"
                min="0"
                value={form.purchase_price}
                onChange={(e) => setForm({ ...form, purchase_price: e.target.value })}
              />
            </div>
            <div className="form-group">
              <label>Quantity in Stock</label>
              <input
                type="number"
                min="0"
                value={form.quantity_in_stock}
                onChange={(e) => setForm({ ...form, quantity_in_stock: e.target.value })}
              />
            </div>
            <div className="form-group">
              <label>Manufacture Date</label>
              <input
                type="date"
                value={form.manufacture_date}
                onChange={(e) => setForm({ ...form, manufacture_date: e.target.value })}
                required
              />
            </div>
            <div className="form-group">
              <label>Expiry Date</label>
              <input
                type="date"
                value={form.expiry_date}
                onChange={(e) => setForm({ ...form, expiry_date: e.target.value })}
                required
              />
            </div>
            <div className="form-group">
              <label>Supplier</label>
              <select
                value={form.supplier_id}
                onChange={(e) => setForm({ ...form, supplier_id: e.target.value })}
              >
                <option value="">None</option>
                {suppliers.map((s) => (
                  <option key={s.supplier_id} value={s.supplier_id}>
                    {s.supplier_name}
                  </option>
                ))}
              </select>
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
