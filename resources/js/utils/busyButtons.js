import { router } from '@inertiajs/react';

/**
 * Every button whose click starts a request (save, submit, approve, email, delete…) shows the same circular
 * spinner until the request finishes, and ignores further clicks meanwhile. Buttons that already draw their own
 * spinner (.btn-spinner) keep theirs; the CSS hides this one for them.
 */
export function installBusyButtons() {
  if (typeof window === 'undefined' || window.__busyButtonsInstalled) return;
  window.__busyButtonsInstalled = true;

  let lastButton = null;
  let lastClickAt = 0;
  let busyButton = null;

  document.addEventListener('click', (event) => {
    const button = event.target instanceof Element ? event.target.closest('button') : null;
    if (button && !button.disabled) {
      lastButton = button;
      lastClickAt = Date.now();
    }
  }, true);

  const clear = () => {
    if (busyButton) {
      busyButton.removeAttribute('data-busy');
      busyButton = null;
    }
  };

  router.on('start', () => {
    clear();
    // Only a request that follows a click within a moment is that button's request (not a poll or a redirect).
    if (lastButton && Date.now() - lastClickAt < 800 && document.contains(lastButton)) {
      busyButton = lastButton;
      busyButton.setAttribute('data-busy', 'true');
    }
  });
  router.on('finish', clear);
}
