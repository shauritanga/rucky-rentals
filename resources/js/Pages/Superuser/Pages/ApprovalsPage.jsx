import { useState } from 'react';
import { router } from '@inertiajs/react';

function formatDate(ts) {
  if (!ts) return '-';
  return new Date(ts).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
}

function RejectModal({ open, onClose, onConfirm, label }) {
  const [reason, setReason] = useState('');
  if (!open) return null;
  return (
    <div className="modal-overlay open" onClick={(e) => e.target === e.currentTarget && onClose()}>
      <div className="modal" style={{ width: 440 }}>
        <div className="modal-header">
          <div className="modal-title">Reject {label}</div>
          <button className="modal-close" onClick={onClose}>✕</button>
        </div>
        <div className="modal-body">
          <div className="form-group">
            <label className="form-label">Reason (optional)</label>
            <textarea
              className="form-input"
              rows={3}
              value={reason}
              onChange={(e) => setReason(e.target.value)}
              placeholder="Explain reason for rejection..."
              style={{ resize: 'vertical' }}
            />
          </div>
        </div>
        <div className="modal-footer">
          <button className="btn-ghost" onClick={onClose}>Cancel</button>
          <button
            className="btn-primary"
            style={{ background: 'var(--red)', borderColor: 'var(--red)' }}
            onClick={() => { onConfirm(reason); onClose(); }}
          >
            Reject
          </button>
        </div>
      </div>
    </div>
  );
}

export default function ApprovalsPage({ pendingLeases = [], pendingMaintenance = [] }) {
  const [rejectModal, setRejectModal] = useState(null); // { type: 'lease'|'maintenance', id }

  const approveLease = (id) => {
    router.post(`/superuser/leases/${id}/approve`, {}, { preserveScroll: true });
  };

  const rejectLease = (id, reason) => {
    router.post(`/superuser/leases/${id}/reject`, { reason }, { preserveScroll: true });
  };

  const approveMaintenance = (id) => {
    router.post(`/superuser/maintenance/${id}/approve`, {}, { preserveScroll: true });
  };

  const rejectMaintenance = (id, reason) => {
    router.post(`/superuser/maintenance/${id}/reject`, { reason }, { preserveScroll: true });
  };

  const totalPending = pendingLeases.length + pendingMaintenance.length;

  return (
    <>
      <RejectModal
        open={!!rejectModal}
        label={rejectModal?.type === 'lease' ? 'Lease' : 'Maintenance Ticket'}
        onClose={() => setRejectModal(null)}
        onConfirm={(reason) => {
          if (rejectModal.type === 'lease') rejectLease(rejectModal.id, reason);
          else rejectMaintenance(rejectModal.id, reason);
        }}
      />

      <div className="page-header">
        <div>
          <div className="page-title">Pending Approvals</div>
          <div className="page-sub">
            {totalPending === 0 ? 'No pending requests' : `${totalPending} request${totalPending !== 1 ? 's' : ''} awaiting your approval`}
          </div>
        </div>
      </div>

      {/* Pending Leases */}
      <div className="card" style={{ marginBottom: 20 }}>
        <div className="card-header">
          <div>
            <div className="card-title">Pending Leases</div>
            <div className="card-sub">{pendingLeases.length} lease{pendingLeases.length !== 1 ? 's' : ''} pending</div>
          </div>
        </div>
        {pendingLeases.length === 0 ? (
          <div style={{ padding: '20px 20px', color: 'var(--text-muted)', fontSize: 13 }}>No leases pending approval.</div>
        ) : (
          <table className="data-table">
            <thead>
              <tr>
                <th>Tenant</th>
                <th>Unit</th>
                <th>Property</th>
                <th>Status</th>
                <th>Submitted</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {pendingLeases.map((lease) => (
                <tr key={lease.id}>
                  <td>
                    <div style={{ fontWeight: 600 }}>{lease.tenant?.name ?? '-'}</div>
                    <div style={{ fontSize: 11.5, color: 'var(--text-muted)' }}>{lease.lease_number ?? `Lease #${lease.id}`}</div>
                  </td>
                  <td>{lease.unit?.unit_number ?? '-'}</td>
                  <td>{lease.unit?.property?.name ?? '-'}</td>
                  <td>
                    <span className="badge" style={{ background: 'rgba(245,158,11,0.12)', color: 'rgb(245,158,11)', border: '1px solid rgba(245,158,11,0.3)' }}>
                      {lease.status === 'pending_accountant' ? 'Pending Review' : 'Pending PM'}
                    </span>
                  </td>
                  <td style={{ color: 'var(--text-muted)', fontSize: 12 }}>{formatDate(lease.created_at)}</td>
                  <td>
                    <div style={{ display: 'flex', gap: 8 }}>
                      <button
                        className="btn-primary"
                        style={{ padding: '4px 12px', fontSize: 12 }}
                        onClick={() => approveLease(lease.id)}
                      >
                        Approve
                      </button>
                      <button
                        className="btn-ghost"
                        style={{ padding: '4px 12px', fontSize: 12, color: 'var(--red)', borderColor: 'var(--red)' }}
                        onClick={() => setRejectModal({ type: 'lease', id: lease.id })}
                      >
                        Reject
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      {/* Pending Maintenance */}
      <div className="card">
        <div className="card-header">
          <div>
            <div className="card-title">Pending Maintenance Tickets</div>
            <div className="card-sub">{pendingMaintenance.length} ticket{pendingMaintenance.length !== 1 ? 's' : ''} pending</div>
          </div>
        </div>
        {pendingMaintenance.length === 0 ? (
          <div style={{ padding: '20px 20px', color: 'var(--text-muted)', fontSize: 13 }}>No maintenance tickets pending approval.</div>
        ) : (
          <table className="data-table">
            <thead>
              <tr>
                <th>Title</th>
                <th>Unit</th>
                <th>Property</th>
                <th>Priority</th>
                <th>Submitted By</th>
                <th>Date</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {pendingMaintenance.map((ticket) => (
                <tr key={ticket.id}>
                  <td>
                    <div style={{ fontWeight: 600 }}>{ticket.title}</div>
                    <div style={{ fontSize: 11.5, color: 'var(--text-muted)' }}>{ticket.ticket_number ?? `TK #${ticket.id}`}</div>
                  </td>
                  <td>{ticket.unit?.unit_number ?? ticket.unit_ref ?? '-'}</td>
                  <td>{ticket.unit?.property?.name ?? '-'}</td>
                  <td>
                    <span className={`badge ${ticket.priority === 'high' ? 'overdue' : ticket.priority === 'med' ? 'trial' : 'active'}`}>
                      {ticket.priority ?? '-'}
                    </span>
                  </td>
                  <td style={{ fontSize: 12 }}>{ticket.reported_by ?? '-'}</td>
                  <td style={{ color: 'var(--text-muted)', fontSize: 12 }}>{formatDate(ticket.created_at)}</td>
                  <td>
                    <div style={{ display: 'flex', gap: 8 }}>
                      <button
                        className="btn-primary"
                        style={{ padding: '4px 12px', fontSize: 12 }}
                        onClick={() => approveMaintenance(ticket.id)}
                      >
                        Approve
                      </button>
                      <button
                        className="btn-ghost"
                        style={{ padding: '4px 12px', fontSize: 12, color: 'var(--red)', borderColor: 'var(--red)' }}
                        onClick={() => setRejectModal({ type: 'maintenance', id: ticket.id })}
                      >
                        Reject
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </>
  );
}
