import { useState } from 'react';

export default function SettingsPage() {
  const [tab, setTab] = useState('general');

  return (
    <>
      <div className="page-header">
        <div><div className="page-title">System Settings</div><div className="page-sub">Global configuration for the Rucky Rentals platform</div></div>
      </div>

      <div className="settings-tabs">
        <button type="button" className={`stab ${tab === 'general' ? 'active' : ''}`} onClick={() => setTab('general')}>General</button>
        <button type="button" className={`stab ${tab === 'security' ? 'active' : ''}`} onClick={() => setTab('security')}>Security</button>
        <button type="button" className={`stab ${tab === 'billing' ? 'active' : ''}`} onClick={() => setTab('billing')}>Subscription</button>
        <button type="button" className={`stab ${tab === 'notifications' ? 'active' : ''}`} onClick={() => setTab('notifications')}>Notifications</button>
        <button type="button" className={`stab ${tab === 'backup' ? 'active' : ''}`} onClick={() => setTab('backup')}>Backup & Data</button>
      </div>

      {tab === 'general' && (
        <div className="grid-2" style={{ gridTemplateColumns: '1fr 1fr' }}>
          <div className="card" style={{ padding: 20 }}>
            <div className="card-title" style={{ marginBottom: 12 }}>Platform Identity</div>
            <div className="form-row"><div className="form-group"><label className="form-label">Company Name</label><input className="form-input" defaultValue="Rucky Rentals Ltd" /></div></div>
            <div className="form-row"><div className="form-group"><label className="form-label">Company Registration No.</label><input className="form-input" defaultValue="CPR-2021-00482" /></div><div className="form-group"><label className="form-label">VAT Number</label><input className="form-input" defaultValue="P051234567M" /></div></div>
            <div className="form-row"><div className="form-group"><label className="form-label">Default Currency</label><select className="form-input form-select"><option>TZS - Tanzanian Shilling</option><option>USD - US Dollar</option><option>EUR - Euro</option></select></div><div className="form-group"><label className="form-label">Default Country</label><select className="form-input form-select"><option>Tanzania</option><option>Kenya</option><option>Uganda</option></select></div></div>
            <div className="form-row"><div className="form-group"><label className="form-label">Support Email</label><input className="form-input" defaultValue="support@ruckyrentals.co.tz" /></div></div>
            <button type="button" className="btn-primary">Save Changes</button>
          </div>
          <div className="card" style={{ padding: 20 }}>
            <div className="card-title" style={{ marginBottom: 12 }}>Lease Policy Defaults</div>
            <div className="form-row"><div className="form-group"><label className="form-label">Minimum Lease Duration (months)</label><input className="form-input" defaultValue="12" /></div><div className="form-group"><label className="form-label">Default Security Deposit (x rent)</label><input className="form-input" defaultValue="2" /></div></div>
            <div className="form-row"><div className="form-group"><label className="form-label">Late Payment Grace Period (days)</label><input className="form-input" defaultValue="7" /></div><div className="form-group"><label className="form-label">Late Payment Penalty (%)</label><input className="form-input" defaultValue="5" /></div></div>
            <div className="form-row"><div className="form-group"><label className="form-label">Lease Expiry Warning (days before)</label><input className="form-input" defaultValue="60" /></div><div className="form-group"><label className="form-label">Auto-Renew by Default</label><select className="form-input form-select"><option>No - manual renewal</option><option>Yes - auto-renew</option></select></div></div>
            <button type="button" className="btn-primary">Save Changes</button>
          </div>
        </div>
      )}

      {tab === 'security' && (
        <div className="grid-2" style={{ gridTemplateColumns: '1fr 1fr' }}>
          <div className="card" style={{ padding: 20 }}>
            <div className="card-title" style={{ marginBottom: 12 }}>Authentication</div>
            <div className="perm-row"><div><div className="perm-label">Two-Factor Authentication (2FA)</div><div className="perm-sub">Require 2FA for all users</div></div><button type="button" className="toggle on"></button></div>
            <div className="perm-row"><div><div className="perm-label">Single Sign-On (SSO)</div><div className="perm-sub">Allow Google/Microsoft SSO login</div></div><button type="button" className="toggle off"></button></div>
            <div className="perm-row"><div><div className="perm-label">Session Timeout</div><div className="perm-sub">Auto-logout after inactivity</div></div><select className="form-input form-select" style={{ width: 120, padding: '5px 28px 5px 10px', fontSize: 12.5 }}><option>30 minutes</option><option>1 hour</option><option>4 hours</option></select></div>
          </div>
          <div className="card" style={{ padding: 20 }}>
            <div className="card-title" style={{ marginBottom: 12 }}>Audit & Compliance</div>
            <div className="perm-row"><div><div className="perm-label">Full Audit Logging</div><div className="perm-sub">Log all user actions</div></div><button type="button" className="toggle on"></button></div>
            <div className="perm-row"><div><div className="perm-label">Failed Login Alerts</div><div className="perm-sub">Email superuser on 3+ failed attempts</div></div><button type="button" className="toggle on"></button></div>
            <div className="perm-row"><div><div className="perm-label">Data Encryption at Rest</div><div className="perm-sub">AES-256 for all stored data</div></div><button type="button" className="toggle on"></button></div>
          </div>
        </div>
      )}

      {tab === 'billing' && (
        <div className="card" style={{ padding: 20 }}>
          <div style={{ fontSize: 18, fontWeight: 800, marginBottom: 6 }}>Enterprise Plan</div>
          <div style={{ color: 'var(--text-secondary)', marginBottom: 10 }}>Up to 500 units - Unlimited managers - SLA 99.9%</div>
          <div style={{ fontSize: 22, fontWeight: 800 }}>TZS 6,500,000<span style={{ fontSize: 13, fontWeight: 400, color: 'var(--text-muted)' }}>/month</span></div>
        </div>
      )}

      {tab === 'notifications' && (
        <div className="card" style={{ padding: '0 20px' }}>
          <div className="card-header" style={{ paddingLeft: 0, paddingRight: 0 }}><div className="card-title">Notification Rules</div><div className="card-sub">Configure what alerts are sent to the superuser</div></div>
          <div className="perm-row"><div><div className="perm-label">New property added</div><div className="perm-sub">Email + in-app</div></div><button type="button" className="toggle on"></button></div>
          <div className="perm-row"><div><div className="perm-label">Manager account created or suspended</div><div className="perm-sub">Email + in-app</div></div><button type="button" className="toggle on"></button></div>
          <div className="perm-row"><div><div className="perm-label">Failed login attempts (3+)</div><div className="perm-sub">Immediate alert</div></div><button type="button" className="toggle on"></button></div>
          <div className="perm-row"><div><div className="perm-label">Lease approved (all properties)</div><div className="perm-sub">Daily digest</div></div><button type="button" className="toggle off"></button></div>
          <div className="perm-row"><div><div className="perm-label">Overdue rent (portfolio-wide)</div><div className="perm-sub">Weekly summary</div></div><button type="button" className="toggle on"></button></div>
          <div className="perm-row"><div><div className="perm-label">System errors or downtime</div><div className="perm-sub">Immediate email + SMS</div></div><button type="button" className="toggle on"></button></div>
          <div style={{ padding: '14px 0' }}><button type="button" className="btn-primary">Save Settings</button></div>
        </div>
      )}

      {tab === 'backup' && (
        <div className="grid-2" style={{ gridTemplateColumns: '1fr 1fr' }}>
          <div className="card" style={{ padding: 20 }}>
            <div className="perm-row"><div><div className="perm-label">Automatic Backups</div><div className="perm-sub">Daily automated backup</div></div><button type="button" className="toggle on"></button></div>
            <div style={{ marginTop: 14, display: 'flex', gap: 8 }}>
              <button type="button" className="btn-primary">Backup Now</button>
              <button type="button" className="btn-ghost">Restore</button>
            </div>
          </div>
          <div className="card">
            <div className="card-header"><div className="card-title">Recent Backups</div></div>
            <table className="data-table">
              <thead><tr><th>Date</th><th>Size</th><th>Type</th><th>Status</th></tr></thead>
              <tbody>
                <tr><td>Mar 19, 2026 03:00</td><td>48.2 MB</td><td>Auto</td><td><span className="badge active">Complete</span></td></tr>
                <tr><td>Mar 18, 2026 03:00</td><td>47.8 MB</td><td>Auto</td><td><span className="badge active">Complete</span></td></tr>
                <tr><td>Mar 17, 2026 03:00</td><td>47.5 MB</td><td>Auto</td><td><span className="badge active">Complete</span></td></tr>
                <tr><td>Mar 16, 2026 14:32</td><td>47.1 MB</td><td>Manual</td><td><span className="badge active">Complete</span></td></tr>
              </tbody>
            </table>
          </div>
        </div>
      )}
    </>
  );
}
