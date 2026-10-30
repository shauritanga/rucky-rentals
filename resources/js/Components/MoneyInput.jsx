import { useEffect, useLayoutEffect, useRef, useState } from 'react';

// A text input that shows money with thousands separators (1,234,567.50) while keeping the
// plain number ("1234567.50") in form state. onChange receives an event-like object
// ({ target: { value, name } }) so existing `e => setX(e.target.value)` handlers keep working.

const group = (raw) => {
  if (raw === '') return '';
  const [int, dec] = raw.split('.');
  const withCommas = int.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  return dec !== undefined ? `${withCommas}.${dec}` : withCommas;
};

const clean = (text, decimals) => {
  let v = String(text).replace(/,/g, '').replace(/[^\d.]/g, '');
  const dot = v.indexOf('.');
  if (dot !== -1) {
    const dec = v.slice(dot + 1).replace(/\./g, '');
    v = decimals === 0 ? v.slice(0, dot) : `${v.slice(0, dot)}.${dec.slice(0, decimals)}`;
  }
  return v.replace(/^0+(?=\d)/, '');
};

export default function MoneyInput({ value, onChange, decimals = 2, step, min, max, type, inputMode, ...rest }) {
  const parent = value === null || value === undefined ? '' : String(value);
  const [text, setText] = useState(clean(parent, decimals));
  const ref = useRef(null);
  const caret = useRef(null);

  // Follow outside changes (resets, edits loaded from the server) but not our own typing,
  // so "1234." keeps its trailing dot while the parent stores the number 1234.
  useEffect(() => {
    if (Number(text || 0) !== Number(clean(parent, decimals) || 0)) setText(clean(parent, decimals));
  }, [parent]); // eslint-disable-line react-hooks/exhaustive-deps

  // Keep the caret next to the same digit after commas are inserted or removed.
  useLayoutEffect(() => {
    if (caret.current === null || !ref.current) return;
    let digits = caret.current;
    const shown = group(text);
    let pos = 0;
    while (pos < shown.length && digits > 0) {
      if (shown[pos] !== ',') digits -= 1;
      pos += 1;
    }
    ref.current.setSelectionRange(pos, pos);
    caret.current = null;
  });

  const handle = (e) => {
    const input = e.target;
    const before = input.value.slice(0, input.selectionStart ?? input.value.length);
    caret.current = before.replace(/[^\d.]/g, '').length;
    const next = clean(input.value, decimals);
    setText(next);
    onChange?.({ target: { value: next, name: rest.name } });
  };

  return <input {...rest} ref={ref} type="text" inputMode={inputMode || 'decimal'} autoComplete="off" value={group(text)} onChange={handle} />;
}
