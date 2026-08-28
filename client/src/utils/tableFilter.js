export function matchSearch(row, query, fields) {
  const q = query.trim().toLowerCase();
  if (!q) return true;
  return fields.some((field) => {
    const value = typeof field === 'function' ? field(row) : row[field];
    return String(value ?? '').toLowerCase().includes(q);
  });
}

export function uniqueValues(rows, field) {
  return [...new Set(rows.map((r) => r[field]).filter(Boolean))].sort();
}

export function uniqueOptions(rows, valueField, labelField = valueField) {
  return uniqueValues(rows, valueField).map((val) => ({
    value: String(val),
    label: String(rows.find((r) => r[valueField] === val)?.[labelField] ?? val),
  }));
}
