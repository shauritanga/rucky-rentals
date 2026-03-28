import { useState, useEffect } from 'react';
import { Link, usePage, router } from '@inertiajs/react';
import useExchangeRate from '@/hooks/useExchangeRate';

const NAV = [
  { label: 'Dashboard',    href: '/',            icon: <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg>, section: 'Overview' },
  { label: 'Units',        href: '/units',        icon: <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>, section: null },
  { label: 'Tenants',      href: '/tenants',      icon: <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>, section: null },
  { label: 'Leases',       href: '/leases',       icon: <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>, section: 'Operations' },
  { label: 'Electricity',  href: '/electricity',  icon: <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>, section: null },
  { label: 'Maintenance',  href: '/maintenance',  icon: <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><path d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z"/></svg>, section: null },
  { label: 'Documents',    href: '/documents',    icon: <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>, section: null },
  { label: 'Payments',     href: '/payments',     icon: <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>, section: 'Finance' },
  { label: 'Invoices',     href: '/invoices',     icon: <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>, section: null },
  { label: 'Accounting',   href: '/accounting',   icon: <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>, section: null },
  { label: 'Reports',      href: '/reports',      icon: <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>, section: null },
  { label: 'Team',         href: '/team',         icon: <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>, section: 'Administration', badge: '0' },
  { label: 'Audit Trail',  href: '/audit',        icon: <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><line x1="10" y1="9" x2="8" y2="9"/></svg>, section: null, badge: '!', badgeStyle: { background: 'var(--red)', display: 'none' } },
];

export default function AppLayout({ children, title, subtitle }) {
  const [collapsed, setCollapsed] = useState(() => localStorage.getItem('sidebar-collapsed') === 'true');
  useEffect(() => { localStorage.setItem('sidebar-collapsed', collapsed); }, [collapsed]);
  const [theme, setTheme] = useState(() => localStorage.getItem('app-theme') || 'dark');
  const { url, props } = usePage();
  const { rate, sourceLabel, refreshRate } = useExchangeRate();
  const user = props?.auth?.user;
  const viewingProperty = props?.viewing_property ?? null;
  const displayName = user?.name || 'User';
  const roleLabel = user?.role ? user.role.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()) : 'User';
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
          <span className="logo-text">Rucky Rentals</span>
        </div>

        <nav className="nav">
          {NAV.map((item, index) => {
            const previousSection = index > 0 ? NAV[index - 1].section : null;
            const showSection = Boolean(item.section) && item.section !== previousSection;
            return (
              <div key={item.href ?? item.label}>
                {showSection && <span className="nav-section-label">{item.section}</span>}
                <Link href={item.href} className={`nav-item ${isActive(item.href) ? 'active' : ''}`} data-tooltip={item.label}>
                  {item.icon}
                  <span className="nav-label">{item.label}</span>
                  {item.badge && <span className="nav-badge" style={item.badgeStyle}>{item.badge}</span>}
                </Link>
              </div>
            );
          })}
        </nav>

        <div className="sidebar-footer">
          <Link href="/profile" className="user-card" style={{ textDecoration: 'none', color: 'inherit' }}>
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
            <span>Superuser acting as Manager —</span>
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
