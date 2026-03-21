const PERMS = {
  manager: [
    ['Dashboard', true],
    ['Units', true],
    ['Tenants', true],
    ['Leases', true],
    ['Payments', true],
    ['Invoices', true],
    ['Reports', true],
  ],
  accountant: [
    ['Dashboard', true],
    ['Tenants', true],
    ['Leases', true],
    ['Payments', true],
    ['Accounting', true],
    ['Reports', true],
  ],
  viewer: [
    ['Dashboard', true],
    ['Units', true],
    ['Tenants', true],
    ['Reports', true],
  ],
};

export default function RolesPage() {
  const renderRole = (title, sub, className, perms) => (
    <div className="card">
      <div className="card-header">
        <div><div className="card-title">{title}</div><div className="card-sub">{sub}</div></div>
        <span className={`role-tag ${className}`}>{title.split(' ')[0]}</span>
      </div>
      <div style={{ padding: '0 16px' }}>
        {perms.map(([name, enabled]) => (
          <div className="perm-row" key={name}>
            <div><div className="perm-label">{name}</div></div>
            <button type="button" className={`toggle ${enabled ? 'on' : 'off'}`}></button>
          </div>
        ))}
      </div>
    </div>
  );

  return (
    <>
      <div className="page-header">
        <div><div className="page-title">Roles & Permissions</div><div className="page-sub">Configure what each role can see and do</div></div>
        <div className="ph-actions"><button type="button" className="btn-primary">Save Changes</button></div>
      </div>
      <div className="grid-3">
        {renderRole('Property Manager', 'Full property operations', 'manager', PERMS.manager)}
        {renderRole('Accountant', 'Finance & reporting access', 'accountant', PERMS.accountant)}
        {renderRole('Viewer', 'Read-only access', 'viewer', PERMS.viewer)}
      </div>
    </>
  );
}
