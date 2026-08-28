export default function FilterToolbar({ search, onSearchChange, searchPlaceholder, children }) {
  return (
    <div className="filter-toolbar">
      <input
        type="text"
        className="search-input"
        placeholder={searchPlaceholder}
        value={search}
        onChange={(e) => onSearchChange(e.target.value)}
      />
      {children}
    </div>
  );
}

export function FilterSelect({ value, onChange, allLabel, options }) {
  return (
    <select value={value} onChange={(e) => onChange(e.target.value)}>
      <option value="">{allLabel}</option>
      {options.map((opt) => (
        <option key={opt.value} value={opt.value}>
          {opt.label}
        </option>
      ))}
    </select>
  );
}
