import { useEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';

const SHOW_AFTER_MS = 250;   // quick actions finish before this, so they never flash
const GIVE_UP_AFTER_MS = 60_000;

/**
 * A request worth covering the screen for: moving to another page. Saves are left to the spinner on their own button
 * (the form stays readable and shows its errors), and small partial refreshes do not count either.
 */
const counts = (visit) => String(visit?.method).toLowerCase() === 'get' && !(visit?.only?.length > 0) && !visit?.prefetch;

/**
 * Dims the page and shows a ring while a page change or a save is taking time.
 * Replaces Inertia's thin progress bar; clicks are blocked while it shows so nothing is pressed twice.
 */
export default function PageLoader() {
  const [visible, setVisible] = useState(false);
  const running = useRef(0);
  const showTimer = useRef(null);
  const giveUpTimer = useRef(null);

  useEffect(() => {
    const hide = () => {
      window.clearTimeout(showTimer.current);
      window.clearTimeout(giveUpTimer.current);
      showTimer.current = null;
      setVisible(false);
    };

    const offStart = router.on('start', (event) => {
      if (!counts(event.detail?.visit)) return;
      running.current += 1;
      if (running.current === 1) {
        showTimer.current = window.setTimeout(() => setVisible(true), SHOW_AFTER_MS);
        giveUpTimer.current = window.setTimeout(() => { running.current = 0; hide(); }, GIVE_UP_AFTER_MS);
      }
    });

    const offFinish = router.on('finish', (event) => {
      if (!counts(event.detail?.visit)) return;
      running.current = Math.max(0, running.current - 1);
      if (running.current === 0) hide();
    });

    return () => {
      offStart?.();
      offFinish?.();
      hide();
    };
  }, []);

  if (!visible) return null;

  return (
    <div className="page-loader" role="status" aria-live="polite" aria-label="Loading">
      <div className="page-loader-spinner" aria-hidden="true">
        {[0, 1, 2, 3, 4, 5, 6, 7].map((i) => <i key={i} style={{ '--i': i }} />)}
      </div>
    </div>
  );
}
