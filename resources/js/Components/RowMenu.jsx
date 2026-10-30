import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

/**
 * A "⋯" button that opens a small popup menu of row actions.
 * items: [{ label, onClick, danger?, icon? }]
 */
export default function RowMenu({ items, label = 'Actions' }) {
  const [pos, setPos] = useState(null);
  const btnRef = useRef(null);
  const menuRef = useRef(null);

  const close = () => setPos(null);

  const toggle = (e) => {
    e.stopPropagation();
    if (pos) return close();
    const r = btnRef.current.getBoundingClientRect();
    const menuHeight = items.length * 38 + 12;
    const below = r.bottom + 6 + menuHeight <= window.innerHeight;
    setPos({ right: Math.max(8, window.innerWidth - r.right), top: below ? r.bottom + 6 : Math.max(8, r.top - menuHeight - 6) });
  };

  useEffect(() => {
    if (!pos) return undefined;
    const onDown = (e) => {
      if (menuRef.current?.contains(e.target) || btnRef.current?.contains(e.target)) return;
      close();
    };
    const onKey = (e) => e.key === 'Escape' && close();
    document.addEventListener('mousedown', onDown);
    document.addEventListener('keydown', onKey);
    window.addEventListener('scroll', close, true);
    window.addEventListener('resize', close);
    return () => {
      document.removeEventListener('mousedown', onDown);
      document.removeEventListener('keydown', onKey);
      window.removeEventListener('scroll', close, true);
      window.removeEventListener('resize', close);
    };
  }, [pos]);

  return (
    <>
      <button type="button" ref={btnRef} className="row-menu-btn" aria-label={label} aria-haspopup="menu" aria-expanded={!!pos} onClick={toggle}>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
          <circle cx="5" cy="12" r="1.8" /><circle cx="12" cy="12" r="1.8" /><circle cx="19" cy="12" r="1.8" />
        </svg>
      </button>
      {pos && createPortal(
        <div ref={menuRef} className="row-menu" role="menu" style={{ top: pos.top, right: pos.right }}>
          {items.map((item) => (
            <button
              key={item.label}
              type="button"
              role="menuitem"
              className={`row-menu-item ${item.danger ? 'danger' : ''}`}
              onClick={(e) => { e.stopPropagation(); close(); item.onClick(); }}
            >
              {item.icon}
              {item.label}
            </button>
          ))}
        </div>,
        document.body
      )}
    </>
  );
}
