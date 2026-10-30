// Identity document and TIN formats. Keep in sync with app/Support/IdFormats.php.
// The value kept in form state is the normalised one (no separators); `format` is for display.

const digits = (v) => String(v ?? '').replace(/\D/g, '');
const group = (v, sizes) => {
  const out = [];
  let i = 0;
  for (const size of sizes) {
    if (i >= v.length) break;
    out.push(v.slice(i, i + size));
    i += size;
  }
  return out.join('-');
};

export const ID_TYPES = {
  national: {
    label: 'National ID (NIDA)', maxLength: 20, placeholder: '12345678-12345-12345-12', hint: '20 digits',
    normalize: (v) => digits(v).slice(0, 20),
    format: (v) => group(digits(v).slice(0, 20), [8, 5, 5, 2]),
    valid: (v) => /^\d{20}$/.test(v),
  },
  voter: {
    label: 'Voter ID', maxLength: 12, placeholder: '1234-5678-9012', hint: '12 digits',
    normalize: (v) => digits(v).slice(0, 12),
    format: (v) => group(digits(v).slice(0, 12), [4, 4, 4]),
    valid: (v) => /^\d{12}$/.test(v),
  },
  passport: {
    label: 'Passport', maxLength: 9, placeholder: 'AB1234567', hint: '6 to 9 letters or digits (local or foreign)',
    normalize: (v) => String(v ?? '').replace(/[^A-Za-z0-9]/g, '').toUpperCase().slice(0, 9),
    format: (v) => String(v ?? '').replace(/[^A-Za-z0-9]/g, '').toUpperCase().slice(0, 9),
    valid: (v) => /^[A-Z0-9]{6,9}$/.test(v),
  },
  driving_license: {
    label: 'Driving Licence', maxLength: 10, placeholder: '1234567890', hint: '10 digits',
    normalize: (v) => digits(v).slice(0, 10),
    format: (v) => digits(v).slice(0, 10),
    valid: (v) => /^\d{10}$/.test(v),
  },
  other: {
    label: 'Other (legacy)', maxLength: 100, placeholder: '', hint: '',
    normalize: (v) => String(v ?? '').slice(0, 100),
    format: (v) => String(v ?? ''),
    valid: () => true,
  },
};

export const ID_TYPE_OPTIONS = ['national', 'voter', 'passport', 'driving_license'];

export const idLabel = (type) => ID_TYPES[type]?.label || 'ID';
export const formatId = (type, value) => (value ? (ID_TYPES[type]?.format(value) ?? String(value)) : '');

export const TIN = {
  placeholder: '123-456-789',
  normalize: (v) => digits(v).slice(0, 9),
  format: (v) => group(digits(v).slice(0, 9), [3, 3, 3]),
  valid: (v) => /^\d{9}$/.test(v),
};
export const formatTin = (value) => (value ? (/^\d+$/.test(String(value)) ? TIN.format(value) : String(value)) : '');
