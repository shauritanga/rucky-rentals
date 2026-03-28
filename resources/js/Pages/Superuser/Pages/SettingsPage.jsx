import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';

function Toggle({ value, onChange }) {
  return (
    <button
      type="button"
      className={`toggle ${value ? 'on' : 'off'}`}
      onClick={() => onChange(!value)}
    />
  );
}

export default function SettingsPage({ settings = {} }) {
  const [tab, setTab] = useState('general');
  const [submitMessage, setSubmitMessage] = useState('');
  const [processing, setProcessing] = useState(false);

  // ── General — Platform Identity ──────────────────────────────────────────
  const [identity, setIdentity] = useState({
    company_name:         settings.company_name         ?? 'Rucky Rentals Ltd',
    company_registration: settings.company_registration ?? '',
    vat_number:           settings.vat_number           ?? '',
    default_currency:     settings.default_currency     ?? 'TZS',
    default_country:      settings.default_country      ?? 'Tanzania',
    support_email:        settings.support_email        ?? '',
  });

  // ── General — Lease Policy ───────────────────────────────────────────────
  const [lease, setLease] = useState({
    min_lease_months:             settings.min_lease_months             ?? '12',
    deposit_rent_months:          settings.deposit_rent_months          ?? '1',
    deposit_service_charge_months: settings.deposit_service_charge_months ?? '1',
    late_fee_days:                settings.late_fee_days                ?? '7',
    late_fee_percent:             settings.late_fee_percent             ?? '5',
    expiry_warning_days:          settings.expiry_warning_days          ?? '60',
    auto_renew:                   settings.auto_renew                   ?? 'no',
  });

  // ── Security ─────────────────────────────────────────────────────────────
  const [security, setSecurity] = useState({
    require_2fa:         settings.require_2fa         === '1',
    allow_sso:           settings.allow_sso           === '1',
    session_timeout:     settings.session_timeout     ?? '30',
    audit_logging:       settings.audit_logging       === '1',
    failed_login_alerts: settings.failed_login_alerts === '1',
  });

  // ── Notifications ────────────────────────────────────────────────────────
  const [notifs, setNotifs] = useState({
    notif_new_property:    settings.notif_new_property    === '1',
    notif_manager_changes: settings.notif_manager_changes === '1',
    notif_failed_logins:   settings.notif_failed_logins   === '1',
    notif_lease_approved:  settings.notif_lease_approved  === '1',
    notif_overdue_rent:    settings.notif_overdue_rent    === '1',
    notif_system_errors:   settings.notif_system_errors   === '1',
  });

  // Auto-dismiss success banner
  useEffect(() => {
    if (!submitMessage) return;
    const t = setTimeout(() => setSubmitMessage(''), 4000);
    return () => clearTimeout(t);
  }, [submitMessage]);

  const save = (payload) => {
    setProcessing(true);
    router.patch('/superuser/settings', payload, {
      preserveScroll: true,
      onSuccess: () => setSubmitMessage('Settings saved successfully.'),
      onFinish: () => setProcessing(false),
    });
  };

  const saveIdentity  = () => save(identity);
  const saveLease     = () => save(lease);
  const saveSecurity  = () => save({
    ...security,
    require_2fa:         security.require_2fa         ? '1' : '0',
    allow_sso:           security.allow_sso           ? '1' : '0',
    audit_logging:       security.audit_logging       ? '1' : '0',
    failed_login_alerts: security.failed_login_alerts ? '1' : '0',
  });
  const saveNotifs    = () => save({
    notif_new_property:    notifs.notif_new_property    ? '1' : '0',
    notif_manager_changes: notifs.notif_manager_changes ? '1' : '0',
    notif_failed_logins:   notifs.notif_failed_logins   ? '1' : '0',
    notif_lease_approved:  notifs.notif_lease_approved  ? '1' : '0',
    notif_overdue_rent:    notifs.notif_overdue_rent    ? '1' : '0',
    notif_system_errors:   notifs.notif_system_errors   ? '1' : '0',
  });

  return (
    <>
      <div className="page-header">
        <div><div className="page-title">System Settings</div><div className="page-sub">Global configuration for the Rucky Rentals platform</div></div>
      </div>

      {submitMessage && (
        <div className="alert-success" style={{ marginBottom: 16 }}>
          {submitMessage}
        </div>
      )}

      <div className="settings-tabs">
        <button type="button" className={`stab ${tab === 'general'       ? 'active' : ''}`} onClick={() => setTab('general')}>General</button>
        <button type="button" className={`stab ${tab === 'security'      ? 'active' : ''}`} onClick={() => setTab('security')}>Security</button>
        <button type="button" className={`stab ${tab === 'billing'       ? 'active' : ''}`} onClick={() => setTab('billing')}>Subscription</button>
        <button type="button" className={`stab ${tab === 'notifications' ? 'active' : ''}`} onClick={() => setTab('notifications')}>Notifications</button>
        <button type="button" className={`stab ${tab === 'backup'        ? 'active' : ''}`} onClick={() => setTab('backup')}>Backup & Data</button>
      </div>

      {tab === 'general' && (
        <div className="grid-2" style={{ gridTemplateColumns: '1fr 1fr' }}>
          {/* Platform Identity */}
          <div className="card" style={{ padding: 20 }}>
            <div className="card-title" style={{ marginBottom: 12 }}>Platform Identity</div>
            <div className="form-row">
              <div className="form-group">
                <label className="form-label">Company Name</label>
                <input className="form-input" value={identity.company_name} onChange={e => setIdentity(p => ({ ...p, company_name: e.target.value }))} />
              </div>
            </div>
            <div className="form-row">
              <div className="form-group">
                <label className="form-label">Company Registration No.</label>
                <input className="form-input" value={identity.company_registration} onChange={e => setIdentity(p => ({ ...p, company_registration: e.target.value }))} />
              </div>
              <div className="form-group">
                <label className="form-label">VAT Number</label>
                <input className="form-input" value={identity.vat_number} onChange={e => setIdentity(p => ({ ...p, vat_number: e.target.value }))} />
              </div>
            </div>
            <div className="form-row">
              <div className="form-group">
                <label className="form-label">Default Currency</label>
                <select className="form-input form-select" value={identity.default_currency} onChange={e => setIdentity(p => ({ ...p, default_currency: e.target.value }))}>
                  <option value="TZS">TZS - Tanzanian Shilling</option>
                  <option value="USD">USD - US Dollar</option>
                  <option value="EUR">EUR - Euro</option>
                  <option value="KES">KES - Kenyan Shilling</option>
                </select>
              </div>
              <div className="form-group">
                <label className="form-label">Default Country</label>
                <select className="form-input form-select" value={identity.default_country} onChange={e => setIdentity(p => ({ ...p, default_country: e.target.value }))}>
                  <option value="Tanzania">Tanzania</option>
                  <option value="Kenya">Kenya</option>
                  <option value="Uganda">Uganda</option>
                </select>
              </div>
            </div>
            <div className="form-row">
              <div className="form-group">
                <label className="form-label">Support Email</label>
                <input className="form-input" type="email" value={identity.support_email} onChange={e => setIdentity(p => ({ ...p, support_email: e.target.value }))} />
              </div>
            </div>
            <button type="button" className="btn-primary" onClick={saveIdentity} disabled={processing}>
              {processing ? <><span className="btn-spinner" />Saving…</> : 'Save Changes'}
            </button>
          </div>

          {/* Lease Policy */}
          <div className="card" style={{ padding: 20 }}>
            <div className="card-title" style={{ marginBottom: 12 }}>Lease Policy Defaults</div>
            <div className="form-row">
              <div className="form-group">
                <label className="form-label">Minimum Lease Duration (months)</label>
                <input className="form-input" type="number" min="1" value={lease.min_lease_months} onChange={e => setLease(p => ({ ...p, min_lease_months: e.target.value }))} />
              </div>
              <div className="form-group">
                <label className="form-label">Security Deposit: Rent (months)</label>
                <input className="form-input" type="number" min="0" step="0.5" value={lease.deposit_rent_months} onChange={e => setLease(p => ({ ...p, deposit_rent_months: e.target.value }))} />
              </div>
            </div>
            <div className="form-row">
              <div className="form-group">
                <label className="form-label">Security Deposit: Service Charge (months)</label>
                <input className="form-input" type="number" min="0" step="0.5" value={lease.deposit_service_charge_months} onChange={e => setLease(p => ({ ...p, deposit_service_charge_months: e.target.value }))} />
              </div>
            </div>
            <div className="form-row">
              <div className="form-group">
                <label className="form-label">Late Payment Grace Period (days)</label>
                <input className="form-input" type="number" min="0" value={lease.late_fee_days} onChange={e => setLease(p => ({ ...p, late_fee_days: e.target.value }))} />
              </div>
              <div className="form-group">
                <label className="form-label">Late Payment Penalty (%)</label>
                <input className="form-input" type="number" min="0" step="0.5" value={lease.late_fee_percent} onChange={e => setLease(p => ({ ...p, late_fee_percent: e.target.value }))} />
              </div>
            </div>
            <div className="form-row">
              <div className="form-group">
                <label className="form-label">Lease Expiry Warning (days before)</label>
                <input className="form-input" type="number" min="0" value={lease.expiry_warning_days} onChange={e => setLease(p => ({ ...p, expiry_warning_days: e.target.value }))} />
              </div>
              <div className="form-group">
                <label className="form-label">Auto-Renew by Default</label>
                <select className="form-input form-select" value={lease.auto_renew} onChange={e => setLease(p => ({ ...p, auto_renew: e.target.value }))}>
                  <option value="no">No - manual renewal</option>
                  <option value="yes">Yes - auto-renew</option>
                </select>
              </div>
            </div>
            <button type="button" className="btn-primary" onClick={saveLease} disabled={processing}>
              {processing ? <><span className="btn-spinner" />Saving…</> : 'Save Changes'}
            </button>
          </div>
        </div>
      )}

      {tab === 'security' && (
        <div className="grid-2" style={{ gridTemplateColumns: '1fr 1fr' }}>
          <div className="card" style={{ padding: 20 }}>
            <div className="card-title" style={{ marginBottom: 12 }}>Authentication</div>
            <div className="perm-row">
              <div><div className="perm-label">Two-Factor Authentication (2FA)</div><div className="perm-sub">Require 2FA for all users</div></div>
              <Toggle value={security.require_2fa} onChange={v => setSecurity(p => ({ ...p, require_2fa: v }))} />
            </div>
            <div className="perm-row">
              <div><div className="perm-label">Single Sign-On (SSO)</div><div className="perm-sub">Allow Google/Microsoft SSO login</div></div>
              <Toggle value={security.allow_sso} onChange={v => setSecurity(p => ({ ...p, allow_sso: v }))} />
            </div>
            <div className="perm-row">
              <div><div className="perm-label">Session Timeout</div><div className="perm-sub">Auto-logout after inactivity</div></div>
              <select className="form-input form-select" style={{ width: 130, padding: '5px 28px 5px 10px', fontSize: 12.5 }} value={security.session_timeout} onChange={e => setSecurity(p => ({ ...p, session_timeout: e.target.value }))}>
                <option value="30">30 minutes</option>
                <option value="60">1 hour</option>
                <option value="240">4 hours</option>
              </select>
            </div>
            <div style={{ marginTop: 14 }}>
              <button type="button" className="btn-primary" onClick={saveSecurity} disabled={processing}>
                {processing ? <><span className="btn-spinner" />Saving…</> : 'Save Changes'}
              </button>
            </div>
          </div>

          <div className="card" style={{ padding: 20 }}>
            <div className="card-title" style={{ marginBottom: 12 }}>Audit & Compliance</div>
            <div className="perm-row">
              <div><div className="perm-label">Full Audit Logging</div><div className="perm-sub">Log all user actions</div></div>
              <Toggle value={security.audit_logging} onChange={v => setSecurity(p => ({ ...p, audit_logging: v }))} />
            </div>
            <div className="perm-row">
              <div><div className="perm-label">Failed Login Alerts</div><div className="perm-sub">Email superuser on 3+ failed attempts</div></div>
              <Toggle value={security.failed_login_alerts} onChange={v => setSecurity(p => ({ ...p, failed_login_alerts: v }))} />
            </div>
            <div className="perm-row">
              <div><div className="perm-label">Data Encryption at Rest</div><div className="perm-sub">AES-256 for all stored data</div></div>
              <Toggle value={true} onChange={() => {}} />
            </div>
          </div>
        </div>
      )}

      {tab === 'billing' && (
        <div className="card" style={{ padding: 20 }}>
          <div style={{ fontSize: 18, fontWeight: 800, marginBottom: 6 }}>Enterprise Plan</div>
          <div style={{ color: 'var(--text-secondary)', marginBottom: 10 }}>Up to 500 units · Unlimited managers · SLA 99.9%</div>
          <div style={{ fontSize: 22, fontWeight: 800 }}>TZS 6,500,000<span style={{ fontSize: 13, fontWeight: 400, color: 'var(--text-muted)' }}>/month</span></div>
        </div>
      )}

      {tab === 'notifications' && (
        <div className="card" style={{ padding: '0 20px' }}>
          <div className="card-header" style={{ paddingLeft: 0, paddingRight: 0 }}>
            <div className="card-title">Notification Rules</div>
            <div className="card-sub">Configure what alerts are sent to the superuser</div>
          </div>
          {[
            ['notif_new_property',    'New property added',                      'Email + in-app'],
            ['notif_manager_changes', 'Manager account created or suspended',    'Email + in-app'],
            ['notif_failed_logins',   'Failed login attempts (3+)',              'Immediate alert'],
            ['notif_lease_approved',  'Lease approved (all properties)',         'Daily digest'],
            ['notif_overdue_rent',    'Overdue rent (portfolio-wide)',           'Weekly summary'],
            ['notif_system_errors',   'System errors or downtime',              'Immediate email + SMS'],
          ].map(([key, label, sub]) => (
            <div className="perm-row" key={key}>
              <div><div className="perm-label">{label}</div><div className="perm-sub">{sub}</div></div>
              <Toggle value={notifs[key]} onChange={v => setNotifs(p => ({ ...p, [key]: v }))} />
            </div>
          ))}
          <div style={{ padding: '14px 0' }}>
            <button type="button" className="btn-primary" onClick={saveNotifs} disabled={processing}>
              {processing ? <><span className="btn-spinner" />Saving…</> : 'Save Settings'}
            </button>
          </div>
        </div>
      )}

      {tab === 'backup' && (
        <div className="grid-2" style={{ gridTemplateColumns: '1fr 1fr' }}>
          <div className="card" style={{ padding: 20 }}>
            <div className="perm-row">
              <div><div className="perm-label">Automatic Backups</div><div className="perm-sub">Daily automated backup</div></div>
              <Toggle value={true} onChange={() => {}} />
            </div>
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
                <tr><td>Auto</td><td>—</td><td>Auto</td><td><span className="badge active">Complete</span></td></tr>
              </tbody>
            </table>
          </div>
        </div>
      )}
    </>
  );
}
