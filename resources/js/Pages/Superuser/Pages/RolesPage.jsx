import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';

const DEFAULT_PERMS = {
  manager:   ['Dashboard','Units','Tenants','Leases','Payments','Invoices','Reports'],
  accountant:['Dashboard','Tenants','Leases','Payments','Accounting','Reports'],
  viewer:    ['Dashboard','Units','Tenants','Reports'],
};

const ALL_MODULES = ['Dashboard','Units','Tenants','Leases','Payments','Invoices','Accounting','Reports','Maintenance','Documents','Electricity'];

function parsePerms(settings) {
  try {
    if (settings?.role_permissions) {
      const parsed = JSON.parse(settings.role_permissions);
      if (parsed && typeof parsed === 'object') return parsed;
    }
  } catch (_) { /* ignore */ }
  return DEFAULT_PERMS;
}

export default function RolesPage({ settings = {} }) {
  const [permissions, setPermissions] = useState(() => parsePerms(settings));
  const [processing, setProcessing]   = useState(false);
  const [submitMessage, setSubmitMessage] = useState('');

  useEffect(() => {
    if (!submitMessage) return;
    const t = setTimeout(() => setSubmitMessage(''), 4000);
    return () => clearTimeout(t);
  }, [submitMessage]);

  const toggle = (role, module) => {
    setPermissions(prev => {
      const current = prev[role] ?? [];
      const next    = current.includes(module)
        ? current.filter(m => m !== module)
        : [...current, module];
      return { ...prev, [role]: next };
    });
  };

  const save = () => {
    setProcessing(true);
    router.patch('/superuser/roles', { permissions }, {
      preserveScroll: true,
      onSuccess: () => setSubmitMessage('Role permissions saved.'),
      onFinish:  () => setProcessing(false),
    });
  };

  const renderRole = (role, title, sub, className) => {
    const enabled = permissions[role] ?? [];
    return (
      <div className="card" key={role}>
        <div className="card-header">
          <div><div className="card-title">{title}</div><div className="card-sub">{sub}</div></div>
          <span className={`role-tag ${className}`}>{title.split(' ')[0]}</span>
        </div>
        <div style={{ padding: '0 16px' }}>
          {ALL_MODULES.map(mod => (
            <div className="perm-row" key={mod}>
              <div><div className="perm-label">{mod}</div></div>
              <button
                type="button"
                className={`toggle ${enabled.includes(mod) ? 'on' : 'off'}`}
                onClick={() => toggle(role, mod)}
              />
            </div>
          ))}
        </div>
      </div>
    );
  };

  return (
    <>
      <div className="page-header">
        <div><div className="page-title">Roles & Permissions</div><div className="page-sub">Configure what each role can see and do</div></div>
        <div className="ph-actions">
          <button type="button" className="btn-primary" onClick={save} disabled={processing}>
            {processing ? <><span className="btn-spinner" />Saving…</> : 'Save Changes'}
          </button>
        </div>
      </div>

      {submitMessage && (
        <div className="alert-success" style={{ marginBottom: 16 }}>{submitMessage}</div>
      )}

      <div className="grid-3">
        {renderRole('manager',    'Property Manager', 'Full property operations', 'manager')}
        {renderRole('accountant', 'Accountant',       'Finance & reporting access', 'accountant')}
        {renderRole('viewer',     'Viewer',           'Read-only access', 'viewer')}
      </div>
    </>
  );
}
