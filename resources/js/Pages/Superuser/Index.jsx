import { useEffect, useMemo, useState } from 'react';
import { generateFloors } from '@/utils/floorConfig';
import { Head, useForm, router, usePage } from '@inertiajs/react';
import SuperuserLayout from '@/Layouts/SuperuserLayout';
import OverviewPage from '@/Pages/Superuser/Pages/OverviewPage';
import PropertiesPage from '@/Pages/Superuser/Pages/PropertiesPage';
import ManagersPage from '@/Pages/Superuser/Pages/ManagersPage';
import RolesPage from '@/Pages/Superuser/Pages/RolesPage';
import AuditPage from '@/Pages/Superuser/Pages/AuditPage';
import SettingsPage from '@/Pages/Superuser/Pages/SettingsPage';
import ApprovalsPage from '@/Pages/Superuser/Pages/ApprovalsPage';


const VIEW_META = {
  overview: { title: 'Overview', subtitle: 'Superuser Console', actionLabel: 'Add Property' },
  properties: { title: 'Properties', subtitle: 'All buildings', actionLabel: 'Add Property' },
  managers: { title: 'Administrators', subtitle: 'All system users', actionLabel: 'Add User' },
  roles: { title: 'Roles & Permissions', subtitle: 'Access control', actionLabel: 'Save Changes' },
  approvals: { title: 'Approvals', subtitle: 'Pending requests', actionLabel: null },
  audit: { title: 'Audit Trail', subtitle: 'System log', actionLabel: 'Export CSV' },
  settings: { title: 'Settings', subtitle: 'System configuration', actionLabel: null },
};

export default function SuperuserIndex({ properties = [], managers = [], auditLogs = [], settings = {}, pendingLeases = [], pendingMaintenance = [], archivedManagers = [] }) {
  const [activeView, setActiveView] = useState('overview');
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('all');
  const [showModal, setShowModal] = useState(false);
  const [upperFloorsRaw, setUpperFloorsRaw] = useState('7');
  const [showManagerModal, setShowManagerModal] = useState(false);
  const [creatingManager, setCreatingManager] = useState(false);
  const [managerErrors, setManagerErrors] = useState({});
  const [toast, setToast] = useState({ msg: '', type: '' });

  const { props } = usePage();
  const flash = props.flash ?? {};

  useEffect(() => {
    if (flash.success) { setToast({ msg: flash.success, type: 'success' }); window.setTimeout(() => setToast({ msg: '', type: '' }), 3500); }
    if (flash.warning) { setToast({ msg: flash.warning, type: 'warning' }); window.setTimeout(() => setToast({ msg: '', type: '' }), 5000); }
    if (flash.error)   { setToast({ msg: flash.error,   type: 'error'   }); window.setTimeout(() => setToast({ msg: '', type: '' }), 4000); }
  }, [flash.success, flash.warning, flash.error]);

  const [managerForm, setManagerForm] = useState({
    name: '',
    email: '',
    phone: '',
    role: '',
    property_id: '',
    twoFA: 'yes',
  });

  const { data, setData, post, processing, reset } = useForm({
    name: '',
    code: '',
    address: '',
    city: 'Dar es Salaam',
    country: 'Tanzania',
    status: 'active',
    unit_count: 0,
    occupied_units: 0,
    // floor config
    basements: 0,
    has_ground_floor: false,
    has_mezzanine: false,
    upper_floors: 7,
  });

  const effectiveProperties = properties ?? [];
  const effectiveManagers   = managers   ?? [];

  const stats = useMemo(() => {
    const total = effectiveProperties.length;
    const units = effectiveProperties.reduce((sum, p) => sum + Number(p.unit_count || 0), 0);
    const occupied = effectiveProperties.reduce((sum, p) => sum + Number(p.occupied_units || 0), 0);
    const revenue = effectiveProperties.reduce((sum, p) => sum + Number(p.monthly_rent || p.revenue || 0), 0);
    return { total, units, occupied, revenue };
  }, [effectiveProperties]);

  const submit = (e) => {
    e.preventDefault();
    post('/superuser/properties', {
      preserveScroll: true,
      onSuccess: () => {
        reset();
        setData('city', 'Dar es Salaam');
        setData('country', 'Tanzania');
        setData('status', 'active');
        setData('basements', 0);
        setData('has_ground_floor', false);
        setData('has_mezzanine', false);
        setData('upper_floors', 7);
        setUpperFloorsRaw('7');
        setShowModal(false);
      },
    });
  };

  const assignManager = (propertyId, managerId) => {
    if (!managerId) return;
    router.patch(`/superuser/properties/${propertyId}/assign-manager`, { manager_user_id: managerId }, { preserveScroll: true });
  };

  const meta = VIEW_META[activeView] || VIEW_META.overview;

  const onAction = () => {
    if (activeView === 'overview' || activeView === 'properties') {
      setShowModal(true);
      return;
    }
    if (activeView === 'managers') {
      setShowManagerModal(true);
      return;
    }
    if (activeView === 'roles') return;
    if (activeView === 'audit') return;
  };

  const submitManager = (e) => {
    e.preventDefault();
    if (creatingManager) return;

    setCreatingManager(true);
    setManagerErrors({});
    router.post('/superuser/managers', managerForm, {
      preserveScroll: true,
      onSuccess: () => {
        setShowManagerModal(false);
        setManagerErrors({});
        setManagerForm({ name: '', email: '', phone: '', role: '', property_id: '', twoFA: 'yes' });
      },
      onError: (errors) => {
        setManagerErrors(errors);
      },
      onFinish: () => {
        setCreatingManager(false);
      },
    });
  };

  return (
    <SuperuserLayout
      activeView={activeView}
      onNavigate={setActiveView}
      title={meta.title}
      subtitle={meta.subtitle}
      actionLabel={meta.actionLabel}
      onAction={onAction}
      navCounts={{ properties: effectiveProperties.length, managers: effectiveManagers.length, approvals: pendingLeases.length + pendingMaintenance.length }}
    >
      <Head title={`Superuser - ${meta.title}`} />

      {activeView === 'overview' && (
        <OverviewPage
          properties={effectiveProperties}
          managers={effectiveManagers}
          auditLogs={auditLogs}
        />
      )}

      {activeView === 'properties' && (
        <PropertiesPage
          properties={effectiveProperties}
          managers={effectiveManagers}
          search={search}
          setSearch={setSearch}
          status={status}
          setStatus={setStatus}
          onOpenPropertyModal={() => setShowModal(true)}
          onAssignManager={assignManager}
        />
      )}

      {activeView === 'managers' && <ManagersPage managers={effectiveManagers} properties={effectiveProperties} archivedManagers={archivedManagers} onOpenManagerModal={() => setShowManagerModal(true)} />}
      {activeView === 'roles' && <RolesPage settings={settings} />}
      {activeView === 'approvals' && <ApprovalsPage pendingLeases={pendingLeases} pendingMaintenance={pendingMaintenance} />}
      {activeView === 'audit' && <AuditPage properties={effectiveProperties} managers={effectiveManagers} auditLogs={auditLogs} />}
      {activeView === 'settings' && <SettingsPage settings={settings} />}

      <div className={`modal-overlay ${showModal ? 'open' : ''}`} onClick={(e) => e.target === e.currentTarget && setShowModal(false)}>
        <div className="modal" style={{ width: 540 }}>
          <div className="modal-header"><div className="modal-title">Add New Property</div><button className="modal-close" onClick={() => setShowModal(false)}>✕</button></div>
          <form onSubmit={submit}>
            <div className="modal-body">
              <div className="form-row"><div className="form-group"><label className="form-label">Property Name *</label><input className="form-input" value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="e.g. Ruky Heights" required /></div></div>
              <div className="form-row"><div className="form-group"><label className="form-label">Address *</label><input className="form-input" value={data.address} onChange={(e) => setData('address', e.target.value)} placeholder="Full street address" required /></div><div className="form-group"><label className="form-label">City</label><input className="form-input" value={data.city} onChange={(e) => setData('city', e.target.value)} placeholder="Dar es Salaam" /></div></div>
              <div className="form-row"><div className="form-group"><label className="form-label">Total Units</label><input className="form-input" type="number" value={data.unit_count} onChange={(e) => setData('unit_count', e.target.value)} placeholder="0" min="0" /></div></div>
              <div className="form-row">
                <div className="form-group"><label className="form-label">Upper Floors *</label><input className="form-input" type="text" inputMode="numeric" pattern="[0-9]*" value={upperFloorsRaw} onChange={(e) => { const v = e.target.value.replace(/[^0-9]/g, ''); setUpperFloorsRaw(v); const n = parseInt(v, 10); if (!isNaN(n) && n >= 1) setData('upper_floors', n); }} onBlur={() => { const n = parseInt(upperFloorsRaw, 10); const clamped = isNaN(n) || n < 1 ? 1 : Math.min(n, 100); setData('upper_floors', clamped); setUpperFloorsRaw(String(clamped)); }} placeholder="e.g. 7" required /></div>
                <div className="form-group"><label className="form-label">Basement Levels</label><input className="form-input" type="number" value={data.basements} onChange={(e) => setData('basements', Math.max(0, parseInt(e.target.value, 10) || 0))} placeholder="0" min="0" max="10" /></div>
              </div>
              <div className="form-row" style={{ gap: 24 }}>
                <label style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 13, cursor: 'pointer', userSelect: 'none' }}>
                  <input type="checkbox" checked={data.has_ground_floor} onChange={(e) => setData('has_ground_floor', e.target.checked)} style={{ width: 15, height: 15, cursor: 'pointer' }} />
                  Has Ground Floor
                </label>
                <label style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 13, cursor: 'pointer', userSelect: 'none' }}>
                  <input type="checkbox" checked={data.has_mezzanine} onChange={(e) => setData('has_mezzanine', e.target.checked)} style={{ width: 15, height: 15, cursor: 'pointer' }} />
                  Has Mezzanine
                </label>
              </div>
              {(() => {
                const preview = generateFloors({ basements: data.basements, has_ground_floor: data.has_ground_floor, has_mezzanine: data.has_mezzanine, upper_floors: data.upper_floors });
                return (
                  <div style={{ fontSize: 11.5, color: 'var(--text-muted)', marginTop: 2, padding: '6px 10px', background: 'var(--surface-alt, rgba(0,0,0,0.03))', borderRadius: 6, lineHeight: 1.7 }}>
                    <span style={{ fontWeight: 600, color: 'var(--text-secondary)' }}>Floor preview: </span>
                    {preview.map(f => f.label).join(' · ')}
                  </div>
                );
              })()}
              <div className="form-row"><div className="form-group"><label className="form-label">Status</label>
                <select className="form-input form-select" value={data.status} onChange={(e) => setData('status', e.target.value)}>
                  <option value="active">Active</option>
                  <option value="trial">Trial</option>
                  <option value="inactive">Inactive</option>
                </select>
              </div></div>
            </div>
            <div className="modal-footer">
              <button type="button" className="btn-ghost" onClick={() => setShowModal(false)} disabled={processing}>Cancel</button>
              <button type="submit" className="btn-primary" disabled={processing} aria-busy={processing}>
                {processing ? (
                  <>
                    <span className="btn-spinner" aria-hidden="true"></span>
                    <span>Adding Property...</span>
                  </>
                ) : (
                  <span>Add Property</span>
                )}
              </button>
            </div>
          </form>
        </div>
      </div>

      <div className={`modal-overlay ${showManagerModal ? 'open' : ''}`} onClick={(e) => { if (e.target === e.currentTarget) { setShowManagerModal(false); setManagerErrors({}); } }}>
        <div className="modal" style={{ width: 540 }}>
          <div className="modal-header"><div className="modal-title">Add User</div><button className="modal-close" onClick={() => { setShowManagerModal(false); setManagerErrors({}); }}>✕</button></div>
          <form onSubmit={submitManager}>
            <div className="modal-body">
              <div className="form-row"><div className="form-group"><label className="form-label">Full Name *</label><input className="form-input" value={managerForm.name} onChange={(e) => setManagerForm((f) => ({ ...f, name: e.target.value }))} required />{managerErrors.name && <div style={{ fontSize: 12, color: 'var(--red)', marginTop: 4 }}>{managerErrors.name}</div>}</div></div>
              <div className="form-row"><div className="form-group"><label className="form-label">Email *</label><input className="form-input" type="email" value={managerForm.email} onChange={(e) => setManagerForm((f) => ({ ...f, email: e.target.value }))} required />{managerErrors.email && <div style={{ fontSize: 12, color: 'var(--red)', marginTop: 4 }}>{managerErrors.email}</div>}</div><div className="form-group"><label className="form-label">Phone</label><input className="form-input" value={managerForm.phone} onChange={(e) => setManagerForm((f) => ({ ...f, phone: e.target.value }))} /></div></div>
              <div className="form-row"><div className="form-group"><label className="form-label">Role *</label>
                <select className="form-input form-select" value={managerForm.role} onChange={(e) => setManagerForm((f) => ({ ...f, role: e.target.value }))} required>
                  <option value="">Select role...</option>
                  <option value="manager">Property Manager</option>
                  <option value="accountant">Accountant</option>
                  <option value="viewer">Viewer (Read-Only)</option>
                </select>
                {managerErrors.role && <div style={{ fontSize: 12, color: 'var(--red)', marginTop: 4 }}>{managerErrors.role}</div>}
              </div></div>
              <div className="form-row"><div className="form-group"><label className="form-label">Assign Property</label>
                <select className="form-input form-select" value={managerForm.property_id} onChange={(e) => setManagerForm((f) => ({ ...f, property_id: e.target.value }))}>
                  <option value="">Select property...</option>
                  {effectiveProperties.map((property) => (
                    <option key={property.id} value={property.id}>{property.name}</option>
                  ))}
                </select>
              </div></div>
              <div className="form-row"><div className="form-group"><label className="form-label">Require 2FA</label>
                <select className="form-input form-select" value={managerForm.twoFA} onChange={(e) => setManagerForm((f) => ({ ...f, twoFA: e.target.value }))}>
                  <option value="yes">Yes (recommended)</option>
                  <option value="no">No</option>
                </select>
              </div></div>
            </div>
            <div className="modal-footer">
              <button type="button" className="btn-ghost" onClick={() => setShowManagerModal(false)} disabled={creatingManager}>Cancel</button>
              <button type="submit" className="btn-primary" disabled={creatingManager} aria-busy={creatingManager}>
                {creatingManager ? (
                  <>
                    <span className="btn-spinner" aria-hidden="true"></span>
                    <span>Creating User...</span>
                  </>
                ) : (
                  <span>Create User</span>
                )}
              </button>
            </div>
          </form>
        </div>
      </div>

      {toast.msg && (
        <div className={`toast show`} style={{
          background: toast.type === 'warning' ? 'var(--amber)' : toast.type === 'error' ? 'var(--red)' : undefined,
          color: (toast.type === 'warning' || toast.type === 'error') ? '#fff' : undefined,
        }}>{toast.msg}</div>
      )}
    </SuperuserLayout>
  );
}
