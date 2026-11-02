import { useEffect, useMemo, useRef, useState } from 'react';
import { COUNTRIES, countryByDial, flagOf, groupDigits, parsePhone, toE164 } from '@/utils/phone';

/**
 * Phone field with a country-code picker. Value in and out is one string: "+255712345678".
 * The number is shown grouped ("712 345 678") and a leading 0 is dropped.
 */
export default function PhoneInput({ value, onChange, required = false, disabled = false, id, placeholder = '712 345 678' }) {
  const parsed = useMemo(() => parsePhone(value), [value]);
  const [dial, setDial] = useState(parsed.dial);
  const [national, setNational] = useState(parsed.national);
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState('');
  const wrapRef = useRef(null);

  // Follow outside changes (form reset, editing another record).
  useEffect(() => {
    if (toE164(dial, national) !== (value || '')) {
      setDial(parsed.dial);
      setNational(parsed.national);
    }
  }, [value]); // eslint-disable-line react-hooks/exhaustive-deps

  useEffect(() => {
    if (!open) return undefined;
    const onDown = (e) => { if (!wrapRef.current?.contains(e.target)) setOpen(false); };
    const onKey = (e) => e.key === 'Escape' && setOpen(false);
    document.addEventListener('mousedown', onDown);
    document.addEventListener('keydown', onKey);
    return () => { document.removeEventListener('mousedown', onDown); document.removeEventListener('keydown', onKey); };
  }, [open]);

  const country = countryByDial(dial);
  const emit = (nextDial, nextNational) => onChange(toE164(nextDial, nextNational));

  const typeNumber = (e) => {
    let digits = e.target.value.replace(/\D/g, '').replace(/^0+/, '');
    digits = digits.slice(0, country.max);
    setNational(digits);
    emit(dial, digits);
  };

  const pickCountry = (c) => {
    const digits = national.slice(0, c.max);
    setDial(c.dial);
    setNational(digits);
    setOpen(false);
    setQuery('');
    emit(c.dial, digits);
  };

  const q = query.trim().toLowerCase();
  const list = q ? COUNTRIES.filter((c) => c.name.toLowerCase().includes(q) || c.dial.includes(q.replace('+', ''))) : COUNTRIES;

  return (
    <div className="phone-input" ref={wrapRef}>
      <button type="button" className="phone-input-code" disabled={disabled} onClick={() => setOpen((o) => !o)} aria-haspopup="listbox" aria-expanded={open} title={country.name}>
        <span className="phone-input-flag">{flagOf(country.code)}</span>
        <span>+{country.dial}</span>
        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3" aria-hidden="true"><polyline points="6 9 12 15 18 9" /></svg>
      </button>
      <input
        id={id}
        className="form-input phone-input-number"
        type="tel"
        inputMode="numeric"
        autoComplete="tel-national"
        value={groupDigits(national)}
        onChange={typeNumber}
        placeholder={placeholder}
        required={required}
        disabled={disabled}
      />
      {open && (
        <div className="phone-input-menu" role="listbox">
          <input className="form-input" autoFocus placeholder="Search country" value={query} onChange={(e) => setQuery(e.target.value)} />
          <div className="phone-input-list">
            {list.map((c) => (
              <button type="button" role="option" aria-selected={c.dial === dial} key={c.code} className={`phone-input-option ${c.dial === dial ? 'active' : ''}`} onClick={() => pickCountry(c)}>
                <span className="phone-input-flag">{flagOf(c.code)}</span>
                <span style={{ flex: 1 }}>{c.name}</span>
                <span style={{ color: 'var(--text-muted)' }}>+{c.dial}</span>
              </button>
            ))}
            {list.length === 0 && <div style={{ padding: 10, fontSize: 12.5, color: 'var(--text-muted)' }}>No country found</div>}
          </div>
        </div>
      )}
    </div>
  );
}
