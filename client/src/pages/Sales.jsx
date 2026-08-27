import { useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { api } from '../api/client';
import Modal from '../components/Modal';
import { money } from '../components/Badges';

export default function Sales() {
  const navigate = useNavigate();
  const [sales, setSales] = useState([]);
  const [medicines, setMedicines] = useState([]);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [open, setOpen] = useState(false);
  const [form, setForm] = useState({
    customer_name: '',
    customer_contact: '',
    payment_method: 'Cash',
    discount_percentage: 0,
    medicine_id: '',
    quantity: 1,
  });
  const [cart, setCart] = useState([]);

  async function load() {
    try {
      const [s, m] = await Promise.all([api('/sales'), api('/medicines/list-for-sale')]);
      setSales(s.data);
      setMedicines(m.data);
      setError('');
    } catch (err) {
      setError(err.message);
    }
  }

  useEffect(() => {
    load();
  }, []);

  const cartTotal = useMemo(() => {
    const sub = cart.reduce((sum, i) => sum + i.unit_price * i.quantity, 0);
    const disc = (sub * Number(form.discount_percentage || 0)) / 100;
    return { sub, disc, total: sub - disc };
  }, [cart, form.discount_percentage]);

  function addToCart() {
    const med = medicines.find((m) => String(m.medicine_id) === String(form.medicine_id));
    const qty = Number(form.quantity);
    if (!med || !qty || qty <= 0) {
      setError('Select medicine and quantity');
      return;
    }
    if (qty > med.quantity_in_stock) {
      setError(`Only ${med.quantity_in_stock} in stock`);
      return;
    }
    setError('');
    setCart((prev) => {
      const existing = prev.find((p) => p.medicine_id === med.medicine_id);
      if (existing) {
        return prev.map((p) =>
          p.medicine_id === med.medicine_id
            ? { ...p, quantity: p.quantity + qty }
            : p
        );
      }
      return [
        ...prev,
        {
          medicine_id: med.medicine_id,
          name: med.medicine_name,
          unit_price: Number(med.unit_price),
          quantity: qty,
          category: med.category,
        },
      ];
    });
    setForm((f) => ({ ...f, medicine_id: '', quantity: 1 }));
  }

  function removeFromCart(id) {
    setCart((prev) => prev.filter((p) => p.medicine_id !== id));
  }

  async function checkout(e) {
    e.preventDefault();
    if (cart.length === 0) {
      setError('Add at least one medicine');
      return;
    }
    try {
      const res = await api('/sales', {
        method: 'POST',
        body: {
          customer_name: form.customer_name,
          customer_contact: form.customer_contact,
          payment_method: form.payment_method,
          discount_percentage: Number(form.discount_percentage || 0),
          items: cart.map((c) => ({ medicine_id: c.medicine_id, quantity: c.quantity })),
        },
      });
      setSuccess(`Invoice #${res.data.invoice_id} created — Total ${money(res.data.total_amount)}`);
      setOpen(false);
      setCart([]);
      setForm({
        customer_name: '',
        customer_contact: '',
        payment_method: 'Cash',
        discount_percentage: 0,
        medicine_id: '',
        quantity: 1,
      });
      load();
      navigate(`/invoices?highlight=${res.data.invoice_id}`);
    } catch (err) {
      setError(err.message);
    }
  }

  return (
    <>
      <div className="topbar">
        <h1>Sales & Billing</h1>
        <button
          type="button"
          className="add-btn"
          onClick={() => {
            setSuccess('');
            setError('');
            setOpen(true);
          }}
        >
          + New Bill
        </button>
      </div>
      {error && <p className="error-text">{error}</p>}
      {success && <p className="success-text">{success}</p>}
      {sales.length === 0 ? (
        <div className="empty-state">No sales yet</div>
      ) : (
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Medicine</th>
              <th>Qty</th>
              <th>Total</th>
              <th>Discount %</th>
              <th>Payment</th>
              <th>Sold By</th>
              <th>Invoice</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            {sales.map((r) => (
              <tr key={r.sales_id}>
                <td>{r.sales_id}</td>
                <td>{r.medicine_name}</td>
                <td>{r.quantity_sold}</td>
                <td>{money(r.total_price)}</td>
                <td>{r.discount_percentage}%</td>
                <td>
                  <span className="badge badge-cash">{r.payment_method}</span>
                </td>
                <td>{r.sold_by_name || '-'}</td>
                <td>{r.invoice_id || '-'}</td>
                <td>{r.sale_date}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}

      {open && (
        <Modal title="Create Bill" onClose={() => setOpen(false)} wide>
          <form onSubmit={checkout}>
            <div className="form-group">
              <label>Customer Name</label>
              <input
                value={form.customer_name}
                onChange={(e) => setForm({ ...form, customer_name: e.target.value })}
                required
              />
            </div>
            <div className="form-group">
              <label>Contact</label>
              <input
                value={form.customer_contact}
                onChange={(e) => setForm({ ...form, customer_contact: e.target.value })}
              />
            </div>
            <div className="form-group">
              <label>Payment Method</label>
              <select
                value={form.payment_method}
                onChange={(e) => setForm({ ...form, payment_method: e.target.value })}
              >
                <option>Cash</option>
                <option>Card</option>
                <option>bKash</option>
              </select>
            </div>
            <div className="form-group">
              <label>Discount %</label>
              <input
                type="number"
                min="0"
                max="100"
                step="0.01"
                value={form.discount_percentage}
                onChange={(e) => setForm({ ...form, discount_percentage: e.target.value })}
              />
            </div>

            <div className="form-group">
              <label>Add Medicine</label>
              <select
                value={form.medicine_id}
                onChange={(e) => setForm({ ...form, medicine_id: e.target.value })}
              >
                <option value="">Select</option>
                {medicines.map((m) => (
                  <option key={m.medicine_id} value={m.medicine_id}>
                    {m.medicine_name} — {money(m.unit_price)} (stock {m.quantity_in_stock})
                  </option>
                ))}
              </select>
            </div>
            <div className="form-group">
              <label>Quantity</label>
              <input
                type="number"
                min="1"
                value={form.quantity}
                onChange={(e) => setForm({ ...form, quantity: e.target.value })}
              />
            </div>
            <button type="button" className="btn-primary" onClick={addToCart} style={{ marginBottom: 12 }}>
              Add to Cart
            </button>

            <div className="cart-list">
              {cart.length === 0 && <p>Cart is empty</p>}
              {cart.map((c) => (
                <div className="cart-row" key={c.medicine_id}>
                  <span>
                    {c.name} x {c.quantity} = {money(c.unit_price * c.quantity)}
                  </span>
                  <button type="button" className="delete" onClick={() => removeFromCart(c.medicine_id)}>
                    Remove
                  </button>
                </div>
              ))}
              {cart.length > 0 && (
                <div className="cart-row">
                  <strong>
                    Subtotal {money(cartTotal.sub)} − Discount {money(cartTotal.disc)} ={' '}
                    {money(cartTotal.total)}
                  </strong>
                </div>
              )}
            </div>

            {error && <p className="error-text">{error}</p>}
            <div className="form-actions">
              <button type="button" className="cancel-btn" onClick={() => setOpen(false)}>
                Cancel
              </button>
              <button type="submit" className="save-btn">
                Complete Sale
              </button>
            </div>
          </form>
        </Modal>
      )}
    </>
  );
}
