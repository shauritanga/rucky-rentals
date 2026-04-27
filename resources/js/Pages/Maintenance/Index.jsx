import { useEffect, useMemo, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm, router, usePage } from '@inertiajs/react';
import useExchangeRate from '@/hooks/useExchangeRate';
import { formatDisplayDate } from '@/utils/dateFormat';

const CAT_ICONS = {
  Plumbing: '🔧',
  Electrical: '💡',
  'Air Condition': '❄️',
  HVAC: '❄️', // legacy category label compatibility
  Lift: '🛗',
  General: '🪛',
  Security: '🔒',
  Structural: '🏗️',
  Cleaning: '🧹',
};

const CATEGORY_OPTIONS = [
  'Plumbing',
  'Electrical',
  'Air Condition',
  'Lift',
  'General',
  'Security',
  'Structural',
  'Cleaning',
];

const REQUEST_FILTERS = [
  ['all',             'All'],
  ['submitted',       'Submitted'],
  ['pending_manager', 'Pending Manager'],
  ['approved',        'Approved'],
  ['in_progress',     'In Progress'],
  ['resolved',        'Resolved'],
];

const STATUS_META = {
  submitted:       { label: 'Submitted',       bg: 'var(--bg-elevated)',  color: 'var(--text-secondary)' },
  pending_manager: { label: 'Pending Manager', bg: 'var(--amber-dim)',    color: 'var(--amber)'          },
  approved:        { label: 'Approved',        bg: 'var(--green-dim)',    color: 'var(--green)'          },
  in_progress:     { label: 'In Progress',     bg: 'var(--accent-dim)',   color: 'var(--accent)'         },
  resolved:        { label: 'Resolved',        bg: 'var(--green-dim)',    color: 'var(--green)'          },
  open:            { label: 'Open',            bg: 'var(--amber-dim)',    color: 'var(--amber)'          },
};

const PRIORITY_META = {
  critical: { label: 'Critical', color: '#ef4444' },
  high: { label: 'High', color: 'var(--red)' },
  med: { label: 'Medium', color: 'var(--amber)' },
  low: { label: 'Low', color: 'var(--text-muted)' },
};


const MATERIAL_HINTS = {
  Plumbing: [{ name: 'PVC pipe — 1 inch', unit: 'pc', factor: 0.18, qty: 2 }, { name: 'Ball valve — 1/2 inch', unit: 'pc', factor: 0.12, qty: 1 }],
  Electrical: [{ name: 'Circuit breaker 20A', unit: 'pc', factor: 0.2, qty: 1 }, { name: 'Cable — 2.5mm twin', unit: 'm', factor: 0.1, qty: 4 }],
  'Air Condition': [{ name: 'AC refrigerant R22', unit: 'kg', factor: 0.24, qty: 2 }],
  HVAC: [{ name: 'AC refrigerant R22', unit: 'kg', factor: 0.24, qty: 2 }], // legacy category label compatibility
  Lift: [{ name: 'Lift control relay', unit: 'pc', factor: 0.22, qty: 1 }],
  General: [{ name: 'Hinge set', unit: 'set', factor: 0.12, qty: 1 }],
  Security: [{ name: 'Mortise lock', unit: 'pc', factor: 0.16, qty: 1 }],
  Structural: [{ name: 'Roofing sealant', unit: 'tube', factor: 0.14, qty: 2 }],
  Cleaning: [{ name: 'Industrial cleaner', unit: 'L', factor: 0.1, qty: 3 }],
};

const MATERIALS_PAGE_SIZE = 10;
const REQUESTS_PAGE_SIZE = 10;

function normalizeCategory(value) {
  if (String(value || '').trim() === 'HVAC') return 'Air Condition';
  return value || 'General';
}

function normalizeWorkflowStatus(ticket) {
  if (ticket.workflow_status) return ticket.workflow_status;
  if (ticket.status === 'resolved')    return 'resolved';
  if (ticket.status === 'in-progress') return 'in_progress';
  return 'submitted';
}

function buildRecordNumber(item) {
  return item.record_number || item.ticket_number || `MR-${String(item.id).padStart(3, '0')}`;
}

function workflowToDbStatus(status) {
  if (status === 'resolved')    return 'resolved';
  if (status === 'in_progress') return 'in-progress';
  return 'open';
}

function statusRank(status) {
  return { submitted: 0, pending_manager: 1, approved: 2, in_progress: 3, resolved: 4, open: 0 }[status] ?? 0;
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

export default function MaintenanceIndex({ tickets, units, scheduledTasks = [], approvalCount = 0 }) {
  const { props } = usePage();
  const user = props?.auth?.user;
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
  const [scheduleForm, setScheduleForm] = useState({
    title: '',
    unit_ref: '',
    category: 'General',
    frequency: 'monthly',
    next_due: '',
    assignee: '',
    notes: '',
  });
  const [submitMessage, setSubmitMessage] = useState('');
  const [submitError, setSubmitError] = useState('');
  const [scheduleSubmitting, setScheduleSubmitting] = useState(false);
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

  const { formatTzs, formatCompactTzs } = useExchangeRate();

  useEffect(() => {
    if (!submitMessage) return;
    const t = setTimeout(() => setSubmitMessage(''), 4000);
    return () => clearTimeout(t);
  }, [submitMessage]);

  const normalizedTickets = useMemo(() => {
    return tickets.map((ticket) => {
      const workflowStatus = workflowStatusByTicket[ticket.id] || normalizeWorkflowStatus(ticket);
      const labour = Number(ticket.cost || 0);
      const materialCost = 0;
      const recordNumber = buildRecordNumber(ticket);
      const category = normalizeCategory(ticket.category);
      return {
        ...ticket,
        category,
        record_number: recordNumber,
        workflow_status: workflowStatus,
        labour,
        material_cost: materialCost,
        total_cost: labour + materialCost,
        materials: Array.isArray(ticket.materials) && ticket.materials.length
          ? ticket.materials
          : (MATERIAL_HINTS[category] || []).map((m) => ({
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
    const next = { all: normalizedTickets.length, submitted: 0, pending_manager: 0, approved: 0, in_progress: 0, resolved: 0 };
    normalizedTickets.forEach((ticket) => {
      const ws = ticket.workflow_status;
      if (Object.prototype.hasOwnProperty.call(next, ws)) {
        next[ws] += 1;
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

  const openRequests = counts.submitted + counts.pending_manager + counts.approved + counts.in_progress;
  const urgentCount = normalizedTickets.filter(
    (t) => ['high', 'critical'].includes(t.priority) && ['submitted', 'pending_manager', 'approved', 'in_progress'].includes(t.workflow_status),
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
      .filter((r) => ['resolved', 'in_progress'].includes(r.workflow_status))
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

  const submitRequest = (e) => {
    e.preventDefault();
    setSubmitError('');

    const formData = new FormData();
    formData.append('title', data.title);
    formData.append('description', data.description || '');
    formData.append('unit_ref', data.unit_ref);
    formData.append('category', data.category || 'General');
    formData.append('priority', data.priority);
    formData.append('assignee', data.assignee || '');
    if (data.labour) formData.append('cost', data.labour);

    // Append materials as indexed array
    materials.forEach((row, i) => {
      formData.append(`materials[${i}][name]`, row.name || '');
      formData.append(`materials[${i}][unit]`, row.unit || '');
      formData.append(`materials[${i}][qty]`, row.qty || 0);
      formData.append(`materials[${i}][unit_price]`, row.unit_price || 0);
    });

    // Append image files
    images.forEach((img) => {
      if (img.file) formData.append('images[]', img.file);
    });

    router.post('/maintenance', formData, {
      forceFormData: true,
      onSuccess: () => {
        reset();
        setMaterials([]);
        setImages([]);
        setShowModal(false);
        setSubmitMessage('Ticket submitted. Accountants have been notified for review.');
      },
      onError: () => setSubmitError('Failed to submit ticket. Please check all required fields.'),
    });
  };

  const updateWorkflowStatus = (ticket, nextWorkflowStatus) => {
    setWorkflowStatusByTicket((prev) => ({ ...prev, [ticket.id]: nextWorkflowStatus }));
    router.patch(
      `/maintenance/${ticket.id}`,
      { workflow_status: nextWorkflowStatus },
      {
        onSuccess: () => {
          setSelected((s) => (s ? { ...s, workflow_status: nextWorkflowStatus } : null));
          setSubmitMessage('Status updated successfully.');
        },
        onError: () => setSubmitMessage(''),
      },
    );
  };

  const addNote = () => {
    if (!selected || !note.trim()) return;
    router.patch(`/maintenance/${selected.id}`, { note }, {
      onSuccess: () => {
        setNote('');
        setSubmitMessage('Note added.');
      },
    });
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
    setImages((prev) => [
      ...prev,
      ...list.map((file) => ({ file, preview: URL.createObjectURL(file) })),
    ]);
  };

  const upcomingSchedule = scheduledTasks.filter((task) => task.status === 'upcoming');
  const overdueSchedule = scheduledTasks.filter((task) => task.status === 'overdue');

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
          <div className="stat-top"><div className="stat-icon amber"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div><span className="stat-delta">active</span></div>
          <div className="stat-value">{counts.in_progress}</div>
          <div className="stat-label">In Progress</div>
        </div>
        <div className="stat-card">
          <div className="stat-top"><div className="stat-icon green"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="20 6 9 17 4 12"/></svg></div><span className="stat-delta up">this month</span></div>
          <div className="stat-value">{counts.resolved}</div>
          <div className="stat-label">Resolved</div>
        </div>
        <div className="stat-card">
          <div className="stat-top"><div className="stat-icon amber"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg></div><span className="stat-delta down">market rate</span></div>
          <div className="stat-value">{formatCompactTzs(totalCostUsd)}</div>
          <div className="stat-label">Total Cost (Market)</div>
        </div>
      </div>

      {submitMessage && (
        <div style={{ background: 'var(--green-dim)', color: 'var(--green)', border: '1px solid var(--green)', borderRadius: 9, padding: '10px 14px', fontSize: 13, fontWeight: 500, marginBottom: 14, display: 'flex', alignItems: 'center', gap: 8 }}>
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><polyline points="20 6 9 17 4 12"/></svg>
          {submitMessage}
        </div>
      )}

      <div className="team-tabs" style={{ marginBottom: 18 }}>
        <button className={`team-tab ${tab === 'requests' ? 'active' : ''}`} onClick={() => setTab('requests')}>Requests</button>
        <button className={`team-tab ${tab === 'schedule' ? 'active' : ''}`} onClick={() => setTab('schedule')}>Schedule</button>
        <button className={`team-tab ${tab === 'materials' ? 'active' : ''}`} onClick={() => setTab('materials')}>Materials</button>
        <button className={`team-tab ${tab === 'analytics' ? 'active' : ''}`} onClick={() => setTab('analytics')}>Analytics</button>
      </div>

      {tab === 'requests' && (
        <>
          {approvalCount > 0 && (
            <div style={{ background: 'var(--amber-dim)', color: 'var(--amber)', border: '1px solid var(--amber)', borderRadius: 9, padding: '10px 14px', fontSize: 13, fontWeight: 500, marginBottom: 14, display: 'flex', alignItems: 'center', gap: 8 }}>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
              You have <strong>{approvalCount}</strong> ticket{approvalCount > 1 ? 's' : ''} pending your approval — use the filter pills to find them.
            </div>
          )}
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
                {CATEGORY_OPTIONS.map((c) => <option key={c} value={c}>{c}</option>)}
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
                  const statusMeta = STATUS_META[t.workflow_status] || STATUS_META.open;
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
                      <td style={{ fontSize: 12.5, color: 'var(--text-muted)' }}>{formatDisplayDate(t.reported_date)}</td>
                      <td>{t.material_cost ? formatTzs(t.material_cost) : '—'}</td>
                      <td>{t.labour ? formatTzs(t.labour) : '—'}</td>
                      <td style={{ fontWeight: 700 }}>{t.total_cost ? formatTzs(t.total_cost) : '—'}</td>
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
            <div className="card"><div className="card-header"><div className="card-title">Upcoming (30 days)</div></div><div style={{ padding: '0 8px 8px' }}>{upcomingSchedule.length ? upcomingSchedule.map((task) => { const u = task.unit_ref || task.unit || '—'; const d = formatDisplayDate(task.next_due); const taskCategory = normalizeCategory(task.category); return <div key={task.id} style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '10px 12px', borderRadius: 8, background: 'var(--bg-elevated)', marginBottom: 6 }}><div style={{ fontSize: 18 }}>{CAT_ICONS[taskCategory] || '🔧'}</div><div style={{ flex: 1 }}><div style={{ fontSize: 13, fontWeight: 500 }}>{task.title}</div><div style={{ fontSize: '11.5px', color: 'var(--text-muted)' }}>{u} · {task.assignee || 'Unassigned'}</div></div><div style={{ textAlign: 'right', fontSize: 12, color: 'var(--text-muted)' }}>Due {d}</div></div>; }) : <div style={{padding:16,fontSize:13,color:'var(--text-muted)'}}>No tasks due in 30 days</div>}</div></div>
            <div className="card"><div className="card-header"><div className="card-title">Overdue</div></div><div style={{ padding: '0 8px 8px' }}>{overdueSchedule.length ? overdueSchedule.map((task) => { const u = task.unit_ref || task.unit || '—'; const d = formatDisplayDate(task.next_due); const taskCategory = normalizeCategory(task.category); return <div key={task.id} style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '10px 12px', borderRadius: 8, background: 'var(--bg-elevated)', marginBottom: 6 }}><div style={{ fontSize: 18 }}>{CAT_ICONS[taskCategory] || '🔧'}</div><div style={{ flex: 1 }}><div style={{ fontSize: 13, fontWeight: 500 }}>{task.title}</div><div style={{ fontSize: '11.5px', color: 'var(--text-muted)' }}>{u} · {task.assignee || 'Unassigned'}</div></div><div style={{ textAlign: 'right', fontSize: 12, color: 'var(--red)' }}>Due {d}</div></div>; }) : <div style={{padding:16,fontSize:13,color:'var(--text-muted)'}}>No overdue tasks</div>}</div></div>
          </div>
          <div className="card">
            <div className="card-header"><div className="card-title">All Scheduled Tasks</div></div>
            <table className="data-table">
              <thead><tr><th>Task</th><th>Unit / Area</th><th>Frequency</th><th>Next Due</th><th>Assigned To</th><th>Status</th><th></th></tr></thead>
              <tbody>{scheduledTasks.length === 0 && <tr><td colSpan={7} style={{textAlign:'center',padding:30,color:'var(--text-muted)'}}>No scheduled tasks. Add one to get started.</td></tr>}{scheduledTasks.map((task) => {
                const isOverdue = task.status === 'overdue';
                const isCompleted = task.status === 'completed';
                const FREQ = { weekly:'Weekly', monthly:'Monthly', quarterly:'Quarterly', biannual:'Every 6 months', annual:'Annual' };
                const unitRef = task.unit_ref || task.unit || '—';
                const nextDue = formatDisplayDate(task.next_due);
                return <tr key={task.id}><td><div style={{fontWeight:600}}>{task.title}</div><div style={{fontSize:12,color:'var(--text-muted)'}}>{normalizeCategory(task.category)}</div></td><td>{unitRef}</td><td style={{fontSize:13}}>{FREQ[task.frequency] || task.frequency}</td><td style={{color:isOverdue?'var(--red)':'var(--text-secondary)',fontWeight:isOverdue?600:400}}>{nextDue}</td><td style={{fontSize:13}}>{task.assignee || '—'}</td><td><span style={{fontSize:12,fontWeight:600,color:isOverdue?'var(--red)':isCompleted?'var(--green)':'var(--accent)'}}>● {isOverdue?'Overdue':isCompleted?'Completed':'Upcoming'}</span></td><td style={{display:'flex',gap:6}}>{!isCompleted && <button className="btn-ghost" style={{fontSize:12,padding:'4px 8px'}} onClick={() => router.patch(`/scheduled-maintenance/${task.id}`, { status: 'completed' }, { onSuccess: () => setSubmitMessage('Task marked complete.') })}>Done</button>}<button className="btn-ghost" style={{fontSize:12,padding:'4px 8px',color:'var(--red)'}} onClick={() => router.delete(`/scheduled-maintenance/${task.id}`, {}, { onSuccess: () => setSubmitMessage('Task removed.') })}>Remove</button></td></tr>;
              })}</tbody>
            </table>
          </div>
        </>
      )}

      {tab === 'materials' && (
        <>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16, marginBottom: 16 }}>
            <div className="card"><div className="card-header"><div className="card-title">Usage This Month</div><div className="card-sub">Market price entered by staff per request</div></div><div style={{ padding: '0 8px 8px' }}>{materialUsageItems.length ? materialUsageItems.map(([name, usage]) => <div key={name} style={{display:'flex',alignItems:'center',justifyContent:'space-between',padding:'10px 12px',borderBottom:'1px solid var(--border-subtle)'}}><div><div style={{fontSize:13,fontWeight:500}}>{name}</div><div style={{fontSize:'11.5px',color:'var(--text-muted)'}}>Qty: {usage.qty} {usage.unit}</div></div><div style={{fontWeight:700,fontVariantNumeric:'tabular-nums'}}>{formatTzs(usage.total)}</div></div>) : <div style={{padding:20,fontSize:13,color:'var(--text-muted)',textAlign:'center'}}>No materials recorded yet</div>}</div></div>
            <div className="card"><div className="card-header"><div className="card-title">Top Cost Items</div><div className="card-sub">Materials driving most spend</div></div><div style={{ padding: '0 8px 8px' }}>{materialUsageItems.length ? materialUsageItems.slice(0,5).map(([name, usage]) => { const maxVal = materialUsageItems[0][1].total || 1; return <div key={`top-${name}`} style={{padding:'10px 12px',borderBottom:'1px solid var(--border-subtle)'}}><div style={{display:'flex',justifyContent:'space-between',marginBottom:5}}><div style={{fontSize:13,fontWeight:500}}>{name}</div><div style={{fontWeight:700}}>{formatTzs(usage.total)}</div></div><div style={{height:4,borderRadius:20,background:'var(--border)',overflow:'hidden'}}><div style={{height:'100%',width:`${Math.round((usage.total/maxVal)*100)}%`,background:'var(--red)',borderRadius:20}} /></div></div>; }) : <div style={{padding:20,fontSize:13,color:'var(--text-muted)',textAlign:'center'}}>No data</div>}</div></div>
          </div>
          <div className="card">
            <div className="card-header"><div className="card-title">Materials by Record</div><div className="card-sub">Price entered by staff at market rate at time of submission</div></div>
            <table className="data-table">
                <thead><tr><th>Record</th><th>Unit</th><th>Material</th><th>Qty</th><th>Price/Unit (Market)</th><th>Total</th></tr></thead>
              <tbody>{materialsByRequestRows.length ? materialsPageRows.map((row, idx) => <tr key={`mat-${row.reqId}-${materialsCurrentPage}-${idx}`}><td style={{fontSize:'12.5px'}}>{row.reqId}</td><td>{row.unit}</td><td style={{fontSize:13}}>{row.matName}</td><td style={{textAlign:'center'}}>{row.qty} {row.unitQty}</td><td style={{fontVariantNumeric:'tabular-nums'}}>{formatTzs(row.unitPrice)}</td><td style={{fontWeight:700,fontVariantNumeric:'tabular-nums'}}>{formatTzs(row.total)}</td></tr>) : <tr><td colSpan={6} style={{textAlign:'center',padding:30,color:'var(--text-muted)'}}>No material usage recorded</td></tr>}</tbody>
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
            <div className="card"><div className="card-header"><div className="card-title">Cost by Unit</div><div className="card-sub">Which units cost the most</div></div><div style={{ padding: '0 8px 12px' }}>{byUnit.length ? byUnit.slice(0,8).map(([label, value]) => { const maxVal = byUnit[0][1] || 1; return <div key={label} style={{padding:'8px 12px',borderBottom:'1px solid var(--border-subtle)'}}><div style={{display:'flex',alignItems:'center',justifyContent:'space-between',marginBottom:5}}><div style={{fontSize:13,fontWeight:500}}>{label}</div><div style={{fontSize:13,fontWeight:700,fontVariantNumeric:'tabular-nums'}}>{formatTzs(value)}</div></div><div style={{height:5,borderRadius:20,background:'var(--border)',overflow:'hidden'}}><div style={{height:'100%',width:`${Math.round((value/maxVal)*100)}%`,background:'var(--accent)',borderRadius:20}} /></div></div>; }) : <div style={{padding:20,color:'var(--text-muted)'}}>No data</div>}</div></div>
            <div className="card"><div className="card-header"><div className="card-title">Cost by Tenant</div><div className="card-sub">Tenant responsibility for damage</div></div><div style={{ padding: '0 8px 12px' }}>{byTenant.length ? byTenant.slice(0,8).map(([label, value]) => { const maxVal = byTenant[0][1] || 1; return <div key={`tenant-${label}`} style={{padding:'8px 12px',borderBottom:'1px solid var(--border-subtle)'}}><div style={{display:'flex',alignItems:'center',justifyContent:'space-between',marginBottom:5}}><div style={{fontSize:13,fontWeight:500}}>{label}</div><div style={{fontSize:13,fontWeight:700,fontVariantNumeric:'tabular-nums'}}>{formatTzs(value)}</div></div><div style={{height:5,borderRadius:20,background:'var(--border)',overflow:'hidden'}}><div style={{height:'100%',width:`${Math.round((value/maxVal)*100)}%`,background:'var(--accent)',borderRadius:20}} /></div></div>; }) : <div style={{padding:20,color:'var(--text-muted)'}}>No data</div>}</div></div>
          </div>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16 }}>
            <div className="card"><div className="card-header"><div className="card-title">Cost by Category</div></div><div style={{ padding: '0 8px 12px' }}>{byCategory.length ? byCategory.map(([label, value]) => { const maxVal = byCategory[0][1] || 1; return <div key={`cat-${label}`} style={{padding:'8px 12px',borderBottom:'1px solid var(--border-subtle)'}}><div style={{display:'flex',alignItems:'center',justifyContent:'space-between',marginBottom:5}}><div style={{fontSize:13}}>{CAT_ICONS[label] || '🔧'} {label}</div><div style={{fontSize:13,fontWeight:700}}>{formatTzs(value)}</div></div><div style={{height:5,borderRadius:20,background:'var(--border)',overflow:'hidden'}}><div style={{height:'100%',width:`${Math.round((value/maxVal)*100)}%`,background:'var(--amber)',borderRadius:20}} /></div></div>; }) : <div style={{padding:20,color:'var(--text-muted)'}}>No data</div>}</div></div>
            <div className="card" style={{ height: '100%', display: 'flex', flexDirection: 'column' }}><div className="card-header"><div className="card-title">Monthly Trend</div></div><div style={{padding:'0 8px 12px',flex:1,display:'flex'}}><div style={{display:'flex',alignItems:'stretch',gap:8,height:'100%',width:'100%',padding:'12px 12px 0',borderBottom:'1px solid var(--border-subtle)'}}>{(() => { const months=['Oct','Nov','Dec','Jan','Feb','Mar']; const costs=[340000,520000,180000,620000,290000,normalizedTickets.reduce((s,r)=>s + Number(r.total_cost || 0),0)]; const maxCost=Math.max(...costs,1); return months.map((m,i)=><div key={m} style={{flex:1,display:'grid',gridTemplateRows:'auto 1fr auto',alignItems:'stretch',rowGap:6,height:'100%'}}><div style={{fontSize:10,color:'var(--text-muted)',textAlign:'center'}}>{formatCompactTzs(costs[i])}</div><div style={{width:'100%',display:'flex',alignItems:'flex-end'}}><div style={{width:'100%',background:i===5?'var(--accent)':'var(--accent-dim)',borderRadius:'4px 4px 0 0',height:`${Math.round((costs[i]/maxCost)*100)}%`,minHeight:6}} /></div><div style={{fontSize:11,color:'var(--text-muted)',textAlign:'center'}}>{m}</div></div>); })()}</div></div></div>
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
                  <div style={{ display: 'flex', gap: 0, alignItems: 'center', marginBottom: 16 }}>
                    {[{ key: 'submitted', label: 'Submitted' }, { key: 'pending_manager', label: 'Pending Mgr' }, { key: 'approved', label: 'Approved' }, { key: 'in_progress', label: 'In Progress' }, { key: 'resolved', label: 'Resolved' }].map((step, i, arr) => {
                      const currentStatus = normalizeWorkflowStatus(selected);
                      const done = statusRank(currentStatus) >= statusRank(step.key);
                      return (
                        <div key={step.key} style={{ flex: 1, textAlign: 'center', position: 'relative' }}>
                          {i > 0 && <div style={{ position: 'absolute', top: 14, left: 0, right: '50%', height: 2, background: done ? 'var(--green)' : 'var(--border)' }} />}
                          {i < arr.length - 1 && <div style={{ position: 'absolute', top: 14, left: '50%', right: 0, height: 2, background: statusRank(currentStatus) > statusRank(step.key) ? 'var(--green)' : 'var(--border)' }} />}
                          <div style={{ width: 26, height: 26, borderRadius: '50%', background: done ? 'var(--green)' : 'var(--border)', color: done ? '#fff' : 'var(--text-muted)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 11, margin: '0 auto 4px', position: 'relative', zIndex: 1 }}>{done ? '✓' : i + 1}</div>
                          <div style={{ fontSize: 10, fontWeight: 600, color: done ? 'var(--green)' : 'var(--text-muted)' }}>{step.label}</div>
                        </div>
                      );
                    })}
                  </div>
                  <div style={{ display: 'grid', gridTemplateColumns: 'repeat(5, minmax(0, 1fr))', gap: 4, background: 'var(--bg-elevated)', borderRadius: 10, padding: 6 }}>
                    {['submitted', 'pending_manager', 'approved', 'in_progress', 'resolved'].map((s) => {
                      const isApprovalStep = s === 'approved' || s === 'pending_manager';
                      if (isApprovalStep && user?.role !== 'superuser') return null;
                      const active = normalizeWorkflowStatus(selected) === s;
                      return (
                        <button key={s} onClick={() => updateWorkflowStatus(selected, s)} style={{ padding: '6px 4px', borderRadius: 8, border: 'none', fontSize: 10, fontWeight: 500, fontFamily: 'inherit', cursor: 'pointer', background: active ? (STATUS_META[s]?.bg || 'var(--bg-surface)') : 'none', color: active ? (STATUS_META[s]?.color || 'var(--text-primary)') : 'var(--text-muted)' }}>
                          {STATUS_META[s]?.label || s}
                        </button>
                      );
                    })}
                  </div>
                </div>

                <div className="drawer-section">
                  <div className="drawer-section-title">Details</div>
                  <div className="kv-grid">
                    <div className="kv"><div className="kv-label">Status</div><div className="kv-value" style={{ color: (STATUS_META[normalizeWorkflowStatus(selected)] || STATUS_META.open).color }}>{(STATUS_META[normalizeWorkflowStatus(selected)] || STATUS_META.open).label}</div></div>
                    <div className="kv"><div className="kv-label">Unit</div><div className="kv-value accent">{selected.unit_ref}</div></div>
                    <div className="kv"><div className="kv-label">Reported</div><div className="kv-value" style={{ fontSize: '12.5px' }}>{formatDisplayDate(selected.reported_date)}</div></div>
                    <div className="kv"><div className="kv-label">Category</div><div className="kv-value" style={{ fontSize: 13 }}>{selected.category}</div></div>
                    <div className="kv"><div className="kv-label">Materials</div><div className="kv-value">{selected.material_cost ? formatTzs(selected.material_cost) : '—'}</div></div>
                    <div className="kv"><div className="kv-label">Labour</div><div className="kv-value">{selected.labour ? formatTzs(selected.labour) : '—'}</div></div>
                  </div>
                </div>

                <div className="drawer-section">
                  <div className="drawer-section-title">Materials & Costs</div>
                  {(() => {
                    const mats = Array.isArray(selected.materials) && selected.materials.length ? selected.materials : null;
                    const matCost = mats ? mats.reduce((s, m) => s + Number(m.qty || 0) * Number(m.unit_price || 0), 0) : 0;
                    return (
                      <>
                        {mats ? (
                          <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 12.5, marginBottom: 8 }}>
                            <thead><tr style={{ color: 'var(--text-muted)', fontSize: 11 }}><th style={{ textAlign: 'left', paddingBottom: 4 }}>Material</th><th style={{ textAlign: 'center', paddingBottom: 4 }}>Qty</th><th style={{ textAlign: 'right', paddingBottom: 4 }}>Price/Unit</th><th style={{ textAlign: 'right', paddingBottom: 4 }}>Total</th></tr></thead>
                            <tbody>{mats.map((m, i) => <tr key={i} style={{ borderTop: '1px solid var(--border-subtle)' }}><td style={{ padding: '4px 0' }}>{m.name} {m.unit ? <span style={{ color: 'var(--text-muted)' }}>({m.unit})</span> : ''}</td><td style={{ textAlign: 'center' }}>{m.qty}</td><td style={{ textAlign: 'right' }}>{formatTzs(Number(m.unit_price || 0))}</td><td style={{ textAlign: 'right', fontWeight: 600 }}>{formatTzs(Number(m.qty || 0) * Number(m.unit_price || 0))}</td></tr>)}</tbody>
                          </table>
                        ) : (
                          <div style={{ fontSize: 13, color: 'var(--text-muted)', padding: '8px 0' }}>No materials listed</div>
                        )}
                        <div style={{ background: 'var(--bg-elevated)', borderRadius: 9, padding: '10px 12px', marginTop: 8, fontSize: 13 }}>
                          <div style={{ display: 'flex', justifyContent: 'space-between', padding: '3px 0' }}><span style={{ color: 'var(--text-muted)' }}>Materials Total</span><strong>{matCost ? formatTzs(matCost) : '—'}</strong></div>
                          <div style={{ display: 'flex', justifyContent: 'space-between', padding: '3px 0' }}><span style={{ color: 'var(--text-muted)' }}>Labour</span><strong>{selected.labour ? formatTzs(selected.labour) : '—'}</strong></div>
                          <div style={{ display: 'flex', justifyContent: 'space-between', paddingTop: 8, marginTop: 6, borderTop: '1px solid var(--border)' }}><span style={{ fontWeight: 700 }}>Total Cost</span><strong style={{ color: 'var(--accent)' }}>{selected.total_cost ? formatTzs(selected.total_cost) : '—'}</strong></div>
                        </div>
                      </>
                    );
                  })()}
                </div>

                <div className="drawer-section">
                  <div className="drawer-section-title">Image Evidence</div>
                  {(() => {
                    const docs = selected.documents?.filter(d => d.document_type === 'maintenance_image') || [];
                    return docs.length > 0 ? (
                      <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                        {docs.map((doc) => (
                          <a key={doc.id} href={`/storage/${doc.file_path}`} target="_blank" rel="noreferrer">
                            <img src={`/storage/${doc.file_path}`} alt={doc.name} style={{ width: 80, height: 80, objectFit: 'cover', borderRadius: 8, border: '1px solid var(--border)', cursor: 'pointer' }} />
                          </a>
                        ))}
                      </div>
                    ) : (
                      <div style={{ fontSize: 13, color: 'var(--text-muted)' }}>No images attached</div>
                    );
                  })()}
                </div>

                <div className="drawer-section">
                  <div className="drawer-section-title">Approval Log</div>
                  <div style={{display:'flex',flexDirection:'column',gap:8}}>
                    <div style={{background:'var(--bg-elevated)',borderRadius:8,padding:'10px 12px'}}>
                      <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',marginBottom:4}}>
                        <span style={{fontSize:13,fontWeight:600}}>{selected.assignee || 'Staff'}</span>
                        <span style={{fontSize:'11.5px',color:'var(--text-muted)'}}>{formatDisplayDate(selected.reported_date)}</span>
                      </div>
                      <div style={{fontSize:12,color:'var(--text-muted)',textTransform:'capitalize'}}>{normalizeWorkflowStatus(selected).replaceAll('_', ' ')}</div>
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
                <button className="btn-primary" style={{ flex: 1, justifyContent: 'center' }} onClick={() => updateWorkflowStatus(selected, normalizeWorkflowStatus(selected) === 'resolved' ? 'in_progress' : 'resolved')}>
                  {normalizeWorkflowStatus(selected) === 'resolved' ? '↺ Reopen' : '✓ Mark Resolved'}
                </button>
                <button className="btn-danger" onClick={() => { router.delete(`/maintenance/${selected.id}`, {}, { onSuccess: () => { setSelected(null); setSubmitMessage('Ticket deleted.'); } }); }}>
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
                </button>
              </div>
            </>
          )}
        </div>
      </div>

      <div className={`modal-overlay ${showModal ? 'open' : ''}`} onClick={(e) => e.target === e.currentTarget && setShowModal(false)}>
        <div className="modal" style={{ width: 'min(660px, calc(100vw - 24px))', height: 'min(820px, 92dvh)', maxHeight: 'min(92vh, calc(100dvh - 20px))', display: 'flex', flexDirection: 'column' }}>
          <div className="modal-header" style={{ flexShrink: 0 }}><div className="modal-title">New Maintenance Request</div><button className="modal-close" onClick={() => setShowModal(false)}>✕</button></div>
          <form onSubmit={submitRequest} style={{ display: 'flex', flexDirection: 'column', minHeight: 0, flex: 1 }}>
            <div className="modal-body" style={{ flex: 1, overflowY: 'auto' }}>
              {submitError && (
                <div style={{ background: 'var(--red-dim, #fef2f2)', color: 'var(--red)', border: '1px solid var(--red)', borderRadius: 8, padding: '8px 12px', fontSize: 13, marginBottom: 12 }}>{submitError}</div>
              )}
              <div style={{ fontSize: '10.5px', fontWeight: 700, letterSpacing: '.6px', textTransform: 'uppercase', color: 'var(--text-muted)', marginBottom: 12 }}>Request Details</div>
              <div className="form-row">
                <div className="form-group"><label className="form-label">Unit *</label><select className="form-input form-select" value={data.unit_ref} onChange={(e) => setData('unit_ref', e.target.value)} required><option value="">Select unit…</option><option value="Common">Common Area</option>{units.map((u) => <option key={u.id || u.unit_number} value={u.unit_number}>{u.unit_number}</option>)}</select></div>
                <div className="form-group"><label className="form-label">Category *</label><select className="form-input form-select" value={data.category} onChange={(e) => setData('category', e.target.value)} required><option value="">Select…</option>{CATEGORY_OPTIONS.map((c) => <option key={c} value={c}>{c}</option>)}</select></div>
              </div>
              <div className="form-row">
                <div className="form-group"><label className="form-label">Priority *</label><select className="form-input form-select" value={data.priority} onChange={(e) => setData('priority', e.target.value)}><option value="low">Low — can wait</option><option value="med">Medium — within a week</option><option value="high">High — within 48 hours</option><option value="critical">Critical — immediate</option></select></div>
                <div className="form-group"><label className="form-label">Assign To</label><input className="form-input" type="text" list="assignee-hints" value={data.assignee} onChange={(e) => setData('assignee', e.target.value)} placeholder="Type name or select…" /><datalist id="assignee-hints">{['Peter Ng.', 'JK Electric', 'Cool Air Ltd', 'In-house', 'SecurePro'].map((a) => <option key={a} value={a} />)}</datalist></div>
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
                      <div key={`img-${i}`} style={{ position: 'relative' }}>
                        <img src={img.preview} alt="evidence" style={{ width: 72, height: 72, objectFit: 'cover', borderRadius: 6, border: '1px solid var(--border)' }} />
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
                  <div className="nl-summary-row"><span>Materials (market rate)</span><strong>{formatTzs(materialTotal)}</strong></div>
                  <div className="nl-summary-row"><span>Labour</span><strong>{formatTzs(labourTotal)}</strong></div>
                  <div className="nl-summary-row" style={{ borderTop: '1px solid var(--border)', paddingTop: 8, marginTop: 4 }}><span style={{ fontWeight: 600 }}>Estimated Total</span><strong style={{ fontSize: 15 }}>{formatTzs(estimateTotal)}</strong></div>
                </div>
              )}
            </div>
            <div className="modal-footer" style={{ flexShrink: 0 }}>
              <button type="button" className="btn-ghost" onClick={() => setShowModal(false)}>Cancel</button>

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
              <div className="form-group"><label className="form-label">Unit / Area</label><select className="form-input form-select" value={scheduleForm.unit_ref} onChange={(e) => setScheduleForm((s) => ({ ...s, unit_ref: e.target.value }))}><option value="">Common / All</option>{units.map((u) => <option key={u.id} value={u.unit_number}>{u.unit_number}</option>)}</select></div>
            </div>
            <div className="form-row">
              <div className="form-group"><label className="form-label">Category</label><select className="form-input form-select" value={scheduleForm.category} onChange={(e) => setScheduleForm((s) => ({ ...s, category: e.target.value }))}>{CATEGORY_OPTIONS.map((c) => <option key={c}>{c}</option>)}</select></div>
              <div className="form-group"><label className="form-label">Frequency</label><select className="form-input form-select" value={scheduleForm.frequency} onChange={(e) => setScheduleForm((s) => ({ ...s, frequency: e.target.value }))}><option value="weekly">Weekly</option><option value="monthly">Monthly</option><option value="quarterly">Quarterly</option><option value="biannual">Every 6 months</option><option value="annual">Annual</option></select></div>
            </div>
            <div className="form-row">
              <div className="form-group"><label className="form-label">Next Due Date *</label><input className="form-input" type="date" value={scheduleForm.next_due} onChange={(e) => setScheduleForm((s) => ({ ...s, next_due: e.target.value }))} /></div>
              <div className="form-group"><label className="form-label">Assign To</label><input className="form-input" type="text" placeholder="Unassigned" value={scheduleForm.assignee} onChange={(e) => setScheduleForm((s) => ({ ...s, assignee: e.target.value }))} /></div>
            </div>
            <div className="form-group"><label className="form-label">Notes</label><textarea className="form-input" rows={2} style={{ resize: 'vertical' }} value={scheduleForm.notes} onChange={(e) => setScheduleForm((s) => ({ ...s, notes: e.target.value }))} /></div>
          </div>
          <div className="modal-footer">
            <button className="btn-ghost" onClick={() => setShowScheduleModal(false)}>Cancel</button>
            <button className="btn-primary" disabled={scheduleSubmitting} onClick={() => {
              if (!scheduleForm.title.trim() || !scheduleForm.next_due) return;
              setScheduleSubmitting(true);
              router.post('/scheduled-maintenance', {
                title: scheduleForm.title.trim(),
                unit_ref: scheduleForm.unit_ref || null,
                category: scheduleForm.category,
                frequency: scheduleForm.frequency,
                next_due: scheduleForm.next_due,
                assignee: scheduleForm.assignee.trim() || null,
                notes: scheduleForm.notes.trim() || null,
              }, {
                onSuccess: () => {
                  setScheduleForm({ title: '', unit_ref: '', category: 'General', frequency: 'monthly', next_due: '', assignee: '', notes: '' });
                  setShowScheduleModal(false);
                  setSubmitMessage('Task scheduled successfully.');
                  setScheduleSubmitting(false);
                },
                onError: () => setScheduleSubmitting(false),
              });
            }}>
              {scheduleSubmitting ? 'Scheduling…' : 'Schedule Task'}
            </button>
          </div>
        </div>
      </div>
    </AppLayout>
  );
}
