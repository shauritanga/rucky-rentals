import { Link } from '@inertiajs/react';

export default function ReportTabs({ tabs, active }) {
  return (
    <div className="reports-tabbar" style={{ display: 'flex', gap: 2, marginBottom: 20, borderBottom: '1px solid var(--border)' }}>
      {tabs.map((tab) => {
        const isActive = active === tab.key;
        const style = {
          padding: '8px 16px',
          fontSize: 13,
          fontWeight: isActive ? 600 : 400,
          color: isActive ? 'var(--accent)' : 'var(--text-secondary)',
          background: 'none',
          border: 'none',
          borderBottom: isActive ? '2px solid var(--accent)' : '2px solid transparent',
          cursor: 'pointer',
          marginBottom: -1,
          textDecoration: 'none',
          display: 'inline-block',
        };

        return tab.href ? (
          <Link key={tab.key} href={tab.href} style={style}>{tab.label}</Link>
        ) : (
          <button key={tab.key} onClick={tab.onClick} style={style}>{tab.label}</button>
        );
      })}
    </div>
  );
}
