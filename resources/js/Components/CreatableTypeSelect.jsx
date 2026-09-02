import { useMemo, useState } from 'react';
import { router } from '@inertiajs/react';

/**
 * Creatable dropdown: pick an existing option, or type a new value and
 * explicitly confirm to save it as a new option (persisted server-side,
 * scoped to the current property, via POST /units/types).
 */
export default function CreatableTypeSelect({ value, options = [], onChange, onOptionsChange, error, disabled = false }) {
  const [query, setQuery] = useState('');
  const [open, setOpen] = useState(false);
  const [saving, setSaving] = useState(false);
  const [saveError, setSaveError] = useState('');

  const normalizedQuery = query.trim().toLowerCase();
  const filtered = useMemo(
    () => options.filter(o => !normalizedQuery || o.toLowerCase().includes(normalizedQuery)),
    [options, normalizedQuery],
  );
  const exactMatch = options.some(o => o.toLowerCase() === normalizedQuery);
  const canOfferCreate = query.trim().length > 0 && !exactMatch;

  const pick = (opt) => {
    onChange(opt);
    setQuery('');
    setSaveError('');
    setOpen(false);
  };

  const confirmCreate = () => {
    const newType = query.trim();
    if (!newType || saving) return;
    setSaving(true);
    setSaveError('');
    router.post('/units/types', { type: newType }, {
      preserveScroll: true,
      preserveState: true,
      onSuccess: (page) => {
        const saved = page.props.flash?.newUnitType || newType;
        onOptionsChange?.([...options, saved]);
        pick(saved);
      },
      onError: (errs) => setSaveError(errs.type || 'Could not save this type.'),
      onFinish: () => setSaving(false),
    });
  };

  return (
    <div style={{ position: 'relative' }}>
      <input
        className={`form-input form-select${error ? ' input-error' : ''}`}
        style={{ cursor: 'text' }}
        value={open ? query : value}
        placeholder="Select or type a unit type…"
        disabled={disabled}
        onFocus={() => { setQuery(''); setOpen(true); }}
        onBlur={() => setTimeout(() => setOpen(false), 150)}
        onClick={() => setOpen(true)}
        onChange={(e) => { setQuery(e.target.value); setOpen(true); }}
        onKeyDown={(e) => {
          if (e.key === 'Enter' && canOfferCreate) { e.preventDefault(); confirmCreate(); }
        }}
      />
      <div className={`nl-tenant-dropdown ${open ? 'open' : ''}`}>
        {filtered.length === 0 && !canOfferCreate && <div className="nl-no-results">No matching types</div>}
        {filtered.map(opt => (
          <button
            type="button"
            key={opt}
            className="nl-tenant-option"
            onMouseDown={(e) => e.preventDefault()}
            onClick={() => pick(opt)}
          >
            <div style={{ flex: 1, textAlign: 'left', fontSize: 13.5, color: 'var(--text-primary)' }}>{opt}</div>
          </button>
        ))}
        {canOfferCreate && (
          <button
            type="button"
            className="nl-tenant-option"
            onMouseDown={(e) => e.preventDefault()}
            onClick={confirmCreate}
            disabled={saving}
            style={{ color: 'var(--accent)', fontWeight: 600 }}
          >
            {saving ? 'Saving…' : `+ Add "${query.trim()}"`}
          </button>
        )}
      </div>
      {error && <div className="form-error">{error}</div>}
      {saveError && <div className="form-error">{saveError}</div>}
    </div>
  );
}
