import { useState, useEffect, useRef, useCallback } from 'react';
import { Link, usePage, router } from '@inertiajs/react';
import useExchangeRate from '@/hooks/useExchangeRate';

const NAV = [
  { label: 'Dashboard',    href: '/',            icon: <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg>, section: 'Overview' },
  { label: 'Units',        href: '/units',        icon: <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>, section: null },
  { label: 'Tenants',      href: '/tenants',      icon: <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>, section: null },
  { label: 'Leases',       href: '/leases',       icon: <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>, section: 'Operations' },
  { label: 'Electricity',  href: '/electricity',  icon: <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>, section: null },
  { label: 'Maintenance',  href: '/maintenance',  icon: <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><path d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z"/></svg>, section: null },
  { label: 'Documents',    href: '/documents',    icon: <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><path d="M3 6a2 2 0 012-2h5l2 2h7a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V6z"/></svg>, section: null },
  { label: 'Invoices',     href: '/invoices',     icon: <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>, section: 'Finance' },
  { label: 'Payments',     href: '/payments',     icon: <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>, section: null },
  { label: 'Accounting',   href: '/accounting',   icon: <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>, section: null },
  { label: 'Reports',      href: '/reports',      icon: <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>, section: null },
  { label: 'Team',         href: '/team',         icon: <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>, section: 'Administration', badge: '0' },
  { label: 'Audit Trail',  href: '/audit',        icon: <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><line x1="10" y1="9" x2="8" y2="9"/></svg>, section: null, badge: '!', badgeStyle: { background: 'var(--red)', display: 'none' } },
];

/* ─── Overflow "•••" button ──────────────────────────────────────── */
function OverflowNavBtn({ items, isActive, collapsed }) {
  const [show, setShow] = useState(false);
  const [pos, setPos] = useState({ left: 0, bottom: 0 });
  const btnRef = useRef(null);
  const popRef = useRef(null);

  const open = () => {
    if (btnRef.current) {
      const r = btnRef.current.getBoundingClientRect();
      setPos({
        left: collapsed ? r.right + 8 : r.left,
        bottom: window.innerHeight - r.top + 6,
      });
    }
    setShow(true);
  };

  useEffect(() => {
    if (!show) return;
    const handler = (e) => {
      if (!btnRef.current?.contains(e.target) && !popRef.current?.contains(e.target))
        setShow(false);
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, [show]);

  return (
    <>
      <button
        ref={btnRef}
        type="button"
        className={`nav-item${show ? ' active' : ''}`}
        onClick={() => (show ? setShow(false) : open())}
        data-tooltip="More"
        style={{
          position: 'absolute', bottom: 4, left: 8, right: 8,
          border: 'none', background: show ? 'var(--bg-hover)' : 'none',
          cursor: 'pointer',
        }}
      >
        <svg viewBox="0 0 24 24" fill="currentColor" style={{ width: 16, height: 16, flexShrink: 0 }}>
          <circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/>
        </svg>
        <span className="nav-label">More</span>
      </button>

      {show && (
        <div
          ref={popRef}
          style={{
            position: 'fixed',
            left: pos.left,
            bottom: pos.bottom,
            minWidth: 200,
            background: 'var(--bg-surface)',
            border: '1px solid var(--border)',
            borderRadius: 10,
            padding: 6,
            boxShadow: '0 8px 30px rgba(0,0,0,.25)',
            zIndex: 400,
          }}
        >
          {items.map((item) => (
            <Link
              key={item.href}
              href={item.href}
              className={`nav-item${isActive(item.href) ? ' active' : ''}`}
              onClick={() => setShow(false)}
              style={{ display: 'flex' }}
            >
              {item.icon}
              <span style={{ opacity: 1, marginLeft: 10, fontSize: 13.5, whiteSpace: 'nowrap' }}>{item.label}</span>
            </Link>
          ))}
        </div>
      )}
    </>
  );
}

export default function AppLayout({ children, title, subtitle }) {
  const [collapsed, setCollapsed] = useState(() => localStorage.getItem('sidebar-collapsed') === 'true');
  useEffect(() => { localStorage.setItem('sidebar-collapsed', collapsed); }, [collapsed]);
  const [userMenuOpen, setUserMenuOpen] = useState(false);
  const [menuPos, setMenuPos] = useState({ bottom: 0, left: 0 });
  const userMenuRef = useRef(null);
  const triggerRef = useRef(null);
  useEffect(() => {
    if (!userMenuOpen) return;
    const handler = (e) => { if (!userMenuRef.current?.contains(e.target)) setUserMenuOpen(false); };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, [userMenuOpen]);
  const toggleUserMenu = () => {
    if (!userMenuOpen && triggerRef.current) {
      const r = triggerRef.current.getBoundingClientRect();
      setMenuPos({ bottom: window.innerHeight - r.top + 6, left: r.left + 8 });
    }
    setUserMenuOpen(o => !o);
  };
  const [theme, setTheme] = useState(() => localStorage.getItem('app-theme') || 'dark');

  // ── PWA install prompt ──────────────────────────────────────────────────
  const [installPrompt, setInstallPrompt] = useState(
    () => (typeof window !== 'undefined' ? window.__pwaInstallPrompt || null : null)
  );
  const [isInstalled, setIsInstalled] = useState(
    () => typeof window !== 'undefined' && window.matchMedia('(display-mode: standalone)').matches
  );
  useEffect(() => {
    // Pick up any prompt that was captured before React mounted
    if (window.__pwaInstallPrompt && !installPrompt) setInstallPrompt(window.__pwaInstallPrompt);
    const onPrompt = (e) => { e.preventDefault(); window.__pwaInstallPrompt = e; setInstallPrompt(e); };
    const onInstalled = () => { setIsInstalled(true); setInstallPrompt(null); window.__pwaInstallPrompt = null; };
    window.addEventListener('beforeinstallprompt', onPrompt);
    window.addEventListener('appinstalled', onInstalled);
    return () => {
      window.removeEventListener('beforeinstallprompt', onPrompt);
      window.removeEventListener('appinstalled', onInstalled);
    };
  }, []);
  const handleInstall = async () => {
    if (installPrompt) {
      installPrompt.prompt();
      const { outcome } = await installPrompt.userChoice;
      if (outcome === 'accepted') { setInstallPrompt(null); setIsInstalled(true); }
    }
    // If prompt hasn't been captured yet (e.g. Chrome hasn't fired it),
    // the button is still visible so the user knows the app is installable.
    // Chrome will fire beforeinstallprompt once engagement criteria are met.
  };
  const { url, props } = usePage();
  const { rate, sourceLabel, refreshRate } = useExchangeRate();

  /* ── Nav overflow ──────────────────────────────────────────────── */
  const navRef = useRef(null);
  const [overflowFrom, setOverflowFrom] = useState(NAV.length);
  const OVERFLOW_BTN_H = 40;

  const measureNav = useCallback(() => {
    const nav = navRef.current;
    if (!nav) return;
    const wrap = nav.querySelector('.nav-items-wrap');
    if (!wrap) return;
    const navH = nav.clientHeight;
    const children = Array.from(wrap.children);

    // Pass 1: does ANYTHING actually overflow the nav?
    let hasOverflow = false;
    for (const child of children) {
      if (child.offsetTop + child.offsetHeight > navH) { hasOverflow = true; break; }
    }
    if (!hasOverflow) { setOverflowFrom(children.length); return; }

    // Pass 2: find first item hidden behind the "•••" button
    const safeH = navH - OVERFLOW_BTN_H;
    let first = children.length;
    for (let i = 0; i < children.length; i++) {
      if (children[i].offsetTop + children[i].offsetHeight > safeH) { first = i; break; }
    }
    setOverflowFrom(first);
  }, []);

  useEffect(() => {
    measureNav();
    const ro = new ResizeObserver(measureNav);
    if (navRef.current) ro.observe(navRef.current);
    window.addEventListener('resize', measureNav);
    return () => {
      ro.disconnect();
      window.removeEventListener('resize', measureNav);
    };
  }, [measureNav, collapsed]);

  const user = props?.auth?.user;
  const viewingProperty = props?.viewing_property ?? null;
  const displayName = user?.name || 'User';
  const roleLabel = user?.role
    ? (user.role === 'lease_manager'
      ? 'Lease Assistant'
      : user.role.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()))
    : 'User';
  const initials = displayName
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((s) => s[0])
    .join('')
    .toUpperCase() || 'U';

  useEffect(() => {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('app-theme', theme);
  }, [theme]);

  const isActive = (href) => href === '/' ? url === '/' : url.startsWith(href);

  return (
    <div className="app-layout">
      <aside className={`sidebar ${collapsed ? 'collapsed' : ''}`} id="sidebar">
        <div className="sidebar-header">
          <div className="logo-mark">R</div>
          <span className="logo-text">Ruky Rentals</span>
        </div>

        <nav className="nav" ref={navRef} style={{ position: 'relative' }}>
          {/* Items wrapper — all items are rendered; those past overflowFrom are naturally
              clipped by the nav's overflow:hidden. They appear in the overflow popover. */}
          <div className="nav-items-wrap">
            {NAV.map((item, index) => {
              const previousSection = index > 0 ? NAV[index - 1].section : null;
              const showSection = Boolean(item.section) && item.section !== previousSection;
              // Items past overflowFrom are still in DOM for measurement but invisible + inert
              const hidden = index >= overflowFrom;
              return (
                <div key={item.href ?? item.label} aria-hidden={hidden || undefined}
                  style={hidden ? { visibility: 'hidden', pointerEvents: 'none' } : undefined}>
                  {showSection && <span className="nav-section-label">{item.section}</span>}
                  <Link href={item.href}
                    className={`nav-item ${isActive(item.href) ? 'active' : ''}`}
                    data-tooltip={item.label}
                    tabIndex={hidden ? -1 : undefined}>
                    {item.icon}
                    <span className="nav-label">{item.label}</span>
                    {item.badge && <span className="nav-badge" style={item.badgeStyle}>{item.badge}</span>}
                  </Link>
                </div>
              );
            })}
          </div>

          {/* Overflow button — absolutely positioned at nav bottom; only when items overflow */}
          {overflowFrom < NAV.length && (
            <OverflowNavBtn
              items={NAV.slice(overflowFrom)}
              isActive={isActive}
              collapsed={collapsed}
            />
          )}
        </nav>

        <div className="sidebar-footer" ref={userMenuRef} style={{ position: 'relative' }}>
          {/* User menu dropdown — fixed position to escape sidebar overflow:hidden */}
          {userMenuOpen && (
            <div className="user-menu-dropdown" style={{ position: 'fixed', bottom: menuPos.bottom, left: menuPos.left, width: 220 }}>
              <div className="user-menu-email">{user?.email}</div>
              <div style={{ padding: '4px 6px' }}>
                <Link href="/profile" onClick={() => setUserMenuOpen(false)} className="user-menu-item" style={{ textDecoration: 'none' }}>
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                  Profile
                </Link>
              </div>
              <div className="user-menu-divider" />
              <div style={{ padding: '4px 6px' }}>
                <button onClick={() => router.post('/logout')} className="user-menu-item user-menu-item--danger">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                  Log out
                </button>
              </div>
            </div>
          )}

          {collapsed ? (
            /* ── Collapsed: plain download icon stacked above avatar ── */
            <div ref={triggerRef} style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 8 }}>
              {installPrompt && (
                <button onClick={handleInstall} title="Install app" style={{ background: 'none', border: 'none', cursor: 'pointer', color: 'var(--text-muted)', padding: 4, display: 'flex' }}>
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
                    <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
                    <polyline points="7 10 12 15 17 10"/>
                    <line x1="12" y1="15" x2="12" y2="3"/>
                  </svg>
                </button>
              )}
              <button onClick={() => toggleUserMenu()} style={{ background: 'none', border: 'none', cursor: 'pointer', padding: 0 }}>
                <div className="avatar" style={{ overflow: 'hidden', padding: 0 }}>
                  {user?.avatar_url
                    ? <img src={user.avatar_url} alt={displayName} style={{ width: '100%', height: '100%', objectFit: 'cover', borderRadius: 'inherit' }} />
                    : initials}
                </div>
              </button>
            </div>
          ) : (
            /* ── Expanded: [user card opens menu] [install btn] [chevron btn] ── */
            <div ref={triggerRef} style={{ display: 'flex', alignItems: 'center', gap: 4 }}>
              <button onClick={() => toggleUserMenu()} className="user-card" style={{ flex: 1, minWidth: 0, background: 'none', border: 'none', cursor: 'pointer', textAlign: 'left' }}>
                <div className="avatar" style={{ overflow: 'hidden', padding: 0 }}>
                  {user?.avatar_url
                    ? <img src={user.avatar_url} alt={displayName} style={{ width: '100%', height: '100%', objectFit: 'cover', borderRadius: 'inherit' }} />
                    : initials}
                </div>
                <div className="user-info">
                  <div className="user-name">{displayName}</div>
                  <div className="user-role">{roleLabel}</div>
                </div>
              </button>
              {installPrompt && (
                <button onClick={handleInstall} title="Install app" className="pwa-install-btn">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
                    <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
                    <polyline points="7 10 12 15 17 10"/>
                    <line x1="12" y1="15" x2="12" y2="3"/>
                  </svg>
                </button>
              )}
              <button onClick={() => toggleUserMenu()} title="User menu" className="pwa-install-btn">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
                  <polyline points="18 8 12 2 6 8"/>
                  <polyline points="6 16 12 22 18 16"/>
                </svg>
              </button>
            </div>
          )}
        </div>
      </aside>

      <div className="main">
        {viewingProperty && (
          <div style={{
            background: 'rgba(245, 158, 11, 0.08)',
            borderBottom: '1px solid rgba(245, 158, 11, 0.35)',
            padding: '8px 20px',
            display: 'flex',
            alignItems: 'center',
            gap: 10,
            fontSize: 13,
            color: 'rgb(245, 158, 11)',
          }}>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            <span>Superuser · Managing</span>
            <strong style={{ color: 'var(--text)' }}>{viewingProperty.name}</strong>
            <button
              onClick={() => router.post('/superuser/property/exit')}
              style={{
                marginLeft: 'auto',
                padding: '4px 12px',
                borderRadius: 6,
                border: '1px solid rgba(245, 158, 11, 0.5)',
                background: 'transparent',
                color: 'rgb(245, 158, 11)',
                cursor: 'pointer',
                fontSize: 12,
              }}
            >
              ← Exit to Superuser Panel
            </button>
          </div>
        )}
        <header className="topbar">
          <button
            className="toggle-btn"
            onClick={() => setCollapsed(!collapsed)}
            title="Toggle sidebar"
            aria-label="Toggle sidebar"
            aria-expanded={!collapsed}
          >
            <svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
              <rect x="1.5" y="1.5" width="15" height="15" rx="3" stroke="currentColor" strokeWidth="1.5"/>
              <line x1="6" y1="1.5" x2="6" y2="16.5" stroke="currentColor" strokeWidth="1.5"/>
            </svg>
          </button>
          <div className="topbar-title">
            {title} {subtitle && <span>— {subtitle}</span>}
          </div>
          <div className="search-box">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            <input type="text" placeholder="Search units, tenants…" />
          </div>
          <div
            onClick={refreshRate}
            title="Click to refresh rate"
            style={{fontSize:11,color:'var(--text-muted)',background:'var(--bg-elevated)',padding:'4px 10px',borderRadius:20,border:'1px solid var(--border)',cursor:'pointer',whiteSpace:'nowrap'}}
          >
            {`1 USD = ${Math.round(rate).toLocaleString()} TZS · ${sourceLabel}`}
          </div>
          <button className="icon-btn" onClick={() => setTheme(t => t === 'dark' ? 'light' : 'dark')} title="Toggle theme">
            {theme === 'dark'
              ? <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/></svg>
              : <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
            }
          </button>
          <button className="icon-btn" title="Notifications">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 01-3.46 0"/></svg>
            <span className="notif-dot"></span>
          </button>
        </header>

        <div className="content">
          {children}
        </div>
      </div>
    </div>
  );
}
