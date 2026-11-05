import { router } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

const ACTIVITY_EVENTS = ['mousedown', 'keydown', 'scroll', 'touchstart', 'pointerdown', 'mousemove'];
const PING_EVERY_MS = 60_000;

const xsrfToken = () => {
  const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
  return match ? decodeURIComponent(match[1]) : '';
};

export default function useIdleLogout(user, timeoutMinutes = 15) {
  const timerRef = useRef(null);

  useEffect(() => {
    if (!user) {
      return undefined;
    }

    const timeoutMs = Math.max(Number(timeoutMinutes) || 15, 1) * 60_000;
    let activeSinceLastPing = false;
    let lastReset = 0;

    const clearIdleTimer = () => {
      if (timerRef.current) {
        window.clearTimeout(timerRef.current);
      }
    };

    const scheduleLogout = () => {
      clearIdleTimer();
      timerRef.current = window.setTimeout(() => {
        router.post('/logout');
      }, timeoutMs);
    };

    // Any activity restarts the browser timer. Mouse moves fire constantly, so they restart it at most once a second.
    const onActivity = (event) => {
      const now = Date.now();
      if (event?.type === 'mousemove' && now - lastReset < 1000) return;
      lastReset = now;
      activeSinceLastPing = true;
      scheduleLogout();
    };

    // The server only counts requests as activity. Working inside one page (a long form, filters, tabs)
    // makes none, so while the user is active the page tells the server once a minute.
    const ping = async () => {
      if (!activeSinceLastPing) return;
      activeSinceLastPing = false;
      try {
        const res = await fetch('/session/keep-alive', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { Accept: 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
        });
        if (res.status === 401 || res.status === 419) {
          window.location.assign('/login');
        }
      } catch { /* offline: try again next minute */ }
    };

    scheduleLogout();
    const pingTimer = window.setInterval(ping, PING_EVERY_MS);

    ACTIVITY_EVENTS.forEach((eventName) => {
      window.addEventListener(eventName, onActivity, { passive: true, capture: true });
    });

    return () => {
      clearIdleTimer();
      window.clearInterval(pingTimer);
      ACTIVITY_EVENTS.forEach((eventName) => {
        window.removeEventListener(eventName, onActivity, { capture: true });
      });
    };
  }, [user, timeoutMinutes]);
}
