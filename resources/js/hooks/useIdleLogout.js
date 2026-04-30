import { router } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

const ACTIVITY_EVENTS = ['mousedown', 'keydown', 'scroll', 'touchstart', 'pointerdown'];

export default function useIdleLogout(user, timeoutMinutes = 15) {
  const timerRef = useRef(null);

  useEffect(() => {
    if (!user) {
      return undefined;
    }

    const timeoutMs = Math.max(Number(timeoutMinutes) || 15, 1) * 60_000;

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

    scheduleLogout();

    ACTIVITY_EVENTS.forEach((eventName) => {
      window.addEventListener(eventName, scheduleLogout, { passive: true });
    });

    return () => {
      clearIdleTimer();
      ACTIVITY_EVENTS.forEach((eventName) => {
        window.removeEventListener(eventName, scheduleLogout);
      });
    };
  }, [user, timeoutMinutes]);
}
