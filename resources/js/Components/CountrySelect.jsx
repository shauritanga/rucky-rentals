import { useMemo, useState } from 'react';
import { flagOf } from '@/utils/phone';

// ISO 3166-1 alpha-2 codes; names come from the browser's own region names so no list has to be maintained.
const CODES = 'AF AL DZ AD AO AG AR AM AU AT AZ BS BH BD BB BY BE BZ BJ BT BO BA BW BR BN BG BF BI CV KH CM CA CF TD CL CN CO KM CG CD CR CI HR CU CY CZ DK DJ DM DO EC EG SV GQ ER EE SZ ET FJ FI FR GA GM GE DE GH GR GD GT GN GW GY HT HN HU IS IN ID IR IQ IE IL IT JM JP JO KZ KE KI KW KG LA LV LB LS LR LY LI LT LU MG MW MY MV ML MT MH MR MU MX FM MD MC MN ME MA MZ MM NA NR NP NL NZ NI NE NG KP MK NO OM PK PW PA PG PY PE PH PL PT QA RO RU RW KN LC VC WS SM ST SA SN RS SC SL SG SK SI SB SO ZA KR SS ES LK SD SR SE CH SY TW TJ TZ TH TL TG TO TT TN TR TM TV UG UA AE GB US UY UZ VU VA VE VN YE ZM ZW'.split(' ');

const buildCountries = () => {
  let names = null;
  try { names = new Intl.DisplayNames(['en'], { type: 'region' }); } catch { /* very old browser */ }
  const list = CODES.map((code) => ({ code, name: names?.of(code) || code }))
    .sort((a, b) => a.name.localeCompare(b.name));
  // Tanzania first: it is where almost every tenant is.
  return [...list.filter((c) => c.code === 'TZ'), ...list.filter((c) => c.code !== 'TZ')];
};

/**
 * Searchable country dropdown. The value is the country name as text (what the forms already store);
 * a value that is not in the list (older free-text entries) is still shown and kept until changed.
 */
export default function CountrySelect({ value, onChange, placeholder = 'Search or select country…', disabled = false }) {
  const countries = useMemo(buildCountries, []);
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState('');

  const q = query.trim().toLowerCase();
  const shown = q ? countries.filter((c) => c.name.toLowerCase().includes(q)) : countries;
  const current = countries.find((c) => c.name === value);

  const pick = (c) => {
    onChange(c.name);
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
        placeholder={placeholder}
        value={open ? query : (current ? `${flagOf(current.code)}  ${current.name}` : (value || ''))}
        onFocus={() => { setQuery(''); setOpen(true); }}
        onClick={() => setOpen(true)}
        onBlur={() => setTimeout(() => setOpen(false), 120)}
        onChange={(e) => { setQuery(e.target.value); setOpen(true); }}
      />
      <div className={`nl-tenant-dropdown ${open ? 'open' : ''}`}>
        {shown.length === 0 && <div className="nl-no-results">No country found</div>}
        {shown.map((c) => (
          <button type="button" key={c.code} className="nl-tenant-option" onMouseDown={(e) => e.preventDefault()} onClick={() => pick(c)}>
            <span style={{ fontSize: 16, lineHeight: 1 }}>{flagOf(c.code)}</span>
            <span style={{ flex: 1, textAlign: 'left', color: 'var(--text-primary)', fontSize: 13.5 }}>{c.name}</span>
          </button>
        ))}
      </div>
    </div>
  );
}
