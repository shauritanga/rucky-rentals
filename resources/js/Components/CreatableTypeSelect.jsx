import { useMemo, useState } from 'react';
import { router } from '@inertiajs/react';

export function ensureOtherAtEnd(list = []) {
  if (!Array.isArray(list)) return ['Other'];
  const withoutOther = [];
  let otherLabel = 'Other';

  for (const item of list) {
    if (!item || typeof item !== 'string') continue;
    const trimmed = item.trim();
    if (!trimmed) continue;
    if (trimmed.toLowerCase() === 'other') {
      otherLabel = trimmed;
    } else if (!withoutOther.some(existing => existing.toLowerCase() === trimmed.toLowerCase())) {
      withoutOther.push(trimmed);
    }
  }

  return [...withoutOther, otherLabel];
}

/**
 * Creatable dropdown: pick an existing option, or type a new value and
 * explicitly confirm to save it as a new option (persisted server-side,
 * scoped to the current property, via POST /units/types).
 * "Other" option is guaranteed to always remain at the end, even when
 * new dynamic types are created.
 */
export default function CreatableTypeSelect({ value, options = [], onChange, onOptionsChange, error, disabled = false }) {
  const [query, setQuery] = useState('');
  const [open, setOpen] = useState(false);
  const [saving, setSaving] = useState(false);
  const [saveError, setSaveError] = useState('');

  const orderedOptions = useMemo(() => ensureOtherAtEnd(options), [options]);
  const normalizedQuery = query.trim().toLowerCase();

  const filtered = useMemo(() => {
    const withoutOther = orderedOptions.filter(o => o.toLowerCase() !== 'other');
    const matchedWithoutOther = withoutOther.filter(o => !normalizedQuery || o.toLowerCase().includes(normalizedQuery));
    const matchesOther = !normalizedQuery || 'other'.includes(normalizedQuery);

    return matchesOther ? [...matchedWithoutOther, 'Other'] : matchedWithoutOther;
  }, [orderedOptions, normalizedQuery]);

  const exactMatch = orderedOptions.some(o => o.toLowerCase() === normalizedQuery);
  const canOfferCreate = query.trim().length > 0 && !exactMatch && normalizedQuery !== 'other';

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
        const nextOptions = ensureOtherAtEnd([...orderedOptions.filter(o => o.toLowerCase() !== 'other'), saved]);
        onOptionsChange?.(nextOptions);
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
