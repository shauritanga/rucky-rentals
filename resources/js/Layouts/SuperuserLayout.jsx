import { useState, useEffect } from 'react';
import { Link, usePage } from '@inertiajs/react';
import useExchangeRate from '@/hooks/useExchangeRate';

const NAV = [
  { id: 'overview', label: 'Overview', section: 'Main', icon: <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg> },
  { id: 'properties', label: 'Properties', section: null, badgeKey: 'properties', icon: <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg> },
  { id: 'managers', label: 'Managers', section: 'Users', badgeKey: 'managers', icon: <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg> },
  { id: 'roles', label: 'Roles & Permissions', section: null, icon: <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg> },
  { id: 'audit', label: 'Audit Trail', section: 'System', icon: <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg> },
  { id: 'settings', label: 'Settings', section: null, icon: <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 00.34 1.87l.06.06a2 2 0 01-2.83 2.83l-.06-.06a1.7 1.7 0 00-1.87-.34 1.7 1.7 0 00-1 1.54V22a2 2 0 01-4 0v-.09a1.7 1.7 0 00-1-1.54 1.7 1.7 0 00-1.87.34l-.06.06a2 2 0 01-2.83-2.83l.06-.06a1.7 1.7 0 00.34-1.87 1.7 1.7 0 00-1.54-1H2a2 2 0 010-4h.09a1.7 1.7 0 001.54-1 1.7 1.7 0 00-.34-1.87l-.06-.06a2 2 0 012.83-2.83l.06.06a1.7 1.7 0 001.87.34h.01a1.7 1.7 0 001-1.54V2a2 2 0 014 0v.09a1.7 1.7 0 001 1.54h.01a1.7 1.7 0 001.87-.34l.06-.06a2 2 0 012.83 2.83l-.06.06a1.7 1.7 0 00-.34 1.87v.01a1.7 1.7 0 001.54 1H22a2 2 0 010 4h-.09a1.7 1.7 0 00-1.54 1z"/></svg> },
];

export default function SuperuserLayout({ activeView, onNavigate, title, subtitle, actionLabel, onAction, navCounts = {}, children }) {
  const [collapsed, setCollapsed] = useState(false);
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
          <button className="icon-btn" title="Notifications">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 01-3.46 0"/></svg>
            <span className="notif-dot"></span>
          </button>
        </header>

        <div className="content">{children}</div>
      </div>
    </div>
  );
}
