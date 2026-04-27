import { useState, useEffect, useRef, useCallback } from 'react';
import { Link, usePage, router } from '@inertiajs/react';
import useExchangeRate from '@/hooks/useExchangeRate';
import echo from '@/echo';

/* ─── Notification Bell ──────────────────────────────────────────── */
function timeAgo(ts) {
    if (!ts) return '';
    const diff = Math.floor((Date.now() - new Date(ts).getTime()) / 1000);
    if (diff < 60) return 'Just now';
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    return `${Math.floor(diff / 86400)}d ago`;
}

function NotificationBell({ onNavigate }) {
    const { props } = usePage();
    const [open, setOpen] = useState(false);
    const [items, setItems] = useState([]);
    const [loading, setLoading] = useState(false);
    const [localUnread, setLocalUnread] = useState(null);
    const ref = useRef(null);

    const unread = localUnread !== null ? localUnread : (props.notifications_unread ?? 0);

    // Close on outside click
    useEffect(() => {
        const handler = (e) => { if (ref.current && !ref.current.contains(e.target)) setOpen(false); };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, []);

    const fetchNotifications = async () => {
        try {
            const res = await fetch('/superuser/notifications', { headers: { 'Accept': 'application/json' } });
            const json = await res.json();
            setItems(json.notifications ?? []);
            setLocalUnread(json.unread_count ?? 0);
        } catch {}
    };

    // Subscribe to real-time notifications via Reverb WebSocket + 30s polling fallback
    useEffect(() => {
        const userId = props.auth?.user?.id;
        if (!userId) return;

        // Initial fetch on mount
        fetchNotifications();

        // 30-second polling fallback (works even without Reverb running)
        const interval = setInterval(fetchNotifications, 30_000);

        // Listen for pushed notifications on the user's private channel
        if (echo) {
            const channel = echo.private(`App.Models.User.${userId}`);
            channel.notification(() => {
                fetchNotifications();
            });
        }

        return () => {
            clearInterval(interval);
            if (echo) echo.leave(`App.Models.User.${userId}`);
        };
    }, [props.auth?.user?.id]);

    const handleOpen = () => {
        setOpen((v) => !v);
        if (!open) { setLoading(true); fetchNotifications().finally(() => setLoading(false)); }
    };

    const csrfToken = () => document.querySelector('meta[name=csrf-token]')?.content ?? '';

    const handleMarkAllRead = async () => {
        await fetch('/superuser/notifications/read', {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
        });
        setLocalUnread(0);
        setItems((prev) => prev.map((n) => ({ ...n, read_at: n.read_at ?? new Date().toISOString() })));
    };

    const handleClearAll = async () => {
        await fetch('/superuser/notifications', {
            method: 'DELETE',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
        });
        setItems([]);
        setLocalUnread(0);
    };

    const handleItemClick = () => {
        handleMarkAllRead();
        setOpen(false);
        onNavigate?.('approvals');
    };

    const getIcon = (data) => {
        if (data?.type === 'lease_approval_request') {
            return (
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" style={{ flexShrink: 0, color: 'var(--accent)' }}>
                    <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/>
                    <line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>
                </svg>
            );
        }
        if (data?.type === 'team_approval_request') {
            return (
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" style={{ flexShrink: 0, color: 'var(--green)' }}>
                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/>
                </svg>
            );
        }
        return (
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" style={{ flexShrink: 0, color: 'var(--amber)' }}>
                <path d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z"/>
            </svg>
        );
    };

    const getTitle = (data) => {
        if (data?.type === 'lease_approval_request') return 'Lease Approval Request';
        if (data?.type === 'team_approval_request') return 'Team Approval Request';
        if (data?.stage === 'submitted') return 'Maintenance Request';
        if (data?.stage === 'pending_manager') return 'Maintenance Pending Review';
        return 'New Request';
    };

    const getSub = (data) => {
        if (data?.type === 'lease_approval_request') return [data.tenant, data.property].filter(Boolean).join(' · ');
        if (data?.type === 'team_approval_request') return [data.name, data.property || data.role_label].filter(Boolean).join(' · ');
        if (data?.title) return [data.ticket_number, data.priority].filter(Boolean).join(' · ');
        return '';
    };

    return (
        <div ref={ref} style={{ position: 'relative' }}>
            <button
                className="icon-btn"
                title="Notifications"
                onClick={handleOpen}
                style={{ position: 'relative' }}
            >
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 01-3.46 0"/>
                </svg>
                {unread > 0 && (
                    <span style={{
                        position: 'absolute', top: 3, right: 3,
                        minWidth: 16, height: 16, borderRadius: 8, padding: '0 4px',
                        background: '#e53935', color: '#fff',
                        fontSize: 10, fontWeight: 700,
                        display: 'flex', alignItems: 'center', justifyContent: 'center',
                        border: '2px solid var(--bg-surface)',
                    }}>
                        {unread > 99 ? '99+' : unread}
                    </span>
                )}
            </button>

            {open && (
                <div style={{
                    position: 'absolute', top: 'calc(100% + 8px)', right: 0,
                    width: 340, background: 'var(--bg-surface)',
                    border: '1px solid var(--border)', borderRadius: 12,
                    boxShadow: 'var(--shadow-float)', zIndex: 200,
                    overflow: 'hidden',
                }}>
                    {/* Header */}
                    <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '12px 16px', borderBottom: '1px solid var(--border)' }}>
                        <span style={{ fontWeight: 700, fontSize: 13 }}>Notifications</span>
                        <div style={{ display: 'flex', gap: 10 }}>
                            {unread > 0 && (
                                <button type="button" onClick={handleMarkAllRead}
                                    style={{ fontSize: 11.5, color: 'var(--accent)', background: 'none', border: 'none', cursor: 'pointer', padding: 0 }}>
                                    Mark all read
                                </button>
                            )}
                            {items.length > 0 && (
                                <button type="button" onClick={handleClearAll}
                                    style={{ fontSize: 11.5, color: 'var(--text-muted)', background: 'none', border: 'none', cursor: 'pointer', padding: 0 }}>
                                    Clear all
                                </button>
                            )}
                        </div>
                    </div>

                    {/* List */}
                    <div style={{ maxHeight: 400, overflowY: 'auto' }}>
                        {loading && (
                            <div style={{ padding: '20px 16px', textAlign: 'center', color: 'var(--text-muted)', fontSize: 13 }}>
                                Loading…
                            </div>
                        )}
                        {!loading && items.length === 0 && (
                            <div style={{ padding: '28px 16px', textAlign: 'center', color: 'var(--text-muted)', fontSize: 13 }}>
                                You're all caught up ✓
                            </div>
                        )}
                        {!loading && items.map((n) => {
                            const isUnread = !n.read_at;
                            return (
                                <button
                                    key={n.id}
                                    type="button"
                                    onClick={handleItemClick}
                                    style={{
                                        display: 'flex', alignItems: 'flex-start', gap: 10,
                                        width: '100%', textAlign: 'left', padding: '11px 16px',
                                        background: isUnread ? 'var(--bg-elevated)' : 'transparent',
                                        border: 'none', borderBottom: '1px solid var(--border)', cursor: 'pointer',
                                        transition: 'background .12s',
                                    }}
                                    onMouseEnter={(e) => e.currentTarget.style.background = 'var(--bg-hover)'}
                                    onMouseLeave={(e) => e.currentTarget.style.background = isUnread ? 'var(--bg-elevated)' : 'transparent'}
                                >
                                    {isUnread && (
                                        <span style={{ width: 7, height: 7, borderRadius: '50%', background: 'var(--accent)', flexShrink: 0, marginTop: 4 }} />
                                    )}
                                    {!isUnread && <span style={{ width: 7, flexShrink: 0 }} />}
                                    <span style={{ marginTop: 1 }}>{getIcon(n.data)}</span>
                                    <div style={{ flex: 1, minWidth: 0 }}>
                                        <div style={{ fontSize: 13, fontWeight: isUnread ? 600 : 400, color: 'var(--text-primary)', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                                            {getTitle(n.data)}
                                        </div>
                                        <div style={{ fontSize: 11.5, color: 'var(--text-muted)', marginTop: 1, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                                            {getSub(n.data)}
                                        </div>
                                    </div>
                                    <span style={{ fontSize: 11, color: 'var(--text-muted)', flexShrink: 0, marginTop: 2 }}>{timeAgo(n.created_at)}</span>
                                </button>
                            );
                        })}
                    </div>
                </div>
            )}
        </div>
    );
}

const NAV = [
  { id: 'overview', label: 'Overview', section: 'Main', icon: <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg> },
  { id: 'properties', label: 'Properties', section: null, badgeKey: 'properties', icon: <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg> },
  { id: 'managers', label: 'Administrators', section: 'Users', badgeKey: 'managers', icon: <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg> },
  { id: 'roles', label: 'Roles & Permissions', section: null, icon: <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg> },
  { id: 'approvals', label: 'Approvals', section: 'System', badgeKey: 'approvals', icon: <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> },
  { id: 'audit', label: 'Audit Trail', section: null, icon: <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg> },
  { id: 'settings', label: 'Settings', section: null, icon: <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 00.34 1.87l.06.06a2 2 0 01-2.83 2.83l-.06-.06a1.7 1.7 0 00-1.87-.34 1.7 1.7 0 00-1 1.54V22a2 2 0 01-4 0v-.09a1.7 1.7 0 00-1-1.54 1.7 1.7 0 00-1.87.34l-.06.06a2 2 0 01-2.83-2.83l.06-.06a1.7 1.7 0 00.34-1.87 1.7 1.7 0 00-1.54-1H2a2 2 0 010-4h.09a1.7 1.7 0 001.54-1 1.7 1.7 0 00-.34-1.87l-.06-.06a2 2 0 012.83-2.83l.06.06a1.7 1.7 0 001.87.34h.01a1.7 1.7 0 001-1.54V2a2 2 0 014 0v.09a1.7 1.7 0 001 1.54h.01a1.7 1.7 0 001.87-.34l.06-.06a2 2 0 012.83 2.83l-.06.06a1.7 1.7 0 00-.34 1.87v.01a1.7 1.7 0 001.54 1H22a2 2 0 010 4h-.09a1.7 1.7 0 00-1.54 1z"/></svg> },
];

/* ─── Overflow "•••" button (superuser sidebar) ──────────────────── */
function OverflowNavBtnSU({ items, activeView, onNavigate, navCounts, collapsed }) {
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
          cursor: 'pointer', width: 'calc(100% - 16px)',
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
            <button
              key={item.id}
              type="button"
              className={`nav-item${activeView === item.id ? ' active' : ''}`}
              onClick={() => { onNavigate(item.id); setShow(false); }}
              style={{ width: '100%', textAlign: 'left', border: 'none', background: 'none', cursor: 'pointer', display: 'flex' }}
            >
              {item.icon}
              <span style={{ opacity: 1, marginLeft: 10, fontSize: 13.5, whiteSpace: 'nowrap' }}>{item.label}</span>
              {!!item.badgeKey && navCounts[item.badgeKey] > 0 && (
                <span className="nav-badge" style={{ marginLeft: 'auto', opacity: 1 }}>{navCounts[item.badgeKey]}</span>
              )}
            </button>
          ))}
        </div>
      )}
    </>
  );
}

export default function SuperuserLayout({ activeView, onNavigate, title, subtitle, actionLabel, onAction, navCounts = {}, children }) {
  const [collapsed, setCollapsed] = useState(() => localStorage.getItem('sidebar-collapsed') === 'true');
  useEffect(() => { localStorage.setItem('sidebar-collapsed', collapsed); }, [collapsed]);
  const [theme, setTheme] = useState(() => localStorage.getItem('app-theme') || 'dark');
  const { props } = usePage();

  // ── User menu ───────────────────────────────────────────────────────────
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
      if (outcome === 'accepted') { setInstallPrompt(null); setIsInstalled(true); window.__pwaInstallPrompt = null; }
    }
  };
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
  const displayName = user?.name || 'Super Admin';
  const roleLabel = user?.role
    ? (user.role === 'lease_manager'
      ? 'Lease Assistant'
      : user.role.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()))
    : 'System Owner';
  const initials = displayName
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((s) => s[0])
    .join('')
    .toUpperCase() || 'SA';

  useEffect(() => {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('app-theme', theme);
  }, [theme]);

  let lastSection = null;

  return (
    <div className="app-layout superuser-shell">
      <aside className={`sidebar ${collapsed ? 'collapsed' : ''}`}>
        <div className="sidebar-header">
          <div className="logo-mark">M</div>
          <span className="logo-text">Mwamba Properties</span>
        </div>

        <nav className="nav" ref={navRef} style={{ position: 'relative' }}>
          <div className="nav-items-wrap">
            {NAV.map((item, index) => {
              const showSection = item.section && item.section !== lastSection;
              if (item.section) lastSection = item.section;
              const hidden = index >= overflowFrom;
              return (
                <div key={item.id} aria-hidden={hidden || undefined}
                  style={hidden ? { visibility: 'hidden', pointerEvents: 'none' } : undefined}>
                  {showSection && <span className="nav-section-label">{item.section}</span>}
                  <button
                    type="button"
                    className={`nav-item ${activeView === item.id ? 'active' : ''}`}
                    onClick={() => onNavigate(item.id)}
                    style={{ width: '100%', textAlign: 'left', border: 'none', background: 'none' }}
                    data-tooltip={item.label}
                    tabIndex={hidden ? -1 : undefined}
                  >
                    {item.icon}
                    <span className="nav-label">{item.label}</span>
                    {!!item.badgeKey && <span className="nav-badge">{navCounts[item.badgeKey] || 0}</span>}
                  </button>
                </div>
              );
            })}
          </div>

          {overflowFrom < NAV.length && (
            <OverflowNavBtnSU
              items={NAV.slice(overflowFrom)}
              activeView={activeView}
              onNavigate={onNavigate}
              navCounts={navCounts}
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
                <Link href="/superuser/profile" onClick={() => setUserMenuOpen(false)} className="user-menu-item" style={{ textDecoration: 'none' }}>
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                  Profile
                </Link>
                <button onClick={() => { setUserMenuOpen(false); onNavigate('settings'); }} className="user-menu-item">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 00.34 1.87l.06.06a2 2 0 01-2.83 2.83l-.06-.06a1.7 1.7 0 00-1.87-.34 1.7 1.7 0 00-1 1.54V22a2 2 0 01-4 0v-.09a1.7 1.7 0 00-1-1.54 1.7 1.7 0 00-1.87.34l-.06.06a2 2 0 01-2.83-2.83l.06-.06a1.7 1.7 0 00.34-1.87 1.7 1.7 0 00-1.54-1H2a2 2 0 010-4h.09a1.7 1.7 0 001.54-1 1.7 1.7 0 00-.34-1.87l-.06-.06a2 2 0 012.83-2.83l.06.06a1.7 1.7 0 001.87.34h.01a1.7 1.7 0 001-1.54V2a2 2 0 014 0v.09a1.7 1.7 0 001 1.54h.01a1.7 1.7 0 001.87-.34l.06-.06a2 2 0 012.83 2.83l-.06.06a1.7 1.7 0 00-.34 1.87v.01a1.7 1.7 0 001.54 1H22a2 2 0 010 4h-.09a1.7 1.7 0 00-1.54 1z"/></svg>
                  Settings
                </button>
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
          <div className="topbar-title">{title} {subtitle && <span>- {subtitle}</span>}</div>
          <div style={{ fontSize: 12, color: 'var(--text-muted)', background: 'var(--bg-elevated)', padding: '4px 12px', borderRadius: 20, border: '1px solid var(--border)', display: 'flex', alignItems: 'center', gap: 6, whiteSpace: 'nowrap' }}>
            <span style={{ width: 6, height: 6, background: 'var(--green)', borderRadius: '50%', display: 'inline-block' }}></span>
            All systems operational
          </div>
          <div
            onClick={refreshRate}
            title="Click to refresh rate"
            style={{ fontSize: 11, color: 'var(--text-muted)', background: 'var(--bg-elevated)', padding: '4px 10px', borderRadius: 20, border: '1px solid var(--border)', cursor: 'pointer', whiteSpace: 'nowrap' }}
          >
            {`1 USD = ${Math.round(rate).toLocaleString()} TZS · ${sourceLabel}`}
          </div>
          <button className="icon-btn" onClick={() => setTheme((t) => t === 'dark' ? 'light' : 'dark')} title="Toggle theme">
            {theme === 'dark'
              ? <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/></svg>
              : <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>}
          </button>
          <NotificationBell onNavigate={onNavigate} />
        </header>

        <div className="content">{children}</div>
      </div>
    </div>
  );
}
