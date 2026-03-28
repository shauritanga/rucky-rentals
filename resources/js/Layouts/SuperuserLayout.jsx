import { useState, useEffect, useRef } from 'react';
import { Link, usePage } from '@inertiajs/react';
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

    // Subscribe to real-time notifications via Reverb WebSocket
    useEffect(() => {
        const userId = props.auth?.user?.id;
        if (!userId) return;

        // Initial fetch on mount
        fetchNotifications();

        // Listen for pushed notifications on the user's private channel
        const channel = echo.private(`App.Models.User.${userId}`);
        channel.notification(() => {
            fetchNotifications();
        });

        return () => {
            echo.leave(`App.Models.User.${userId}`);
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
        return (
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" style={{ flexShrink: 0, color: 'var(--amber)' }}>
                <path d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z"/>
            </svg>
        );
    };

    const getTitle = (data) => {
        if (data?.type === 'lease_approval_request') return 'Lease Approval Request';
        if (data?.stage === 'submitted') return 'Maintenance Request';
        if (data?.stage === 'pending_manager') return 'Maintenance Pending Review';
        return 'New Request';
    };

    const getSub = (data) => {
        if (data?.type === 'lease_approval_request') return [data.tenant, data.property].filter(Boolean).join(' · ');
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
  { id: 'managers', label: 'Managers', section: 'Users', badgeKey: 'managers', icon: <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg> },
  { id: 'roles', label: 'Roles & Permissions', section: null, icon: <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg> },
  { id: 'approvals', label: 'Approvals', section: 'System', badgeKey: 'approvals', icon: <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> },
  { id: 'audit', label: 'Audit Trail', section: null, icon: <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg> },
  { id: 'settings', label: 'Settings', section: null, icon: <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 00.34 1.87l.06.06a2 2 0 01-2.83 2.83l-.06-.06a1.7 1.7 0 00-1.87-.34 1.7 1.7 0 00-1 1.54V22a2 2 0 01-4 0v-.09a1.7 1.7 0 00-1-1.54 1.7 1.7 0 00-1.87.34l-.06.06a2 2 0 01-2.83-2.83l.06-.06a1.7 1.7 0 00.34-1.87 1.7 1.7 0 00-1.54-1H2a2 2 0 010-4h.09a1.7 1.7 0 001.54-1 1.7 1.7 0 00-.34-1.87l-.06-.06a2 2 0 012.83-2.83l.06.06a1.7 1.7 0 001.87.34h.01a1.7 1.7 0 001-1.54V2a2 2 0 014 0v.09a1.7 1.7 0 001 1.54h.01a1.7 1.7 0 001.87-.34l.06-.06a2 2 0 012.83 2.83l-.06.06a1.7 1.7 0 00-.34 1.87v.01a1.7 1.7 0 001.54 1H22a2 2 0 010 4h-.09a1.7 1.7 0 00-1.54 1z"/></svg> },
];

export default function SuperuserLayout({ activeView, onNavigate, title, subtitle, actionLabel, onAction, navCounts = {}, children }) {
  const [collapsed, setCollapsed] = useState(() => localStorage.getItem('sidebar-collapsed') === 'true');
  useEffect(() => { localStorage.setItem('sidebar-collapsed', collapsed); }, [collapsed]);
  const [theme, setTheme] = useState('dark');
  const { props } = usePage();
  const { rate, sourceLabel, refreshRate } = useExchangeRate();
  const user = props?.auth?.user;
  const displayName = user?.name || 'Super Admin';
  const roleLabel = user?.role ? user.role.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()) : 'System Owner';
  const initials = displayName
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((s) => s[0])
    .join('')
    .toUpperCase() || 'SA';

  useEffect(() => {
    document.documentElement.setAttribute('data-theme', theme);
  }, [theme]);

  let lastSection = null;

  return (
    <div className="app-layout superuser-shell">
      <aside className={`sidebar ${collapsed ? 'collapsed' : ''}`}>
        <div className="sidebar-header">
          <div className="logo-mark">R</div>
          <span className="logo-text">Rucky Rentals</span>
        </div>

        <nav className="nav">
          {NAV.map((item) => {
            const showSection = item.section && item.section !== lastSection;
            if (item.section) lastSection = item.section;
            return (
              <div key={item.id}>
                {showSection && <span className="nav-section-label">{item.section}</span>}
                <button
                  type="button"
                  className={`nav-item ${activeView === item.id ? 'active' : ''}`}
                  onClick={() => onNavigate(item.id)}
                  style={{ width: '100%', textAlign: 'left', border: 'none', background: 'none' }}
                  data-tooltip={item.label}
                >
                  {item.icon}
                  <span className="nav-label">{item.label}</span>
                  {!!item.badgeKey && <span className="nav-badge">{navCounts[item.badgeKey] || 0}</span>}
                </button>
              </div>
            );
          })}
        </nav>

        <div className="sidebar-footer">
          <Link href="/superuser/profile" className="user-card" style={{ textDecoration: 'none', color: 'inherit' }}>
            <div className="avatar" style={{ overflow: 'hidden', padding: 0 }}>
              {user?.avatar_url
                ? <img src={user.avatar_url} alt={displayName} style={{ width: '100%', height: '100%', objectFit: 'cover', borderRadius: 'inherit' }} />
                : initials}
            </div>
            <div className="user-info">
              <div className="user-name">{displayName}</div>
              <div className="user-role">{roleLabel}</div>
            </div>
            <svg style={{ flexShrink: 0, marginLeft: 'auto', opacity: .4 }} width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg>
          </Link>
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
