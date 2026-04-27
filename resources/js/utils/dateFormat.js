function parseDateValue(value) {
  if (!value) return null;
  if (value instanceof Date) {
    return Number.isNaN(value.getTime()) ? null : value;
  }

  if (typeof value === 'string') {
    const trimmed = value.trim();
    if (!trimmed) return null;

    if (/^\d{4}-\d{2}-\d{2}$/.test(trimmed)) {
      const date = new Date(`${trimmed}T00:00:00`);
      return Number.isNaN(date.getTime()) ? null : date;
    }

    if (/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/.test(trimmed)) {
      const date = new Date(trimmed.replace(' ', 'T'));
      return Number.isNaN(date.getTime()) ? null : date;
    }
  }

  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? null : date;
}

function pad(number) {
  return String(number).padStart(2, '0');
}

export function formatDisplayDate(value, fallback = '—') {
  const date = parseDateValue(value);
  if (!date) return value ? String(value) : fallback;

  return `${pad(date.getDate())}/${pad(date.getMonth() + 1)}/${date.getFullYear()}`;
}

export function formatDisplayDateTime(value, fallback = '—') {
  const date = parseDateValue(value);
  if (!date) return value ? String(value) : fallback;

  return `${formatDisplayDate(date, fallback)} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

export function formatDisplayDateRange(value, fallback = '—') {
  if (!value) return fallback;
  const text = String(value).trim();
  const rangeMatch = text.match(/^(\d{4}-\d{2}-\d{2})\s*([\-–]|->)\s*(\d{4}-\d{2}-\d{2})$/);
  if (!rangeMatch) return text;

  const [, start, separator, end] = rangeMatch;
  return `${formatDisplayDate(start, fallback)} ${separator === '->' ? '->' : '-'} ${formatDisplayDate(end, fallback)}`;
}
