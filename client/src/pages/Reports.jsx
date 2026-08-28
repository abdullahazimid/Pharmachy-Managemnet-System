import { useEffect, useMemo, useState } from 'react';
import { api } from '../api/client';
import { money } from '../components/Badges';
import FilterToolbar from '../components/FilterToolbar';
import { matchSearch } from '../utils/tableFilter';

export default function Reports() {
  const [type, setType] = useState('Daily');
  const [rows, setRows] = useState([]);
  const [error, setError] = useState('');
  const [search, setSearch] = useState('');

  const filteredRows = useMemo(
    () =>
      rows.filter((r) =>
        matchSearch(r, search, ['report_type', 'report_date']),
      ),
    [rows, search],
  );

  async function load(reportType = type) {
    try {
      const res = await api(`/reports/summary?type=${reportType}`);
      setRows(res.data);
      setError('');
    } catch (err) {
      setError(err.message);
    }
  }

  useEffect(() => {
    load(type);
  }, [type]);

  return (
    <>
      <div className="topbar">
        <h1>Sales & Profit Reports</h1>
        <button type="button" className="add-btn no-print" onClick={() => window.print()}>
          Print
        </button>
      </div>

      <div className="tabs no-print">
        <button
          type="button"
          className={`tab-btn ${type === 'Daily' ? 'active' : ''}`}
          onClick={() => setType('Daily')}
        >
          Daily
        </button>
        <button
          type="button"
          className={`tab-btn ${type === 'Monthly' ? 'active' : ''}`}
          onClick={() => setType('Monthly')}
        >
          Monthly
        </button>
      </div>

      {error && <p className="error-text">{error}</p>}
      <FilterToolbar
        search={search}
        onSearchChange={setSearch}
        searchPlaceholder="Search by date..."
      />
      {rows.length === 0 ? (
        <div className="empty-state">No report data yet — create some sales first</div>
      ) : filteredRows.length === 0 ? (
        <div className="empty-state">No reports match your search</div>
      ) : (
        <table>
          <thead>
            <tr>
              <th>Type</th>
              <th>Date</th>
              <th>Total Sales</th>
              <th>Purchase Cost</th>
              <th>Profit / Loss</th>
            </tr>
          </thead>
          <tbody>
            {filteredRows.map((r, i) => (
              <tr key={`${r.report_date}-${i}`}>
                <td>
                  <span className={`badge ${r.report_type === 'Daily' ? 'badge-daily' : 'badge-monthly'}`}>
                    {r.report_type}
                  </span>
                </td>
                <td>{r.report_date}</td>
                <td>{money(r.total_sales_amount)}</td>
                <td>{money(r.total_purchase_amount)}</td>
                <td>{money(r.profit_loss)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </>
  );
}
