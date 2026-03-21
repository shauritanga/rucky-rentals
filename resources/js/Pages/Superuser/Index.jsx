import { useMemo, useState } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import SuperuserLayout from '@/Layouts/SuperuserLayout';
import OverviewPage from '@/Pages/Superuser/Pages/OverviewPage';
import PropertiesPage from '@/Pages/Superuser/Pages/PropertiesPage';
import ManagersPage from '@/Pages/Superuser/Pages/ManagersPage';
import RolesPage from '@/Pages/Superuser/Pages/RolesPage';
import AuditPage from '@/Pages/Superuser/Pages/AuditPage';
import SettingsPage from '@/Pages/Superuser/Pages/SettingsPage';

const DEMO_PROPERTIES = [
  {
    id: 'P001',
    name: 'Rucky Heights',
    code: 'P001',
    address: '14 Msasani Rd, Masaki',
    city: 'Dar es Salaam',
    country: 'Tanzania',
    unit_count: 32,
    occupied_units: 28,
    status: 'active',
    monthly_rent: 56000000,
    manager_user_id: 2,
    manager: { id: 2, name: 'James Mwangi', email: 'james@ruckyrentals.co.tz' },
  },
  {
    id: 'P002',
    name: 'Rucky Gardens',
    code: 'P002',
    address: '8 Ali Hassan Mwinyi Rd, Upanga',
    city: 'Dar es Salaam',
    country: 'Tanzania',
    unit_count: 48,
    occupied_units: 42,
    status: 'active',
    monthly_rent: 78400000,
    manager_user_id: 3,
    manager: { id: 3, name: 'Grace Wanjiru', email: 'grace@ruckyrentals.co.tz' },
  },
  {
    id: 'P003',
    name: 'Rucky Towers',
    code: 'P003',
    address: '22 Haile Selassie Rd, Oyster Bay',
    city: 'Dar es Salaam',
    country: 'Tanzania',
    unit_count: 30,
    occupied_units: 24,
    status: 'active',
    monthly_rent: 67200000,
    manager_user_id: 4,
    manager: { id: 4, name: 'Kevin Otieno', email: 'kevin@ruckyrentals.co.tz' },
  },
  {
    id: 'P004',
    name: 'Rucky Court',
    code: 'P004',
    address: '5 New Bagamoyo Rd, Mikocheni',
    city: 'Dar es Salaam',
    country: 'Tanzania',
    unit_count: 17,
    occupied_units: 14,
    status: 'trial',
    monthly_rent: 29400000,
    manager_user_id: 2,
    manager: { id: 2, name: 'James Mwangi', email: 'james@ruckyrentals.co.tz' },
  },
];

const DEMO_MANAGERS = [
  { id: 1, name: 'Super Admin', email: 'admin@ruckyrentals.co.tz', role: 'superuser', property_name: 'All Properties', property_id: null, lastActive: 'Just now', twoFA: true, status: 'active', online: true },
  { id: 2, name: 'James Mwangi', email: 'james@ruckyrentals.co.tz', role: 'manager', property_name: 'Rucky Heights, Rucky Court', property_id: 'P001', lastActive: '2 min ago', twoFA: true, status: 'active', online: true },
  { id: 3, name: 'Grace Wanjiru', email: 'grace@ruckyrentals.co.tz', role: 'manager', property_name: 'Rucky Gardens', property_id: 'P002', lastActive: '14 min ago', twoFA: true, status: 'active', online: true },
  { id: 4, name: 'Kevin Otieno', email: 'kevin@ruckyrentals.co.tz', role: 'manager', property_name: 'Rucky Towers', property_id: 'P003', lastActive: '1 hour ago', twoFA: false, status: 'active', online: false },
  { id: 5, name: 'Diana Ochieng', email: 'diana@ruckyrentals.co.tz', role: 'accountant', property_name: 'All Properties', property_id: null, lastActive: 'Yesterday 16:20', twoFA: true, status: 'active', online: false },
  { id: 6, name: 'Patrick Kimani', email: 'patrick@ruckyrentals.co.tz', role: 'viewer', property_name: 'Rucky Heights', property_id: 'P001', lastActive: '3 days ago', twoFA: false, status: 'active', online: false },
];

const VIEW_META = {
  overview: { title: 'Overview', subtitle: 'Superuser Console', actionLabel: 'Add Property' },
  properties: { title: 'Properties', subtitle: 'All buildings', actionLabel: 'Add Property' },
  managers: { title: 'Managers & Users', subtitle: 'All system users', actionLabel: 'Add User' },
  roles: { title: 'Roles & Permissions', subtitle: 'Access control', actionLabel: 'Save Changes' },
  audit: { title: 'Audit Trail', subtitle: 'System log', actionLabel: 'Export CSV' },
  settings: { title: 'Settings', subtitle: 'System configuration', actionLabel: null },
};

export default function SuperuserIndex({ properties = [], managers = [] }) {
  const [activeView, setActiveView] = useState('overview');
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('all');
  const [showModal, setShowModal] = useState(false);
  const [showManagerModal, setShowManagerModal] = useState(false);

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
    total_floors: 1,
  });

  const effectiveProperties = properties.length > 0 ? properties : DEMO_PROPERTIES;
  const effectiveManagers = managers.length > 0 ? managers : DEMO_MANAGERS;

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
        setData('total_floors', 1);
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
      navCounts={{ properties: effectiveProperties.length, managers: effectiveManagers.length }}
    >
      <Head title={`Superuser - ${meta.title}`} />

      {activeView === 'overview' && (
        <OverviewPage
          properties={effectiveProperties}
          managers={effectiveManagers}
          stats={stats}
          onGoProperties={() => setActiveView('properties')}
          onOpenPropertyModal={() => setShowModal(true)}
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
      {activeView === 'roles' && <RolesPage />}
      {activeView === 'audit' && <AuditPage properties={effectiveProperties} managers={effectiveManagers} />}
      {activeView === 'settings' && <SettingsPage />}

      <div className={`modal-overlay ${showModal ? 'open' : ''}`} onClick={(e) => e.target === e.currentTarget && setShowModal(false)}>
        <div className="modal" style={{ width: 540 }}>
          <div className="modal-header"><div className="modal-title">Add New Property</div><button className="modal-close" onClick={() => setShowModal(false)}>✕</button></div>
          <form onSubmit={submit}>
            <div className="modal-body">
              <div className="form-row"><div className="form-group"><label className="form-label">Property Name *</label><input className="form-input" value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="e.g. Rucky Heights" required /></div></div>
              <div className="form-row"><div className="form-group"><label className="form-label">Address *</label><input className="form-input" value={data.address} onChange={(e) => setData('address', e.target.value)} placeholder="Full street address" required /></div><div className="form-group"><label className="form-label">City</label><input className="form-input" value={data.city} onChange={(e) => setData('city', e.target.value)} placeholder="Dar es Salaam" /></div></div>
              <div className="form-row"><div className="form-group"><label className="form-label">Total Units</label><input className="form-input" type="number" value={data.unit_count} onChange={(e) => setData('unit_count', e.target.value)} placeholder="32" min="0" /></div><div className="form-group"><label className="form-label">Total Floors</label><input className="form-input" type="number" value={data.total_floors} onChange={(e) => setData('total_floors', e.target.value)} placeholder="6" min="1" /></div></div>
              <div className="form-row"><div className="form-group"><label className="form-label">Status</label>
                <select className="form-input form-select" value={data.status} onChange={(e) => setData('status', e.target.value)}>
                  <option value="active">Active</option>
                  <option value="trial">Trial</option>
                  <option value="inactive">Inactive</option>
                </select>
              </div></div>
            </div>
            <div className="modal-footer">
              <button type="button" className="btn-ghost" onClick={() => setShowModal(false)}>Cancel</button>
              <button type="submit" className="btn-primary" disabled={processing}>Add Property</button>
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
              <button type="button" className="btn-ghost" onClick={() => setShowManagerModal(false)}>Cancel</button>
              <button type="submit" className="btn-primary">Create User</button>
            </div>
          </form>
        </div>
      </div>
    </SuperuserLayout>
  );
}
