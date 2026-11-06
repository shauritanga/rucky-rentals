import { useState } from 'react';

/**
 * A dropdown you can search by typing. `options` is [{ value, label, hint? }]; `value` is the chosen option's value.
 * The text box shows the chosen label while closed and the search text while open.
 */
export default function SearchSelect({ options, value, onChange, placeholder = 'Search or select…', required = false, disabled = false, emptyText = 'Nothing found' }) {
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState('');

  const chosen = options.find((o) => String(o.value) === String(value));
  const q = query.trim().toLowerCase();
  const shown = q ? options.filter((o) => `${o.label} ${o.hint || ''}`.toLowerCase().includes(q)) : options;

  const pick = (option) => {
    onChange(option.value);
    setOpen(false);
    setQuery('');
  };

  return (
    <div style={{ position: 'relative' }}>
      <input
        type="text"
        className="form-input"
        autoComplete="off"
        disabled={disabled}
        required={required}
        placeholder={placeholder}
        value={open ? query : (chosen ? chosen.label : '')}
        onFocus={() => { setQuery(''); setOpen(true); }}
        onClick={() => setOpen(true)}
        onBlur={() => setTimeout(() => setOpen(false), 120)}
        onChange={(e) => { setQuery(e.target.value); setOpen(true); }}
      />
      <div className={`nl-tenant-dropdown ${open ? 'open' : ''}`}>
        {shown.length === 0 && <div className="nl-no-results">{emptyText}</div>}
        {shown.map((o) => (
          <button type="button" key={o.value} className="nl-tenant-option" onMouseDown={(e) => e.preventDefault()} onClick={() => pick(o)}>
            <div style={{ flex: 1, minWidth: 0, textAlign: 'left' }}>
              <div style={{ color: 'var(--text-primary)', fontSize: 13.5 }}>{o.label}</div>
              {o.hint && <div className="nl-opt-meta">{o.hint}</div>}
            </div>
          </button>
        ))}
      </div>
    </div>
  );
}
