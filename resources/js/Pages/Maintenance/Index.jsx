import { useMemo, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm, router } from '@inertiajs/react';
import useExchangeRate from '@/hooks/useExchangeRate';

const CAT_ICONS = {
  Plumbing: '🔧',
  Electrical: '💡',
  HVAC: '❄️',
  General: '🪛',
  Security: '🔒',
  Structural: '🏗️',
  Cleaning: '🧹',
};

const REQUEST_FILTERS = [
  ['all', 'All'],
  ['draft', 'Draft'],
  ['pending_accountant', 'Pending Accountant'],
  ['pending_manager', 'Pending Manager'],
  ['approved', 'Approved'],
  ['in_progress', 'In Progress'],
  ['completed', 'Completed'],
  ['rejected', 'Rejected'],
];

const STATUS_META = {
  draft: { label: 'Draft', bg: 'var(--bg-elevated)', color: 'var(--text-muted)' },
  pending_accountant: { label: 'Pending Accountant', bg: 'var(--amber-dim)', color: 'var(--amber)' },
  pending_manager: { label: 'Pending Manager', bg: 'var(--accent-dim)', color: 'var(--accent)' },
  approved: { label: 'Approved', bg: 'var(--green-dim)', color: 'var(--green)' },
  in_progress: { label: 'In Progress', bg: 'var(--accent-dim)', color: 'var(--accent)' },
  completed: { label: 'Completed', bg: 'var(--green-dim)', color: 'var(--green)' },
  rejected: { label: 'Rejected', bg: 'var(--red-dim)', color: 'var(--red)' },
};

const PRIORITY_META = {
  critical: { label: 'Critical', color: '#ef4444' },
  high: { label: 'High', color: 'var(--red)' },
  med: { label: 'Medium', color: 'var(--amber)' },
  low: { label: 'Low', color: 'var(--text-muted)' },
};

const INITIAL_SCHEDULED_TASKS = [
  { id: 'SC-001', title: 'Quarterly AC service — all units', unit: 'All units', category: 'HVAC', frequency: 'quarterly', next_due: '2026-04-01', assignee: 'Cool Air Ltd', status: 'upcoming' },
  { id: 'SC-002', title: 'Monthly fire extinguisher check', unit: 'Common areas', category: 'Security', frequency: 'monthly', next_due: '2026-04-01', assignee: 'Peter Ng.', status: 'upcoming' },
  { id: 'SC-003', title: 'Annual roof inspection', unit: 'Rooftop', category: 'Structural', frequency: 'annual', next_due: '2026-06-01', assignee: 'In-house', status: 'upcoming' },
  { id: 'SC-004', title: 'Weekly common area cleaning', unit: 'Common areas', category: 'Cleaning', frequency: 'weekly', next_due: '2026-03-23', assignee: 'Peter Ng.', status: 'upcoming' },
  { id: 'SC-005', title: 'Generator monthly service', unit: 'Generator room', category: 'General', frequency: 'monthly', next_due: '2026-03-20', assignee: 'In-house', status: 'overdue' },
  { id: 'SC-006', title: 'Elevator inspection', unit: 'Lift shafts', category: 'Structural', frequency: 'biannual', next_due: '2026-03-15', assignee: 'Otis Ltd', status: 'overdue' },
];

const MATERIAL_HINTS = {
  Plumbing: [{ name: 'PVC pipe — 1 inch', unit: 'pc', factor: 0.18, qty: 2 }, { name: 'Ball valve — 1/2 inch', unit: 'pc', factor: 0.12, qty: 1 }],
  Electrical: [{ name: 'Circuit breaker 20A', unit: 'pc', factor: 0.2, qty: 1 }, { name: 'Cable — 2.5mm twin', unit: 'm', factor: 0.1, qty: 4 }],
  HVAC: [{ name: 'HVAC refrigerant R22', unit: 'kg', factor: 0.24, qty: 2 }],
  General: [{ name: 'Hinge set', unit: 'set', factor: 0.12, qty: 1 }],
  Security: [{ name: 'Mortise lock', unit: 'pc', factor: 0.16, qty: 1 }],
  Structural: [{ name: 'Roofing sealant', unit: 'tube', factor: 0.14, qty: 2 }],
  Cleaning: [{ name: 'Industrial cleaner', unit: 'L', factor: 0.1, qty: 3 }],
};

const MATERIALS_PAGE_SIZE = 10;
const REQUESTS_PAGE_SIZE = 10;

function normalizeWorkflowStatus(ticket) {
  if (ticket.workflow_status) return ticket.workflow_status;
  if (ticket.status === 'resolved') return 'completed';
  if (ticket.status === 'in-progress') return 'in_progress';
  return 'pending_accountant';
}

function buildRecordNumber(item) {
  return item.record_number || item.ticket_number || `MR-${String(item.id).padStart(3, '0')}`;
}

function workflowToDbStatus(workflowStatus) {
  if (workflowStatus === 'completed') return 'resolved';
  if (workflowStatus === 'in_progress') return 'in-progress';
  return 'open';
}

function statusRank(status) {
  const order = {
    draft: 0,
    pending_accountant: 1,
    pending_manager: 2,
    approved: 3,
    in_progress: 4,
    completed: 5,
    rejected: -1,
  };

  return order[status] ?? 0;
}

function safeNotes(value) {
  if (Array.isArray(value)) return value;
  if (!value) return [];
  if (typeof value === 'string') {
    try {
      const parsed = JSON.parse(value);
      return Array.isArray(parsed) ? parsed : [];
    } catch {
      return [];
    }
  }
  return [];
}

export default function MaintenanceIndex({ tickets, units }) {
  const [tab, setTab] = useState('requests');
  const [filter, setFilter] = useState('all');
  const [catFilter, setCatFilter] = useState('');
  const [search, setSearch] = useState('');
  const [selected, setSelected] = useState(null);
  const [showModal, setShowModal] = useState(false);
  const [showScheduleModal, setShowScheduleModal] = useState(false);
  const [note, setNote] = useState('');
  const [materials, setMaterials] = useState([]);
  const [images, setImages] = useState([]);
  const [materialsPage, setMaterialsPage] = useState(1);
  const [requestsPage, setRequestsPage] = useState(1);
  const [scheduleTasks, setScheduleTasks] = useState(INITIAL_SCHEDULED_TASKS);
  const [scheduleForm, setScheduleForm] = useState({
    title: '',
    unit: '',
    category: 'General',
    frequency: 'monthly',
    next_due: '',
    assignee: '',
    notes: '',
  });
  const [workflowStatusByTicket, setWorkflowStatusByTicket] = useState(() => {
    const initial = {};
    tickets.forEach((t) => {
      initial[t.id] = normalizeWorkflowStatus(t);
    });
    return initial;
  });

  const { data, setData, post, processing, reset } = useForm({
    title: '',
    description: '',
    unit_ref: '',
    category: '',
    priority: 'med',
    assignee: '',
    labour: '',
    scheduled_date: '',
  });

  const { formatTzsFromUsd, formatCompactTzsFromUsd } = useExchangeRate();

  const normalizedTickets = useMemo(() => {
    return tickets.map((ticket) => {
      const workflowStatus = workflowStatusByTicket[ticket.id] || normalizeWorkflowStatus(ticket);
      const labour = Number(ticket.cost || 0);
      const materialCost = 0;
      const recordNumber = buildRecordNumber(ticket);
      return {
        ...ticket,
        record_number: recordNumber,
        workflow_status: workflowStatus,
        labour,
        material_cost: materialCost,
        total_cost: labour + materialCost,
        materials: Array.isArray(ticket.materials) && ticket.materials.length
          ? ticket.materials
          : (MATERIAL_HINTS[ticket.category] || []).map((m) => ({
              name: m.name,
              unit: m.unit,
              qty: m.qty,
              unitPrice: Math.max(1, Math.round(labour * m.factor)),
            })),
      };
    });
  }, [tickets, workflowStatusByTicket]);

  const tenantByUnit = useMemo(() => {
    const map = {};
    (units || []).forEach((u) => {
      const key = u.unit_number || u.id || u.unit_ref;
      if (!key) return;
      map[String(key)] = u.tenant?.name || u.tenant_name || u.tenant || '—';
    });
    return map;
  }, [units]);

  const counts = useMemo(() => {
    const next = {
      all: normalizedTickets.length,
      draft: 0,
      pending_accountant: 0,
      pending_manager: 0,
      approved: 0,
      in_progress: 0,
      completed: 0,
      rejected: 0,
    };

    normalizedTickets.forEach((ticket) => {
      if (Object.prototype.hasOwnProperty.call(next, ticket.workflow_status)) {
        next[ticket.workflow_status] += 1;
      }
    });

    return next;
  }, [normalizedTickets]);

  const filteredTickets = useMemo(() => {
    const q = search.trim().toLowerCase();
    return normalizedTickets.filter((ticket) => {
      const matchFilter = filter === 'all' || ticket.workflow_status === filter;
      const matchCategory = !catFilter || ticket.category === catFilter;
      const matchSearch =
        !q ||
        String(ticket.title || '').toLowerCase().includes(q) ||
        String(ticket.unit_ref || '').toLowerCase().includes(q) ||
        String(ticket.record_number || '').toLowerCase().includes(q);
      return matchFilter && matchCategory && matchSearch;
    });
  }, [normalizedTickets, filter, catFilter, search]);

  const requestsTotalPages = Math.max(1, Math.ceil(filteredTickets.length / REQUESTS_PAGE_SIZE));
  const requestsCurrentPage = Math.min(requestsPage, requestsTotalPages);

  const requestPageRows = useMemo(() => {
    const start = (requestsCurrentPage - 1) * REQUESTS_PAGE_SIZE;
    return filteredTickets.slice(start, start + REQUESTS_PAGE_SIZE);
  }, [filteredTickets, requestsCurrentPage]);

  const requestsStart = filteredTickets.length ? ((requestsCurrentPage - 1) * REQUESTS_PAGE_SIZE) + 1 : 0;
  const requestsEnd = filteredTickets.length
    ? Math.min(requestsCurrentPage * REQUESTS_PAGE_SIZE, filteredTickets.length)
    : 0;

  const openRequests = counts.draft + counts.pending_accountant + counts.pending_manager + counts.approved + counts.in_progress;
  const pendingApprovals = counts.pending_accountant + counts.pending_manager;
  const urgentCount = normalizedTickets.filter(
    (t) => ['high', 'critical'].includes(t.priority) && ['approved', 'in_progress'].includes(t.workflow_status),
  ).length;
  const totalCostUsd = normalizedTickets.reduce((sum, ticket) => sum + Number(ticket.total_cost || 0), 0);

  const materialTotal = materials.reduce((sum, row) => sum + Number(row.qty || 0) * Number(row.unit_price || 0), 0);
  const labourTotal = Number(data.labour || 0);
  const estimateTotal = materialTotal + labourTotal;

  const notes = useMemo(() => safeNotes(selected?.notes), [selected]);

  const byCategory = useMemo(() => {
    const acc = {};
    normalizedTickets.forEach((ticket) => {
      const key = ticket.category || 'General';
      acc[key] = (acc[key] || 0) + Number(ticket.total_cost || 0);
    });
    return Object.entries(acc).sort((a, b) => b[1] - a[1]);
  }, [normalizedTickets]);

  const byUnit = useMemo(() => {
    const acc = {};
    normalizedTickets.forEach((ticket) => {
      const key = ticket.unit_ref || 'Common';
      acc[key] = (acc[key] || 0) + Number(ticket.total_cost || 0);
    });
    return Object.entries(acc).sort((a, b) => b[1] - a[1]);
  }, [normalizedTickets]);

  const materialUsageItems = useMemo(() => {
    const usage = {};
    normalizedTickets
      .filter((r) => ['completed', 'in_progress', 'approved'].includes(r.workflow_status))
      .forEach((r) => {
        (r.materials || []).forEach((m) => {
          if (!usage[m.name]) usage[m.name] = { unit: m.unit || '', qty: 0, total: 0 };
          usage[m.name].qty += Number(m.qty || 0);
          usage[m.name].total += Number(m.unitPrice || 0) * Number(m.qty || 0);
        });
      });
    return Object.entries(usage).sort((a, b) => b[1].total - a[1].total);
  }, [normalizedTickets]);

  const materialsByRequestRows = useMemo(() => {
    const rows = [];
    normalizedTickets.forEach((r) => {
      (r.materials || []).forEach((m) => {
        rows.push({
          reqId: r.record_number || buildRecordNumber(r),
          unit: r.unit_ref,
          matName: m.name,
          qty: Number(m.qty || 0),
          unitQty: m.unit || '',
          unitPrice: Number(m.unitPrice || 0),
          total: Number(m.unitPrice || 0) * Number(m.qty || 0),
        });
      });
    });
    return rows;
  }, [normalizedTickets]);

  const materialsTotalPages = Math.max(1, Math.ceil(materialsByRequestRows.length / MATERIALS_PAGE_SIZE));
  const materialsCurrentPage = Math.min(materialsPage, materialsTotalPages);

  const materialsPageRows = useMemo(() => {
    const start = (materialsCurrentPage - 1) * MATERIALS_PAGE_SIZE;
    return materialsByRequestRows.slice(start, start + MATERIALS_PAGE_SIZE);
  }, [materialsByRequestRows, materialsCurrentPage]);

  const materialsStart = materialsByRequestRows.length ? ((materialsCurrentPage - 1) * MATERIALS_PAGE_SIZE) + 1 : 0;
  const materialsEnd = materialsByRequestRows.length
    ? Math.min(materialsCurrentPage * MATERIALS_PAGE_SIZE, materialsByRequestRows.length)
    : 0;

  const byTenant = useMemo(() => {
    const acc = {};
    normalizedTickets.forEach((ticket) => {
      const tenant = tenantByUnit[String(ticket.unit_ref)] || 'Vacant';
      acc[tenant] = (acc[tenant] || 0) + Number(ticket.total_cost || 0);
    });
    return Object.entries(acc).sort((a, b) => b[1] - a[1]);
  }, [normalizedTickets, tenantByUnit]);

  const submitRequest = (e, mode = 'pending_accountant') => {
    e.preventDefault();
    post('/maintenance', {
      data: {
        title: data.title,
        description: data.description,
        unit_ref: data.unit_ref,
        category: data.category || 'General',
        priority: data.priority === 'critical' ? 'high' : data.priority,
        assignee: data.assignee,
      },
      onSuccess: () => {
        reset();
        setMaterials([]);
        setImages([]);
        setShowModal(false);
      },
    });

    if (mode === 'draft') {
      setShowModal(false);
    }
  };

  const updateWorkflowStatus = (ticket, nextWorkflowStatus) => {
    setWorkflowStatusByTicket((prev) => ({ ...prev, [ticket.id]: nextWorkflowStatus }));
    router.patch(
      `/maintenance/${ticket.id}`,
      { status: workflowToDbStatus(nextWorkflowStatus) },
      {
        onSuccess: () => {
          setSelected((s) => (s ? { ...s, workflow_status: nextWorkflowStatus } : null));
        },
      },
    );
  };

  const addNote = () => {
    if (!selected || !note.trim()) return;
    router.patch(`/maintenance/${selected.id}`, { note }, { onSuccess: () => setNote('') });
  };

  const addMaterialRow = () => {
    setMaterials((prev) => [
      ...prev,
      { id: `${Date.now()}-${prev.length}`, name: '', unit: '', qty: 1, unit_price: 0 },
    ]);
  };

  const updateMaterialRow = (id, field, value) => {
    setMaterials((prev) => prev.map((row) => (row.id === id ? { ...row, [field]: value } : row)));
  };

  const removeMaterialRow = (id) => {
    setMaterials((prev) => prev.filter((row) => row.id !== id));
  };

  const handleImageUpload = (files) => {
    const list = Array.from(files || []).filter((f) => String(f.type || '').startsWith('image/'));
    if (!list.length) return;

    list.forEach((file) => {
      const reader = new FileReader();
      reader.onload = (ev) => {
        setImages((prev) => [...prev, String(ev.target?.result || '')]);
      };
      reader.readAsDataURL(file);
    });
  };

  const upcomingSchedule = scheduleTasks.filter((task) => task.status === 'upcoming');
  const overdueSchedule = scheduleTasks.filter((task) => task.status === 'overdue');

  return (
    <AppLayout title="Maintenance" subtitle={`— ${openRequests} open`}>
      <Head title="Maintenance" />

      <div className="stats-grid" style={{ marginBottom: 20 }}>
        <div className="stat-card">
          <div className="stat-top"><div className="stat-icon red"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div><span className="stat-delta down">{urgentCount} urgent</span></div>
          <div className="stat-value">{openRequests}</div>
          <div className="stat-label">Active Requests</div>
        </div>
        <div className="stat-card">
          <div className="stat-top"><div className="stat-icon amber"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div><span className="stat-delta">pending</span></div>
          <div className="stat-value">{pendingApprovals}</div>
          <div className="stat-label">Awaiting Approval</div>
        </div>
        <div className="stat-card">
          <div className="stat-top"><div className="stat-icon green"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="20 6 9 17 4 12"/></svg></div><span className="stat-delta up">this month</span></div>
          <div className="stat-value">{counts.completed}</div>
          <div className="stat-label">Completed</div>
        </div>
        <div className="stat-card">
          <div className="stat-top"><div className="stat-icon amber"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg></div><span className="stat-delta down">market rate</span></div>
          <div className="stat-value">{formatCompactTzsFromUsd(totalCostUsd)}</div>
          <div className="stat-label">Total Cost (Market)</div>
        </div>
      </div>

      <div style={{ display: 'flex', gap: 0, background: 'var(--bg-elevated)', border: '1px solid var(--border)', borderRadius: 11, padding: 3, marginBottom: 18, width: 'fit-content' }}>
        <button className={`prof-tab ${tab === 'requests' ? 'active' : ''}`} onClick={() => setTab('requests')} style={{ padding: '7px 18px' }}>Requests</button>
        <button className={`prof-tab ${tab === 'schedule' ? 'active' : ''}`} onClick={() => setTab('schedule')} style={{ padding: '7px 18px' }}>Schedule</button>
        <button className={`prof-tab ${tab === 'materials' ? 'active' : ''}`} onClick={() => setTab('materials')} style={{ padding: '7px 18px' }}>Materials</button>
        <button className={`prof-tab ${tab === 'analytics' ? 'active' : ''}`} onClick={() => setTab('analytics')} style={{ padding: '7px 18px' }}>Analytics</button>
      </div>

      {tab === 'requests' && (
        <>
          <div className="toolbar" style={{ marginBottom: 14 }}>
            <div className="filters" style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
              {REQUEST_FILTERS.map(([key, label]) => (
                <button key={key} className={`filter-pill ${filter === key ? 'active' : ''}`} onClick={() => setFilter(key)}>
                  {label} <span className="pill-count">{counts[key] || 0}</span>
                </button>
              ))}
            </div>
            <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
              <div className="search-box" style={{ width: 190 }}>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                <input type="text" placeholder="Search requests…" value={search} onChange={(e) => setSearch(e.target.value)} />
              </div>
              <select className="form-input form-select" value={catFilter} onChange={(e) => setCatFilter(e.target.value)} style={{ width: 130, padding: '6px 28px 6px 10px', fontSize: 12.5 }}>
                <option value="">All Categories</option>
                {Object.keys(CAT_ICONS).map((c) => <option key={c} value={c}>{c}</option>)}
              </select>
              <button className="btn-primary" style={{ whiteSpace: 'nowrap' }} onClick={() => setShowModal(true)}>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                New Request
              </button>
            </div>
          </div>

          <div className="card" style={{ overflow: 'hidden' }}>
            <table className="data-table">
              <thead>
                <tr>
                  <th>Request</th>
                  <th>Record</th>
                  <th>Unit / Tenant</th>
                  <th>Category</th>
                  <th>Priority</th>
                  <th>Status</th>
                  <th>Submitted</th>
                  <th>Materials</th>
                  <th>Labour</th>
                  <th>Total</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                {filteredTickets.length === 0 && <tr><td colSpan={11} style={{ textAlign: 'center', padding: 40, color: 'var(--text-muted)' }}>No maintenance records match your filters</td></tr>}
                {requestPageRows.map((t) => {
                  const statusMeta = STATUS_META[t.workflow_status] || STATUS_META.draft;
                  const pri = PRIORITY_META[t.priority] || PRIORITY_META.low;
                  return (
                    <tr key={t.id} style={{ cursor: 'pointer' }} onClick={() => setSelected({ ...t })}>
                      <td>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                          <span style={{ fontSize: 18 }}>{CAT_ICONS[t.category] || '🔧'}</span>
                          <div>
                            <div style={{ fontWeight: 600, fontSize: '13.5px' }}>{t.title}</div>
                            <div style={{ fontSize: '11.5px', color: 'var(--text-muted)' }}>{t.record_number} · {t.assignee || '—'}</div>
                          </div>
                        </div>
                      </td>
                      <td style={{ fontSize: 12.5, fontWeight: 700, color: 'var(--accent)' }}>{t.record_number}</td>
                      <td><div style={{ fontWeight: 600 }}>{t.unit_ref}</div><div style={{ fontSize: 12, color: 'var(--text-muted)' }}>{tenantByUnit[String(t.unit_ref)] || '—'}</div></td>
                      <td style={{ fontSize: 13 }}>{t.category}</td>
                      <td><span style={{ fontSize: 12, fontWeight: 600, color: pri.color }}>{pri.label}</span></td>
                      <td><span style={{ fontSize: 12, fontWeight: 600, padding: '2px 9px', borderRadius: 20, background: statusMeta.bg, color: statusMeta.color }}>{statusMeta.label}</span></td>
                      <td style={{ fontSize: 12.5, color: 'var(--text-muted)' }}>{t.reported_date}</td>
                      <td>{t.material_cost ? formatTzsFromUsd(t.material_cost) : '—'}</td>
                      <td>{t.labour ? formatTzsFromUsd(t.labour) : '—'}</td>
                      <td style={{ fontWeight: 700 }}>{t.total_cost ? formatTzsFromUsd(t.total_cost) : '—'}</td>
                      <td><button className="btn-ghost" style={{ fontSize: 12, padding: '4px 8px' }} onClick={(e) => { e.stopPropagation(); setSelected({ ...t }); }}>View</button></td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 10, padding: '10px 12px 12px', borderTop: '1px solid var(--border-subtle)' }}>
              <div style={{ fontSize: 12, color: 'var(--text-muted)' }}>Showing {requestsStart}-{requestsEnd} of {filteredTickets.length}</div>
              <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                <button
                  className="btn-ghost"
                  style={{ fontSize: 12, padding: '6px 10px' }}
                  onClick={() => setRequestsPage((p) => Math.max(1, Math.min(requestsCurrentPage, p) - 1))}
                  disabled={requestsCurrentPage <= 1}
                >
                  Previous
                </button>
                <div style={{ fontSize: 12, color: 'var(--text-muted)' }}>Page {requestsCurrentPage} / {requestsTotalPages}</div>
                <button
                  className="btn-ghost"
                  style={{ fontSize: 12, padding: '6px 10px' }}
                  onClick={() => setRequestsPage((p) => Math.min(requestsTotalPages, Math.min(requestsCurrentPage, p) + 1))}
                  disabled={requestsCurrentPage >= requestsTotalPages}
                >
                  Next
                </button>
              </div>
            </div>
          </div>
        </>
      )}

      {tab === 'schedule' && (
        <>
          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 16 }}>
            <div style={{ fontSize: 14, fontWeight: 600 }}>Scheduled & Preventive Maintenance</div>
            <button className="btn-primary" onClick={() => setShowScheduleModal(true)}>
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              Schedule Task
            </button>
          </div>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16, marginBottom: 16 }}>
            <div className="card"><div className="card-header"><div className="card-title">Upcoming (30 days)</div></div><div style={{ padding: '0 8px 8px' }}>{upcomingSchedule.length ? upcomingSchedule.map((task) => <div key={task.id} style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '10px 12px', borderRadius: 8, background: 'var(--bg-elevated)', marginBottom: 6 }}><div style={{ fontSize: 18 }}>{CAT_ICONS[task.category] || '🔧'}</div><div style={{ flex: 1 }}><div style={{ fontSize: 13, fontWeight: 500 }}>{task.title}</div><div style={{ fontSize: '11.5px', color: 'var(--text-muted)' }}>{task.unit} · {task.assignee || 'Unassigned'}</div></div><div style={{ textAlign: 'right', fontSize: 12, color: 'var(--text-muted)' }}>Due {task.next_due}</div></div>) : <div style={{padding:16,fontSize:13,color:'var(--text-muted)'}}>No tasks due in 30 days</div>}</div></div>
            <div className="card"><div className="card-header"><div className="card-title">Overdue</div></div><div style={{ padding: '0 8px 8px' }}>{overdueSchedule.length ? overdueSchedule.map((task) => <div key={task.id} style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '10px 12px', borderRadius: 8, background: 'var(--bg-elevated)', marginBottom: 6 }}><div style={{ fontSize: 18 }}>{CAT_ICONS[task.category] || '🔧'}</div><div style={{ flex: 1 }}><div style={{ fontSize: 13, fontWeight: 500 }}>{task.title}</div><div style={{ fontSize: '11.5px', color: 'var(--text-muted)' }}>{task.unit} · {task.assignee || 'Unassigned'}</div></div><div style={{ textAlign: 'right', fontSize: 12, color: 'var(--red)' }}>Due {task.next_due}</div></div>) : <div style={{padding:16,fontSize:13,color:'var(--text-muted)'}}>No overdue tasks</div>}</div></div>
          </div>
          <div className="card">
            <div className="card-header"><div className="card-title">All Scheduled Tasks</div></div>
            <table className="data-table">
              <thead><tr><th>Task</th><th>Unit / Area</th><th>Frequency</th><th>Next Due</th><th>Assigned To</th><th>Status</th><th></th></tr></thead>
              <tbody>{scheduleTasks.map((task) => {
                const isOverdue = task.status === 'overdue';
                const FREQ = { weekly:'Weekly', monthly:'Monthly', quarterly:'Quarterly', biannual:'Every 6 months', annual:'Annual' };
                return <tr key={task.id}><td><div style={{fontWeight:600}}>{task.title}</div><div style={{fontSize:12,color:'var(--text-muted)'}}>{task.id}</div></td><td>{task.unit}</td><td style={{fontSize:13}}>{FREQ[task.frequency] || task.frequency}</td><td style={{color:isOverdue?'var(--red)':'var(--text-secondary)',fontWeight:isOverdue?600:400}}>{task.next_due}</td><td style={{fontSize:13}}>{task.assignee || '—'}</td><td><span style={{fontSize:12,fontWeight:600,color:isOverdue?'var(--red)':'var(--green)'}}>● {isOverdue?'Overdue':'On Track'}</span></td><td><button className="btn-ghost" style={{fontSize:12,padding:'4px 8px'}} onClick={() => setScheduleTasks((prev) => prev.filter((x) => x.id !== task.id))}>Remove</button></td></tr>;
              })}</tbody>
            </table>
          </div>
        </>
      )}

      {tab === 'materials' && (
        <>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16, marginBottom: 16 }}>
            <div className="card"><div className="card-header"><div className="card-title">Usage This Month</div><div className="card-sub">Market price entered by staff per request</div></div><div style={{ padding: '0 8px 8px' }}>{materialUsageItems.length ? materialUsageItems.map(([name, usage]) => <div key={name} style={{display:'flex',alignItems:'center',justifyContent:'space-between',padding:'10px 12px',borderBottom:'1px solid var(--border-subtle)'}}><div><div style={{fontSize:13,fontWeight:500}}>{name}</div><div style={{fontSize:'11.5px',color:'var(--text-muted)'}}>Qty: {usage.qty} {usage.unit}</div></div><div style={{fontWeight:700,fontVariantNumeric:'tabular-nums'}}>{formatTzsFromUsd(usage.total)}</div></div>) : <div style={{padding:20,fontSize:13,color:'var(--text-muted)',textAlign:'center'}}>No materials recorded yet</div>}</div></div>
            <div className="card"><div className="card-header"><div className="card-title">Top Cost Items</div><div className="card-sub">Materials driving most spend</div></div><div style={{ padding: '0 8px 8px' }}>{materialUsageItems.length ? materialUsageItems.slice(0,5).map(([name, usage]) => { const maxVal = materialUsageItems[0][1].total || 1; return <div key={`top-${name}`} style={{padding:'10px 12px',borderBottom:'1px solid var(--border-subtle)'}}><div style={{display:'flex',justifyContent:'space-between',marginBottom:5}}><div style={{fontSize:13,fontWeight:500}}>{name}</div><div style={{fontWeight:700}}>{formatTzsFromUsd(usage.total)}</div></div><div style={{height:4,borderRadius:20,background:'var(--border)',overflow:'hidden'}}><div style={{height:'100%',width:`${Math.round((usage.total/maxVal)*100)}%`,background:'var(--red)',borderRadius:20}} /></div></div>; }) : <div style={{padding:20,fontSize:13,color:'var(--text-muted)',textAlign:'center'}}>No data</div>}</div></div>
          </div>
          <div className="card">
            <div className="card-header"><div className="card-title">Materials by Record</div><div className="card-sub">Price entered by staff at market rate at time of submission</div></div>
            <table className="data-table">
                <thead><tr><th>Record</th><th>Unit</th><th>Material</th><th>Qty</th><th>Price/Unit (Market)</th><th>Total</th></tr></thead>
              <tbody>{materialsByRequestRows.length ? materialsPageRows.map((row, idx) => <tr key={`mat-${row.reqId}-${materialsCurrentPage}-${idx}`}><td style={{fontSize:'12.5px'}}>{row.reqId}</td><td>{row.unit}</td><td style={{fontSize:13}}>{row.matName}</td><td style={{textAlign:'center'}}>{row.qty} {row.unitQty}</td><td style={{fontVariantNumeric:'tabular-nums'}}>{formatTzsFromUsd(row.unitPrice)}</td><td style={{fontWeight:700,fontVariantNumeric:'tabular-nums'}}>{formatTzsFromUsd(row.total)}</td></tr>) : <tr><td colSpan={6} style={{textAlign:'center',padding:30,color:'var(--text-muted)'}}>No material usage recorded</td></tr>}</tbody>
            </table>
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 10, padding: '10px 12px 12px', borderTop: '1px solid var(--border-subtle)' }}>
              <div style={{ fontSize: 12, color: 'var(--text-muted)' }}>Showing {materialsStart}-{materialsEnd} of {materialsByRequestRows.length}</div>
              <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                <button
                  className="btn-ghost"
                  style={{ fontSize: 12, padding: '6px 10px' }}
                  onClick={() => setMaterialsPage((p) => Math.max(1, Math.min(materialsCurrentPage, p) - 1))}
                  disabled={materialsCurrentPage <= 1}
                >
                  Previous
                </button>
                <div style={{ fontSize: 12, color: 'var(--text-muted)' }}>Page {materialsCurrentPage} / {materialsTotalPages}</div>
                <button
                  className="btn-ghost"
                  style={{ fontSize: 12, padding: '6px 10px' }}
                  onClick={() => setMaterialsPage((p) => Math.min(materialsTotalPages, Math.min(materialsCurrentPage, p) + 1))}
                  disabled={materialsCurrentPage >= materialsTotalPages}
                >
                  Next
                </button>
              </div>
            </div>
          </div>
        </>
      )}

      {tab === 'analytics' && (
        <>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16, marginBottom: 16 }}>
            <div className="card"><div className="card-header"><div className="card-title">Cost by Unit</div><div className="card-sub">Which units cost the most</div></div><div style={{ padding: '0 8px 12px' }}>{byUnit.length ? byUnit.slice(0,8).map(([label, value]) => { const maxVal = byUnit[0][1] || 1; return <div key={label} style={{padding:'8px 12px',borderBottom:'1px solid var(--border-subtle)'}}><div style={{display:'flex',alignItems:'center',justifyContent:'space-between',marginBottom:5}}><div style={{fontSize:13,fontWeight:500}}>{label}</div><div style={{fontSize:13,fontWeight:700,fontVariantNumeric:'tabular-nums'}}>{formatTzsFromUsd(value)}</div></div><div style={{height:5,borderRadius:20,background:'var(--border)',overflow:'hidden'}}><div style={{height:'100%',width:`${Math.round((value/maxVal)*100)}%`,background:'var(--accent)',borderRadius:20}} /></div></div>; }) : <div style={{padding:20,color:'var(--text-muted)'}}>No data</div>}</div></div>
            <div className="card"><div className="card-header"><div className="card-title">Cost by Tenant</div><div className="card-sub">Tenant responsibility for damage</div></div><div style={{ padding: '0 8px 12px' }}>{byTenant.length ? byTenant.slice(0,8).map(([label, value]) => { const maxVal = byTenant[0][1] || 1; return <div key={`tenant-${label}`} style={{padding:'8px 12px',borderBottom:'1px solid var(--border-subtle)'}}><div style={{display:'flex',alignItems:'center',justifyContent:'space-between',marginBottom:5}}><div style={{fontSize:13,fontWeight:500}}>{label}</div><div style={{fontSize:13,fontWeight:700,fontVariantNumeric:'tabular-nums'}}>{formatTzsFromUsd(value)}</div></div><div style={{height:5,borderRadius:20,background:'var(--border)',overflow:'hidden'}}><div style={{height:'100%',width:`${Math.round((value/maxVal)*100)}%`,background:'var(--accent)',borderRadius:20}} /></div></div>; }) : <div style={{padding:20,color:'var(--text-muted)'}}>No data</div>}</div></div>
          </div>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16 }}>
            <div className="card"><div className="card-header"><div className="card-title">Cost by Category</div></div><div style={{ padding: '0 8px 12px' }}>{byCategory.length ? byCategory.map(([label, value]) => { const maxVal = byCategory[0][1] || 1; return <div key={`cat-${label}`} style={{padding:'8px 12px',borderBottom:'1px solid var(--border-subtle)'}}><div style={{display:'flex',alignItems:'center',justifyContent:'space-between',marginBottom:5}}><div style={{fontSize:13}}>{CAT_ICONS[label] || '🔧'} {label}</div><div style={{fontSize:13,fontWeight:700}}>{formatTzsFromUsd(value)}</div></div><div style={{height:5,borderRadius:20,background:'var(--border)',overflow:'hidden'}}><div style={{height:'100%',width:`${Math.round((value/maxVal)*100)}%`,background:'var(--amber)',borderRadius:20}} /></div></div>; }) : <div style={{padding:20,color:'var(--text-muted)'}}>No data</div>}</div></div>
            <div className="card" style={{ height: '100%', display: 'flex', flexDirection: 'column' }}><div className="card-header"><div className="card-title">Monthly Trend</div></div><div style={{padding:'0 8px 12px',flex:1,display:'flex'}}><div style={{display:'flex',alignItems:'stretch',gap:8,height:'100%',width:'100%',padding:'12px 12px 0',borderBottom:'1px solid var(--border-subtle)'}}>{(() => { const months=['Oct','Nov','Dec','Jan','Feb','Mar']; const costs=[340000,520000,180000,620000,290000,normalizedTickets.reduce((s,r)=>s + Number(r.total_cost || 0),0)]; const maxCost=Math.max(...costs,1); return months.map((m,i)=><div key={m} style={{flex:1,display:'grid',gridTemplateRows:'auto 1fr auto',alignItems:'stretch',rowGap:6,height:'100%'}}><div style={{fontSize:10,color:'var(--text-muted)',textAlign:'center'}}>{formatCompactTzsFromUsd(costs[i])}</div><div style={{width:'100%',display:'flex',alignItems:'flex-end'}}><div style={{width:'100%',background:i===5?'var(--accent)':'var(--accent-dim)',borderRadius:'4px 4px 0 0',height:`${Math.round((costs[i]/maxCost)*100)}%`,minHeight:6}} /></div><div style={{fontSize:11,color:'var(--text-muted)',textAlign:'center'}}>{m}</div></div>); })()}</div></div></div>
          </div>
        </>
      )}

      <div className={`drawer-overlay ${selected ? 'open' : ''}`} onClick={(e) => e.target === e.currentTarget && setSelected(null)}>
        <div className="drawer">
          {selected && (
            <>
              <div className="drawer-header">
                <div style={{ display: 'flex', alignItems: 'flex-start', gap: 14 }}>
                  <div style={{ width: 46, height: 46, borderRadius: 12, background: 'var(--bg-surface)', border: '1px solid var(--border)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 22, flexShrink: 0 }}>{CAT_ICONS[selected.category] || '🪛'}</div>
                  <div>
                    <div style={{ fontSize: 17, fontWeight: 700, letterSpacing: '-.3px' }}>{selected.title}</div>
                    <div style={{ fontSize: 12, color: 'var(--text-muted)', marginTop: 5, display: 'flex', gap: 8 }}>
                      <span style={{ color: (PRIORITY_META[selected.priority] || PRIORITY_META.low).color }}>{(PRIORITY_META[selected.priority] || PRIORITY_META.low).label} priority</span>
                      <span>·</span><span>{selected.category}</span><span>·</span><span>{selected.record_number || buildRecordNumber(selected)}</span>
                    </div>
                  </div>
                </div>
                <button className="drawer-close" onClick={() => setSelected(null)}>✕</button>
              </div>

              <div className="drawer-body">
                <div className="drawer-section">
                  <div className="drawer-section-title">Approval Workflow</div>
                  {(selected.workflow_status || normalizeWorkflowStatus(selected)) === 'pending_accountant' && (
                    <div style={{background:'var(--amber-dim)',border:'1px solid var(--amber)',borderRadius:10,padding:'14px 16px',marginBottom:16,display:'flex',alignItems:'center',justifyContent:'space-between'}}>
                      <div><div style={{fontWeight:600,fontSize:'13.5px'}}>Awaiting Accountant Approval</div><div style={{fontSize:12,color:'var(--text-muted)',marginTop:2}}>Review cost estimate and materials</div></div>
                      <div style={{display:'flex',gap:8}}>
                        <button className="btn-danger" style={{fontSize:'12.5px',padding:'6px 14px'}} onClick={() => updateWorkflowStatus(selected, 'rejected')}>Reject</button>
                        <button className="btn-primary" style={{fontSize:'12.5px',padding:'6px 14px',background:'var(--green)'}} onClick={() => updateWorkflowStatus(selected, 'pending_manager')}>✓ Approve Cost</button>
                      </div>
                    </div>
                  )}
                  {(selected.workflow_status || normalizeWorkflowStatus(selected)) === 'pending_manager' && (
                    <div style={{background:'var(--accent-dim)',border:'1px solid var(--accent)',borderRadius:10,padding:'14px 16px',marginBottom:16,display:'flex',alignItems:'center',justifyContent:'space-between'}}>
                      <div><div style={{fontWeight:600,fontSize:'13.5px'}}>Awaiting Your Approval</div><div style={{fontSize:12,color:'var(--text-muted)',marginTop:2}}>Final approval to proceed with work</div></div>
                      <div style={{display:'flex',gap:8}}>
                        <button className="btn-danger" style={{fontSize:'12.5px',padding:'6px 14px'}} onClick={() => updateWorkflowStatus(selected, 'rejected')}>Reject</button>
                        <button className="btn-primary" style={{fontSize:'12.5px',padding:'6px 14px'}} onClick={() => updateWorkflowStatus(selected, 'approved')}>✓ Approve Work</button>
                      </div>
                    </div>
                  )}
                  {(selected.workflow_status || normalizeWorkflowStatus(selected)) === 'approved' && (
                    <div style={{background:'var(--green-dim)',border:'1px solid var(--green)',borderRadius:10,padding:'14px 16px',marginBottom:16,display:'flex',alignItems:'center',justifyContent:'space-between'}}>
                      <div><div style={{fontWeight:600,color:'var(--green)'}}>Approved — Work can proceed</div></div>
                      <button className="btn-primary" style={{fontSize:'12.5px',padding:'6px 14px'}} onClick={() => updateWorkflowStatus(selected, 'in_progress')}>Mark In Progress</button>
                    </div>
                  )}
                  {(selected.workflow_status || normalizeWorkflowStatus(selected)) === 'in_progress' && (
                    <div style={{background:'var(--accent-dim)',border:'1px solid var(--accent)',borderRadius:10,padding:'14px 16px',marginBottom:16,display:'flex',alignItems:'center',justifyContent:'space-between'}}>
                      <div><div style={{fontWeight:600}}>Work In Progress</div></div>
                      <button className="btn-primary" style={{fontSize:'12.5px',padding:'6px 14px',background:'var(--green)'}} onClick={() => updateWorkflowStatus(selected, 'completed')}>✓ Mark Completed</button>
                    </div>
                  )}
                  <div style={{ display: 'flex', gap: 0, alignItems: 'center', marginBottom: 16 }}>
                    {[{ key: 'pending_accountant', label: 'Submitted' }, { key: 'pending_manager', label: 'Accountant' }, { key: 'approved', label: 'Manager' }, { key: 'completed', label: 'Completed' }].map((step, i, arr) => {
                      const currentStatus = selected.workflow_status || normalizeWorkflowStatus(selected);
                      const done = statusRank(currentStatus) >= statusRank(step.key);
                      return (
                        <div key={step.key} style={{ flex: 1, textAlign: 'center', position: 'relative' }}>
                          {i > 0 && <div style={{ position: 'absolute', top: 14, left: 0, right: '50%', height: 2, background: done ? 'var(--green)' : 'var(--border)' }} />}
                          {i < arr.length - 1 && <div style={{ position: 'absolute', top: 14, left: '50%', right: 0, height: 2, background: statusRank(currentStatus) > statusRank(step.key) ? 'var(--green)' : 'var(--border)' }} />}
                          <div style={{ width: 28, height: 28, borderRadius: '50%', background: done ? 'var(--green)' : 'var(--border)', color: done ? '#fff' : 'var(--text-muted)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 13, margin: '0 auto 5px', position: 'relative', zIndex: 1 }}>{done ? '✓' : i + 1}</div>
                          <div style={{ fontSize: 11, fontWeight: 600, color: done ? 'var(--green)' : 'var(--text-muted)' }}>{step.label}</div>
                        </div>
                      );
                    })}
                  </div>

                  <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, minmax(0, 1fr))', gap: 6, background: 'var(--bg-elevated)', borderRadius: 10, padding: 6 }}>
                    {['draft', 'pending_accountant', 'pending_manager', 'approved', 'in_progress', 'completed', 'rejected'].map((s) => {
                      const currentStatus = selected.workflow_status || normalizeWorkflowStatus(selected);
                      const active = currentStatus === s;
                      return (
                        <button
                          key={s}
                          onClick={() => updateWorkflowStatus(selected, s)}
                          style={{
                            padding: '7px 8px',
                            borderRadius: 8,
                            border: 'none',
                            fontSize: 11,
                            fontWeight: 500,
                            fontFamily: 'inherit',
                            cursor: 'pointer',
                            background: active ? (STATUS_META[s]?.bg || 'var(--bg-surface)') : 'none',
                            color: active ? (STATUS_META[s]?.color || 'var(--text-primary)') : 'var(--text-muted)',
                          }}
                        >
                          {(STATUS_META[s] || { label: s }).label}
                        </button>
                      );
                    })}
                  </div>
                </div>

                <div className="drawer-section">
                  <div className="drawer-section-title">Details</div>
                  <div className="kv-grid">
                    <div className="kv"><div className="kv-label">Status</div><div className="kv-value" style={{ color: (STATUS_META[selected.workflow_status || normalizeWorkflowStatus(selected)] || STATUS_META.draft).color }}>{(STATUS_META[selected.workflow_status || normalizeWorkflowStatus(selected)] || STATUS_META.draft).label}</div></div>
                    <div className="kv"><div className="kv-label">Unit</div><div className="kv-value accent">{selected.unit_ref}</div></div>
                    <div className="kv"><div className="kv-label">Reported</div><div className="kv-value" style={{ fontSize: '12.5px' }}>{selected.reported_date}</div></div>
                    <div className="kv"><div className="kv-label">Category</div><div className="kv-value" style={{ fontSize: 13 }}>{selected.category}</div></div>
                    <div className="kv"><div className="kv-label">Materials</div><div className="kv-value">{selected.material_cost ? formatTzsFromUsd(selected.material_cost) : '—'}</div></div>
                    <div className="kv"><div className="kv-label">Labour</div><div className="kv-value">{selected.labour ? formatTzsFromUsd(selected.labour) : '—'}</div></div>
                  </div>
                </div>

                <div className="drawer-section">
                  <div className="drawer-section-title">Materials & Costs</div>
                  <div style={{fontSize:13,color:'var(--text-muted)',padding:'8px 0'}}>No materials listed</div>
                  <div style={{background:'var(--bg-elevated)',borderRadius:9,padding:'10px 12px',marginTop:8,fontSize:13}}>
                    <div style={{display:'flex',justifyContent:'space-between',padding:'3px 0'}}><span style={{color:'var(--text-muted)'}}>Materials Total (market rate)</span><strong>{selected.material_cost ? formatTzsFromUsd(selected.material_cost) : '—'}</strong></div>
                    <div style={{display:'flex',justifyContent:'space-between',padding:'3px 0'}}><span style={{color:'var(--text-muted)'}}>Labour</span><strong>{selected.labour ? formatTzsFromUsd(selected.labour) : '—'}</strong></div>
                    <div style={{display:'flex',justifyContent:'space-between',paddingTop:8,marginTop:6,borderTop:'1px solid var(--border)'}}><span style={{fontWeight:700}}>Total Cost</span><strong style={{color:'var(--accent)'}}>{selected.total_cost ? formatTzsFromUsd(selected.total_cost) : '—'}</strong></div>
                  </div>
                </div>

                <div className="drawer-section">
                  <div className="drawer-section-title">Image Evidence</div>
                  <div style={{fontSize:13,color:'var(--text-muted)'}}>No images attached</div>
                </div>

                <div className="drawer-section">
                  <div className="drawer-section-title">Approval Log</div>
                  <div style={{display:'flex',flexDirection:'column',gap:8}}>
                    <div style={{background:'var(--bg-elevated)',borderRadius:8,padding:'10px 12px'}}>
                      <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',marginBottom:4}}>
                        <span style={{fontSize:13,fontWeight:600}}>{selected.assignee || 'Staff'}</span>
                        <span style={{fontSize:'11.5px',color:'var(--text-muted)'}}>{selected.reported_date}</span>
                      </div>
                      <div style={{fontSize:12,color:'var(--text-muted)',textTransform:'capitalize'}}>{(selected.workflow_status || normalizeWorkflowStatus(selected)).replaceAll('_', ' ')}</div>
                    </div>
                  </div>
                </div>

                {selected.description && (
                  <div className="drawer-section">
                    <div className="drawer-section-title">Description</div>
                    <div style={{ background: 'var(--bg-elevated)', borderRadius: 9, padding: '12px 13px', fontSize: 13, color: 'var(--text-secondary)', lineHeight: 1.6 }}>{selected.description}</div>
                  </div>
                )}

                <div className="drawer-section">
                  <div className="drawer-section-title">Notes & Updates</div>
                  {notes.length === 0 && <div style={{ fontSize: 13, color: 'var(--text-muted)', padding: '4px 0' }}>No notes yet.</div>}
                  {notes.map((n, i) => (
                    <div key={i} style={{ display: 'flex', gap: 10, padding: '10px 0', borderBottom: '1px solid var(--border-subtle)' }}>
                      <div style={{ width: 28, height: 28, borderRadius: '50%', background: 'var(--accent-dim)', color: 'var(--accent)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 10, fontWeight: 700, flexShrink: 0 }}>{n.av}</div>
                      <div style={{ flex: 1 }}><div style={{ fontSize: 12, fontWeight: 600 }}>{n.author} <span style={{ fontSize: 11, color: 'var(--text-muted)', fontWeight: 400 }}>{n.date}</span></div><div style={{ fontSize: 13, color: 'var(--text-secondary)', marginTop: 3, lineHeight: 1.5 }}>{n.text}</div></div>
                    </div>
                  ))}
                  <div style={{ marginTop: 12, display: 'flex', gap: 8, alignItems: 'flex-start', background: 'var(--bg-elevated)', borderRadius: 10, padding: '10px 12px', border: '1px solid var(--border)' }}>
                    <textarea value={note} onChange={(e) => setNote(e.target.value)} placeholder="Add a note…" rows={2} style={{ flex: 1, background: 'none', border: 'none', outline: 'none', fontSize: 13, fontFamily: 'inherit', color: 'var(--text-primary)', resize: 'none', minHeight: 36, lineHeight: 1.5 }} />
                    <button onClick={addNote} style={{ width: 30, height: 30, borderRadius: 7, background: 'var(--accent)', border: 'none', color: '#fff', display: 'flex', alignItems: 'center', justifyContent: 'center', cursor: 'pointer', flexShrink: 0, alignSelf: 'flex-end' }}>
                      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    </button>
                  </div>
                </div>
              </div>

              <div className="drawer-footer">
                <button className="btn-primary" style={{ flex: 1, justifyContent: 'center' }} onClick={() => updateWorkflowStatus(selected, (selected.workflow_status || normalizeWorkflowStatus(selected)) === 'completed' ? 'in_progress' : 'completed')}>
                  {(selected.workflow_status || normalizeWorkflowStatus(selected)) === 'completed' ? '↺ Reopen' : '✓ Mark Completed'}
                </button>
                <button className="btn-danger" onClick={() => { router.delete(`/maintenance/${selected.id}`); setSelected(null); }}>
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
                </button>
              </div>
            </>
          )}
        </div>
      </div>

      <div className={`modal-overlay ${showModal ? 'open' : ''}`} onClick={(e) => e.target === e.currentTarget && setShowModal(false)}>
        <div className="modal" style={{ width: 660, maxHeight: '92vh', display: 'flex', flexDirection: 'column' }}>
          <div className="modal-header" style={{ flexShrink: 0 }}><div className="modal-title">New Maintenance Request</div><button className="modal-close" onClick={() => setShowModal(false)}>✕</button></div>
          <form onSubmit={(e) => submitRequest(e, 'pending_accountant')} style={{ display: 'flex', flexDirection: 'column', height: '100%' }}>
            <div className="modal-body" style={{ flex: 1, overflowY: 'auto' }}>
              <div style={{ fontSize: '10.5px', fontWeight: 700, letterSpacing: '.6px', textTransform: 'uppercase', color: 'var(--text-muted)', marginBottom: 12 }}>Request Details</div>
              <div className="form-row">
                <div className="form-group"><label className="form-label">Unit *</label><select className="form-input form-select" value={data.unit_ref} onChange={(e) => setData('unit_ref', e.target.value)} required><option value="">Select unit…</option><option value="Common">Common Area</option>{units.map((u) => <option key={u.id || u.unit_number} value={u.unit_number}>{u.unit_number}</option>)}</select></div>
                <div className="form-group"><label className="form-label">Category *</label><select className="form-input form-select" value={data.category} onChange={(e) => setData('category', e.target.value)} required><option value="">Select…</option>{Object.keys(CAT_ICONS).map((c) => <option key={c} value={c}>{c}</option>)}</select></div>
              </div>
              <div className="form-row">
                <div className="form-group"><label className="form-label">Priority *</label><select className="form-input form-select" value={data.priority} onChange={(e) => setData('priority', e.target.value)}><option value="low">Low — can wait</option><option value="med">Medium — within a week</option><option value="high">High — within 48 hours</option><option value="critical">Critical — immediate</option></select></div>
                <div className="form-group"><label className="form-label">Assign To</label><select className="form-input form-select" value={data.assignee} onChange={(e) => setData('assignee', e.target.value)}><option value="">Unassigned</option>{['Peter Ng.', 'JK Electric', 'Cool Air Ltd', 'In-house', 'SecurePro'].map((a) => <option key={a} value={a}>{a}</option>)}</select></div>
              </div>
              <div className="form-group"><label className="form-label">Title *</label><input className="form-input" type="text" value={data.title} onChange={(e) => setData('title', e.target.value)} placeholder="e.g. Broken water pipe in bathroom" required /></div>
              <div className="form-group"><label className="form-label">Description & Observations</label><textarea className="form-input" value={data.description} onChange={(e) => setData('description', e.target.value)} rows={3} style={{ resize: 'vertical' }} placeholder="Describe the issue, when it started, any safety concerns…" /></div>
              <div className="form-row">
                <div className="form-group"><label className="form-label">Estimated Labour (TZS)</label><input className="form-input" type="number" value={data.labour} onChange={(e) => setData('labour', e.target.value)} placeholder="0" /></div>
                <div className="form-group"><label className="form-label">Scheduled Date (if planned)</label><input className="form-input" type="date" value={data.scheduled_date} onChange={(e) => setData('scheduled_date', e.target.value)} /></div>
              </div>

              <div style={{ borderTop: '1px solid var(--border-subtle)', paddingTop: 16, marginTop: 8 }}>
                <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 12 }}>
                  <div style={{ fontSize: '10.5px', fontWeight: 700, letterSpacing: '.6px', textTransform: 'uppercase', color: 'var(--text-muted)' }}>Materials Required</div>
                  <button type="button" className="btn-ghost" style={{ fontSize: '12.5px', padding: '5px 12px' }} onClick={addMaterialRow}>+ Add Material</button>
                </div>
                {materials.length === 0 && <div style={{ fontSize: 13, color: 'var(--text-muted)', padding: '8px 0' }}>No materials added yet</div>}
                {materials.map((row) => (
                  <div key={row.id} style={{ display: 'grid', gridTemplateColumns: '1fr 90px 90px 120px 32px', gap: 8, alignItems: 'center', marginBottom: 8 }}>
                    <input className="form-input" type="text" placeholder="Material name" style={{ fontSize: '12.5px' }} value={row.name} onChange={(e) => updateMaterialRow(row.id, 'name', e.target.value)} />
                    <input className="form-input" type="text" placeholder="Unit" style={{ fontSize: '12.5px' }} value={row.unit} onChange={(e) => updateMaterialRow(row.id, 'unit', e.target.value)} />
                    <input className="form-input" type="number" placeholder="Qty" min="1" style={{ fontSize: '12.5px' }} value={row.qty} onChange={(e) => updateMaterialRow(row.id, 'qty', Number(e.target.value || 0))} />
                    <input className="form-input" type="number" placeholder="Price/unit" style={{ fontSize: '12.5px' }} value={row.unit_price} onChange={(e) => updateMaterialRow(row.id, 'unit_price', Number(e.target.value || 0))} />
                    <button type="button" onClick={() => removeMaterialRow(row.id)} style={{ background: 'none', border: 'none', color: 'var(--red)', cursor: 'pointer', fontSize: 18, lineHeight: 1 }}>×</button>
                  </div>
                ))}
              </div>

              <div style={{ borderTop: '1px solid var(--border-subtle)', paddingTop: 16, marginTop: 8 }}>
                <div style={{ fontSize: '10.5px', fontWeight: 700, letterSpacing: '.6px', textTransform: 'uppercase', color: 'var(--text-muted)', marginBottom: 12 }}>Image Evidence (Optional)</div>
                <div
                  style={{ border: '2px dashed var(--border)', borderRadius: 10, padding: 20, textAlign: 'center', cursor: 'pointer', transition: 'border-color .15s' }}
                  onClick={() => document.getElementById('mr-image-input')?.click()}
                  onDragOver={(e) => { e.preventDefault(); e.currentTarget.style.borderColor = 'var(--accent)'; }}
                  onDragLeave={(e) => { e.currentTarget.style.borderColor = 'var(--border)'; }}
                  onDrop={(e) => {
                    e.preventDefault();
                    e.currentTarget.style.borderColor = 'var(--border)';
                    handleImageUpload(e.dataTransfer?.files);
                  }}
                >
                  <div style={{ fontSize: 13, color: 'var(--text-muted)' }}>📷 Click or drag images here (JPG, PNG)</div>
                  <input id="mr-image-input" type="file" accept="image/*" multiple style={{ display: 'none' }} onChange={(e) => handleImageUpload(e.target.files)} />
                </div>
                {images.length > 0 && (
                  <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8, marginTop: 10 }}>
                    {images.map((img, i) => (
                      <div key={`${img.slice(0, 24)}-${i}`} style={{ position: 'relative' }}>
                        <img src={img} alt="evidence" style={{ width: 72, height: 72, objectFit: 'cover', borderRadius: 6, border: '1px solid var(--border)' }} />
                        <button
                          type="button"
                          onClick={() => setImages((prev) => prev.filter((_, idx) => idx !== i))}
                          style={{ position: 'absolute', top: -4, right: -4, width: 18, height: 18, borderRadius: '50%', background: 'var(--red)', color: '#fff', border: 'none', cursor: 'pointer', fontSize: 11, lineHeight: 1 }}
                        >
                          ×
                        </button>
                      </div>
                    ))}
                  </div>
                )}
              </div>

              {(materialTotal > 0 || labourTotal > 0) && (
                <div className="nl-summary-card" style={{ marginTop: 16 }}>
                  <div className="nl-summary-row"><span>Materials (market rate)</span><strong>{formatTzsFromUsd(materialTotal)}</strong></div>
                  <div className="nl-summary-row"><span>Labour</span><strong>{formatTzsFromUsd(labourTotal)}</strong></div>
                  <div className="nl-summary-row" style={{ borderTop: '1px solid var(--border)', paddingTop: 8, marginTop: 4 }}><span style={{ fontWeight: 600 }}>Estimated Total</span><strong style={{ fontSize: 15 }}>{formatTzsFromUsd(estimateTotal)}</strong></div>
                </div>
              )}
            </div>
            <div className="modal-footer" style={{ flexShrink: 0 }}>
              <button type="button" className="btn-ghost" onClick={() => setShowModal(false)}>Cancel</button>
              <button type="button" className="btn-secondary" onClick={(e) => submitRequest(e, 'draft')} disabled={processing}>Save Draft</button>
              <button type="submit" className="btn-primary" disabled={processing}>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><path d="M22 2L11 13"/><path d="M22 2L15 22 11 13 2 9l20-7z"/></svg>
                Submit for Approval
              </button>
            </div>
          </form>
        </div>
      </div>

      <div className={`modal-overlay ${showScheduleModal ? 'open' : ''}`} onClick={(e) => e.target === e.currentTarget && setShowScheduleModal(false)}>
        <div className="modal" style={{ width: 540 }}>
          <div className="modal-header"><div className="modal-title">Schedule Maintenance Task</div><button className="modal-close" onClick={() => setShowScheduleModal(false)}>✕</button></div>
          <div className="modal-body">
            <div className="form-row">
              <div className="form-group"><label className="form-label">Task Title *</label><input className="form-input" type="text" placeholder="e.g. Quarterly AC service" value={scheduleForm.title} onChange={(e) => setScheduleForm((s) => ({ ...s, title: e.target.value }))} /></div>
              <div className="form-group"><label className="form-label">Unit / Area *</label><input className="form-input" type="text" placeholder="e.g. All units, Rooftop" value={scheduleForm.unit} onChange={(e) => setScheduleForm((s) => ({ ...s, unit: e.target.value }))} /></div>
            </div>
            <div className="form-row">
              <div className="form-group"><label className="form-label">Category</label><select className="form-input form-select" value={scheduleForm.category} onChange={(e) => setScheduleForm((s) => ({ ...s, category: e.target.value }))}>{Object.keys(CAT_ICONS).map((c) => <option key={c}>{c}</option>)}</select></div>
              <div className="form-group"><label className="form-label">Frequency</label><select className="form-input form-select" value={scheduleForm.frequency} onChange={(e) => setScheduleForm((s) => ({ ...s, frequency: e.target.value }))}><option value="weekly">Weekly</option><option value="monthly">Monthly</option><option value="quarterly">Quarterly</option><option value="biannual">Every 6 months</option><option value="annual">Annual</option></select></div>
            </div>
            <div className="form-row">
              <div className="form-group"><label className="form-label">Next Due Date *</label><input className="form-input" type="date" value={scheduleForm.next_due} onChange={(e) => setScheduleForm((s) => ({ ...s, next_due: e.target.value }))} /></div>
              <div className="form-group"><label className="form-label">Assign To</label><input className="form-input" type="text" placeholder="Unassigned" value={scheduleForm.assignee} onChange={(e) => setScheduleForm((s) => ({ ...s, assignee: e.target.value }))} /></div>
            </div>
            <div className="form-group"><label className="form-label">Notes</label><textarea className="form-input" rows={2} style={{ resize: 'vertical' }} value={scheduleForm.notes} onChange={(e) => setScheduleForm((s) => ({ ...s, notes: e.target.value }))} /></div>
          </div>
          <div className="modal-footer"><button className="btn-ghost" onClick={() => setShowScheduleModal(false)}>Cancel</button><button className="btn-primary" onClick={() => {
            if (!scheduleForm.title.trim() || !scheduleForm.unit.trim() || !scheduleForm.next_due) return;
            setScheduleTasks((prev) => [...prev, {
              id: `SC-${String(prev.length + 1).padStart(3, '0')}`,
              title: scheduleForm.title.trim(),
              unit: scheduleForm.unit.trim(),
              category: scheduleForm.category,
              frequency: scheduleForm.frequency,
              next_due: scheduleForm.next_due,
              assignee: scheduleForm.assignee.trim(),
              status: 'upcoming',
            }]);
            setScheduleForm({ title: '', unit: '', category: 'General', frequency: 'monthly', next_due: '', assignee: '', notes: '' });
            setShowScheduleModal(false);
          }}>Schedule Task</button></div>
        </div>
      </div>
    </AppLayout>
  );
}
