// Country dial codes offered in the phone picker. Tanzania first: it is the default.
export const COUNTRIES = [
  { code: 'TZ', name: 'Tanzania', dial: '255', max: 9 },
  { code: 'KE', name: 'Kenya', dial: '254', max: 9 },
  { code: 'UG', name: 'Uganda', dial: '256', max: 9 },
  { code: 'RW', name: 'Rwanda', dial: '250', max: 9 },
  { code: 'BI', name: 'Burundi', dial: '257', max: 8 },
  { code: 'CD', name: 'DR Congo', dial: '243', max: 9 },
  { code: 'ZM', name: 'Zambia', dial: '260', max: 9 },
  { code: 'MW', name: 'Malawi', dial: '265', max: 9 },
  { code: 'MZ', name: 'Mozambique', dial: '258', max: 9 },
  { code: 'ZW', name: 'Zimbabwe', dial: '263', max: 9 },
  { code: 'ZA', name: 'South Africa', dial: '27', max: 9 },
  { code: 'NG', name: 'Nigeria', dial: '234', max: 10 },
  { code: 'ET', name: 'Ethiopia', dial: '251', max: 9 },
  { code: 'EG', name: 'Egypt', dial: '20', max: 10 },
  { code: 'GB', name: 'United Kingdom', dial: '44', max: 10 },
  { code: 'US', name: 'United States / Canada', dial: '1', max: 10 },
  { code: 'IN', name: 'India', dial: '91', max: 10 },
  { code: 'CN', name: 'China', dial: '86', max: 11 },
  { code: 'AE', name: 'United Arab Emirates', dial: '971', max: 9 },
  { code: 'SA', name: 'Saudi Arabia', dial: '966', max: 9 },
  { code: 'DE', name: 'Germany', dial: '49', max: 11 },
  { code: 'FR', name: 'France', dial: '33', max: 9 },
  { code: 'IT', name: 'Italy', dial: '39', max: 10 },
  { code: 'NL', name: 'Netherlands', dial: '31', max: 9 },
  { code: 'KR', name: 'South Korea', dial: '82', max: 10 },
  { code: 'JP', name: 'Japan', dial: '81', max: 10 },
];

export const DEFAULT_DIAL = '255';

export const flagOf = (code) => String.fromCodePoint(...[...code].map((c) => 0x1f1a5 + c.charCodeAt(0)));

const byDialDescending = [...COUNTRIES].sort((a, b) => b.dial.length - a.dial.length);

export const countryByDial = (dial) => COUNTRIES.find((c) => c.dial === dial) ?? COUNTRIES[0];

/** Split any stored phone ("+255712345678", "255712345678", "0712 345 678") into dial code and national digits. */
export function parsePhone(value) {
  const raw = String(value ?? '').trim();
  const digits = raw.replace(/\D/g, '');
  if (!digits) return { dial: DEFAULT_DIAL, national: '' };

  if (raw.startsWith('+') || raw.startsWith('00')) {
    const full = raw.startsWith('00') ? digits.slice(2) : digits;
    const match = byDialDescending.find((c) => full.startsWith(c.dial));
    if (match) return { dial: match.dial, national: full.slice(match.dial.length) };
  }
  if (digits.startsWith('0')) return { dial: DEFAULT_DIAL, national: digits.replace(/^0+/, '') };

  const match = byDialDescending.find((c) => digits.startsWith(c.dial) && digits.length - c.dial.length >= c.max - 1);
  if (match) return { dial: match.dial, national: digits.slice(match.dial.length) };

  return { dial: DEFAULT_DIAL, national: digits };
}

/** Groups of three, e.g. 712345678 -> "712 345 678". */
export function groupDigits(digits) {
  return String(digits).replace(/(\d{3})(?=\d)/g, '$1 ').trim();
}

export const toE164 = (dial, national) => (national ? `+${dial}${national}` : '');

/** "+255 712 345 678" for display; anything that is not a number is returned unchanged. */
export function formatPhone(value) {
  if (!value || !/\d/.test(String(value))) return value || '';
  const { dial, national } = parsePhone(value);
  return national ? `+${dial} ${groupDigits(national)}` : '';
}
