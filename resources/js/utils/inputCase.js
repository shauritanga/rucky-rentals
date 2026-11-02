/**
 * Keeps typed text in the right case on every form without touching each input:
 *   - any <input type="email"> is lower case
 *   - any input marked data-case="upper" (e.g. VRN) is upper case
 * Runs in the capture phase, so React's onChange already sees the corrected value.
 */
export function installInputCase() {
  if (window.__inputCaseInstalled) return;
  window.__inputCaseInstalled = true;

  document.addEventListener('input', (event) => {
    const el = event.target;
    if (!(el instanceof HTMLInputElement)) return;

    let next = null;
    if (el.type === 'email') next = el.value.toLowerCase();
    else if (el.dataset.case === 'upper') next = el.value.toUpperCase();
    if (next === null || next === el.value) return;

    const start = el.selectionStart;
    const end = el.selectionEnd;
    el.value = next;
    try { el.setSelectionRange(start, end); } catch { /* type without selection support */ }
  }, true);
}
