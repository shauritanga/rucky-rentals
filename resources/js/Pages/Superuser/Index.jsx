import { useMemo, useState } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
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
  managers: { title: 'Managers & Users', subtitle: 'All system users', actionLabel: 'Add User' },
  roles: { title: 'Roles & Permissions', subtitle: 'Access control', actionLabel: 'Save Changes' },
  approvals: { title: 'Approvals', subtitle: 'Pending requests', actionLabel: null },
  audit: { title: 'Audit Trail', subtitle: 'System log', actionLabel: 'Export CSV' },
  settings: { title: 'Settings', subtitle: 'System configuration', actionLabel: null },
};

export default function SuperuserIndex({ properties = [], managers = [], auditLogs = [], settings = {}, pendingLeases = [], pendingMaintenance = [] }) {
  const [activeView, setActiveView] = useState('overview');
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('all');
  const [showModal, setShowModal] = useState(false);
  const [showManagerModal, setShowManagerModal] = useState(false);
  const [creatingManager, setCreatingManager] = useState(false);

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
    total_floors: 7,
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
        setData('total_floors', 7);
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
    router.post('/superuser/managers', managerForm, {
      preserveScroll: true,
      onSuccess: () => {
        setShowManagerModal(false);
        setManagerForm({
          name: '',
          email: '',
          phone: '',
          role: '',
          property_id: '',
          twoFA: 'yes',
        });
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

      {activeView === 'managers' && <ManagersPage managers={effectiveManagers} properties={effectiveProperties} onOpenManagerModal={() => setShowManagerModal(true)} />}
      {activeView === 'roles' && <RolesPage settings={settings} />}
      {activeView === 'approvals' && <ApprovalsPage pendingLeases={pendingLeases} pendingMaintenance={pendingMaintenance} />}
      {activeView === 'audit' && <AuditPage properties={effectiveProperties} managers={effectiveManagers} auditLogs={auditLogs} />}
      {activeView === 'settings' && <SettingsPage settings={settings} />}

      <div className={`modal-overlay ${showModal ? 'open' : ''}`} onClick={(e) => e.target === e.currentTarget && setShowModal(false)}>
        <div className="modal" style={{ width: 540 }}>
          <div className="modal-header"><div className="modal-title">Add New Property</div><button className="modal-close" onClick={() => setShowModal(false)}>✕</button></div>
          <form onSubmit={submit}>
            <div className="modal-body">
              <div className="form-row"><div className="form-group"><label className="form-label">Property Name *</label><input className="form-input" value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="e.g. Rucky Heights" required /></div></div>
              <div className="form-row"><div className="form-group"><label className="form-label">Address *</label><input className="form-input" value={data.address} onChange={(e) => setData('address', e.target.value)} placeholder="Full street address" required /></div><div className="form-group"><label className="form-label">City</label><input className="form-input" value={data.city} onChange={(e) => setData('city', e.target.value)} placeholder="Dar es Salaam" /></div></div>
              <div className="form-row"><div className="form-group"><label className="form-label">Total Units</label><input className="form-input" type="number" value={data.unit_count} onChange={(e) => setData('unit_count', e.target.value)} placeholder="0" min="0" /></div><div className="form-group"><label className="form-label">Total Floors</label><input className="form-input" type="number" value={data.total_floors} onChange={(e) => setData('total_floors', e.target.value)} placeholder="7" min="1" /></div></div>
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

      <div className={`modal-overlay ${showManagerModal ? 'open' : ''}`} onClick={(e) => e.target === e.currentTarget && setShowManagerModal(false)}>
        <div className="modal" style={{ width: 540 }}>
          <div className="modal-header"><div className="modal-title">Add User</div><button className="modal-close" onClick={() => setShowManagerModal(false)}>✕</button></div>
          <form onSubmit={submitManager}>
            <div className="modal-body">
              <div className="form-row"><div className="form-group"><label className="form-label">Full Name *</label><input className="form-input" value={managerForm.name} onChange={(e) => setManagerForm((f) => ({ ...f, name: e.target.value }))} required /></div></div>
              <div className="form-row"><div className="form-group"><label className="form-label">Email *</label><input className="form-input" type="email" value={managerForm.email} onChange={(e) => setManagerForm((f) => ({ ...f, email: e.target.value }))} required /></div><div className="form-group"><label className="form-label">Phone</label><input className="form-input" value={managerForm.phone} onChange={(e) => setManagerForm((f) => ({ ...f, phone: e.target.value }))} /></div></div>
              <div className="form-row"><div className="form-group"><label className="form-label">Role *</label>
                <select className="form-input form-select" value={managerForm.role} onChange={(e) => setManagerForm((f) => ({ ...f, role: e.target.value }))} required>
                  <option value="">Select role...</option>
                  <option value="manager">Property Manager</option>
                  <option value="accountant">Accountant</option>
                  <option value="viewer">Viewer (Read-Only)</option>
                </select>
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
    </SuperuserLayout>
  );
}
