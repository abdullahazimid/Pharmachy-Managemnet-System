import { useEffect, useMemo, useState } from 'react';
import { api } from '../api/client';
import Modal from '../components/Modal';
import FilterToolbar, { FilterSelect } from '../components/FilterToolbar';
import { matchSearch } from '../utils/tableFilter';

export default function Inventory() {
  const [rows, setRows] = useState([]);
  const [medicines, setMedicines] = useState([]);
  const [error, setError] = useState('');
  const [open, setOpen] = useState(false);
  const [form, setForm] = useState({ medicine_id: '', quantity_added: 0, quantity_sold: 0 });
  const [search, setSearch] = useState('');
  const [filterMedicine, setFilterMedicine] = useState('');

  const filteredRows = useMemo(
    () =>
      rows.filter((r) => {
        if (filterMedicine && String(r.medicine_id) !== filterMedicine) return false;
        return matchSearch(r, search, [
          'stock_id',
          'medicine_name',
          'updated_by_name',
          'date_updated',
        ]);
      }),
    [rows, search, filterMedicine],
  );

  async function load() {
    try {
      const [inv, meds] = await Promise.all([api('/inventory'), api('/medicines')]);
      setRows(inv.data);
      setMedicines(meds.data);
      setError('');
    } catch (err) {
      setError(err.message);
    }
  }

  useEffect(() => {
    load();
  }, []);

  async function save(e) {
    e.preventDefault();
    try {
      await api('/inventory/adjust', {
        method: 'POST',
        body: {
          medicine_id: Number(form.medicine_id),
          quantity_added: Number(form.quantity_added || 0),
          quantity_sold: Number(form.quantity_sold || 0),
        },
      });
      setOpen(false);
      setForm({ medicine_id: '', quantity_added: 0, quantity_sold: 0 });
      load();
    } catch (err) {
      setError(err.message);
    }
  }

  return (
    <>
      <div className="topbar">
        <h1>Inventory</h1>
        <button type="button" className="add-btn" onClick={() => setOpen(true)}>
          + Stock Adjustment
        </button>
      </div>
      {error && <p className="error-text">{error}</p>}
      <FilterToolbar
        search={search}
        onSearchChange={setSearch}
        searchPlaceholder="Search by medicine, updated by, date, ID..."
      >
        <FilterSelect
          value={filterMedicine}
          onChange={setFilterMedicine}
          allLabel="All Medicines"
          options={medicines.map((m) => ({
            value: String(m.medicine_id),
            label: m.medicine_name,
          }))}
        />
      </FilterToolbar>
      {rows.length === 0 ? (
        <div className="empty-state">No inventory logs yet</div>
      ) : filteredRows.length === 0 ? (
        <div className="empty-state">No records match your search or filters</div>
      ) : (
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Medicine</th>
              <th>Added</th>
              <th>Sold</th>
              <th>Updated By</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            {filteredRows.map((r) => (
              <tr key={r.stock_id}>
                <td>{r.stock_id}</td>
                <td>{r.medicine_name}</td>
                <td>{r.quantity_added}</td>
                <td>{r.quantity_sold}</td>
                <td>{r.updated_by_name || '-'}</td>
                <td>{r.date_updated}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}

      {open && (
        <Modal title="Stock Adjustment" onClose={() => setOpen(false)}>
          <form onSubmit={save}>
            <div className="form-group">
              <label>Medicine</label>
              <select
                value={form.medicine_id}
                onChange={(e) => setForm({ ...form, medicine_id: e.target.value })}
                required
              >
                <option value="">Select medicine</option>
                {medicines.map((m) => (
                  <option key={m.medicine_id} value={m.medicine_id}>
                    {m.medicine_name} (stock: {m.quantity_in_stock})
                  </option>
                ))}
              </select>
            </div>
            <div className="form-group">
              <label>Quantity Added</label>
              <input
                type="number"
                min="0"
                value={form.quantity_added}
                onChange={(e) => setForm({ ...form, quantity_added: e.target.value })}
              />
            </div>
            <div className="form-group">
              <label>Quantity Sold / Removed</label>
              <input
                type="number"
                min="0"
                value={form.quantity_sold}
                onChange={(e) => setForm({ ...form, quantity_sold: e.target.value })}
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
