import { useCallback, useState } from 'react';

/**
 * Stops a stray click outside a form dialog from throwing the user's work away.
 *
 *   const guard = useDiscardGuard();
 *   <div className="modal-overlay" onClick={(e) => e.target === e.currentTarget && guard.ask(() => setShowModal(false))}>
 *   ...
 *   {guard.dialog}
 *
 * The close buttons (✕ / Cancel) still close straight away; only the backdrop asks.
 */
export function useDiscardGuard() {
  const [pending, setPending] = useState(null);

  const ask = useCallback((closeFn) => setPending(() => closeFn), []);
  const keepEditing = () => setPending(null);
  const discard = () => {
    const close = pending;
    setPending(null);
    close?.();
  };

  const dialog = (
    <div className={`modal-overlay ${pending ? 'open' : ''}`} style={{ zIndex: 1700 }} onClick={(e) => e.target === e.currentTarget && keepEditing()}>
      <div className="modal" style={{ width: 'min(400px, calc(100vw - 24px))' }} role="alertdialog" aria-modal="true" aria-label="Close without saving?">
        <div className="modal-header">
          <div className="modal-title">Close without saving?</div>
        </div>
        <div className="modal-body">
          <p style={{ margin: 0, fontSize: 14, color: 'var(--text-secondary)', lineHeight: 1.6 }}>
            What you have entered will be lost. Do you want to keep editing?
          </p>
        </div>
        <div className="modal-footer">
          <button type="button" className="btn-ghost" onClick={discard}>Discard</button>
          <button type="button" className="btn-primary" onClick={keepEditing} autoFocus>Keep editing</button>
        </div>
      </div>
    </div>
  );

  return { ask, dialog };
}
