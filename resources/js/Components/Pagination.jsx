import { useEffect, useState } from 'react';

export const PAGE_SIZE = 10;

/** Slice a list into pages of 10. The page resets to 1 when `resetKey` changes (filters, search, tab). */
export function usePagination(items, resetKey = '', perPage = PAGE_SIZE) {
  const [page, setPage] = useState(1);

  useEffect(() => { setPage(1); }, [resetKey]);

  const total = items.length;
  const totalPages = Math.max(1, Math.ceil(total / perPage));
  const current = Math.min(page, totalPages);
  const pageItems = items.slice((current - 1) * perPage, current * perPage);

  return { pageItems, page: current, setPage, total, totalPages, perPage };
}

function windowed(current, totalPages) {
  if (totalPages <= 7) return Array.from({ length: totalPages }, (_, i) => i + 1);
  const pages = new Set([1, totalPages, current - 1, current, current + 1]);
  const sorted = [...pages].filter((p) => p >= 1 && p <= totalPages).sort((a, b) => a - b);
  const out = [];
  sorted.forEach((p, i) => {
    if (i > 0 && p - sorted[i - 1] > 1) out.push('…');
    out.push(p);
  });
  return out;
}

export default function Pagination({ pager, label = 'items' }) {
  const { page, setPage, total, totalPages, perPage } = pager;
  if (total <= perPage) return null;

  const from = (page - 1) * perPage + 1;
  const to = Math.min(page * perPage, total);

  return (
    <div className="pager">
      <div className="pager-info">Showing {from}–{to} of {total} {label}</div>
      <div className="pager-controls">
        <button type="button" className="pager-btn" disabled={page === 1} onClick={() => setPage(page - 1)}>← Prev</button>
        {windowed(page, totalPages).map((p, i) => (
          p === '…'
            ? <span key={`gap${i}`} className="pager-gap">…</span>
            : <button type="button" key={p} className={`pager-btn num ${p === page ? 'active' : ''}`} onClick={() => setPage(p)}>{p}</button>
        ))}
        <button type="button" className="pager-btn" disabled={page === totalPages} onClick={() => setPage(page + 1)}>Next →</button>
      </div>
    </div>
  );
}
