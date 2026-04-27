import { useState, useEffect } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm, router } from '@inertiajs/react';
import { formatDisplayDate } from '@/utils/dateFormat';

const fmt = (n) => Number(n).toLocaleString();
const EXT_ICON = { pdf:'📕', word:'📘', img:'🖼️', other:'📁' };
const TAG_LABELS = {
  lease_agreement: 'Lease Agreement',
  invoice: 'Invoice',
  deposit: 'Deposit',
  national_id: 'National ID',
  handover: 'Handover',
};

const resolveDocType = (doc) => {
  if (doc?.document_type) return doc.document_type;
  if (doc?.tag === 'lease') return 'lease_agreement';
  if (doc?.tag === 'id') return 'national_id';
  if (doc?.tag === 'notice') return 'handover';
  if (doc?.tag === 'other') return 'invoice';
  return 'invoice';
};

export default function DocumentsIndex({ documents, units, tenants = [] }) {
  const [filter, setFilter] = useState('all');
  const [search, setSearch] = useState('');
  const [view, setView] = useState('grid');
  const [selected, setSelected] = useState(null);
  const [showUpload, setShowUpload] = useState(false);
  const [page, setPage] = useState(1);
  const [dragOver, setDragOver] = useState(false);
  const [submitMessage, setSubmitMessage] = useState({ type: '', text: '' });
  const [submitError,   setSubmitError]   = useState('');

  const { data, setData, post, processing, reset } = useForm({ file: null, document_type:'lease_agreement', unit_ref:'', tenant_id:'', description:'' });

  const filtered = documents.filter(d => {
    const docType = resolveDocType(d);
    const matchFilter = filter === 'all' || docType === filter;
    const q = search.toLowerCase();
    const matchSearch = !q || d.name.toLowerCase().includes(q) || (d.unit_ref||'').toLowerCase().includes(q);
    return matchFilter && matchSearch;
  });

  const PER_PAGE = 10;
  const totalPages = Math.max(1, Math.ceil(filtered.length / PER_PAGE));
  const safePage = Math.min(page, totalPages);
  const paginated = filtered.slice((safePage - 1) * PER_PAGE, safePage * PER_PAGE);

  function changeFilter(f) { setFilter(f); setPage(1); }
  function changeSearch(q) { setSearch(q); setPage(1); }

  const counts = {
    all: documents.length,
    lease_agreement: documents.filter(d=>resolveDocType(d)==='lease_agreement').length,
    invoice: documents.filter(d=>resolveDocType(d)==='invoice').length,
    deposit: documents.filter(d=>resolveDocType(d)==='deposit').length,
    national_id: documents.filter(d=>resolveDocType(d)==='national_id').length,
    handover: documents.filter(d=>resolveDocType(d)==='handover').length,
  };

  const submit = (e) => {
    e.preventDefault();
    setSubmitError('');
    post('/documents', {
      forceFormData: true,
      onSuccess: () => {
        reset();
        setData('document_type', 'lease_agreement');
        setData('tenant_id', '');
        setData('unit_ref', '');
        setShowUpload(false);
        setSubmitMessage({ type: 'success', text: 'Document uploaded successfully.' });
      },
      onError: (errors) => {
        const first = Object.values(errors || {}).flat().find(v => typeof v === 'string' && v.trim());
        setSubmitError(first || 'Upload failed. Please check the file and try again.');
      },
    });
  };

  useEffect(() => {
    if (!submitMessage.type) return;
    const t = setTimeout(() => setSubmitMessage({ type: '', text: '' }), 4000);
    return () => clearTimeout(t);
  }, [submitMessage]);

  return (
    <AppLayout title="Documents" subtitle={`${documents.length} files`}>
      <Head title="Documents" />

      {submitMessage.type === 'success' && (
        <div style={{ marginBottom: 14, background: 'var(--green-dim)', border: '1px solid var(--green)', borderRadius: 8, padding: '10px 14px', fontSize: 13, color: 'var(--green)', display: 'flex', alignItems: 'center', gap: 8 }}>
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><polyline points="20 6 9 17 4 12"/></svg>
          {submitMessage.text}
        </div>
      )}

      <div className="tn-stats-row">
        <div className="tn-stat"><div className="tn-stat-value">{counts.all}</div><div className="tn-stat-label">Total Files</div></div>
        <div className="tn-stat-divider"></div>
        <div className="tn-stat"><div className="tn-stat-value" style={{color:'var(--green)'}}>{counts.lease_agreement}</div><div className="tn-stat-label">Lease Agreements</div></div>
        <div className="tn-stat-divider"></div>
        <div className="tn-stat"><div className="tn-stat-value" style={{color:'var(--accent)'}}>{counts.invoice}</div><div className="tn-stat-label">Invoices</div></div>
        <div className="tn-stat-divider"></div>
        <div className="tn-stat"><div className="tn-stat-value" style={{color:'var(--amber)'}}>{counts.deposit}</div><div className="tn-stat-label">Deposits</div></div>
        <div className="tn-stat-divider"></div>
        <div className="tn-stat"><div className="tn-stat-value" style={{color:'var(--accent)'}}>{counts.national_id}</div><div className="tn-stat-label">National IDs</div></div>
        <div className="tn-stat-divider"></div>
        <div className="tn-stat"><div className="tn-stat-value" style={{color:'var(--text-secondary)'}}>{counts.handover}</div><div className="tn-stat-label">Handovers</div></div>
      </div>

      <div className="toolbar">
        <div className="filters">
          {[['all','All'],['lease_agreement','Lease Agreement'],['invoice','Invoice'],['deposit','Deposit'],['national_id','National ID'],['handover','Handover']].map(([f,l])=>(
            <button key={f} className={`filter-pill ${filter===f?'active':''}`} onClick={()=>changeFilter(f)}>{l} <span className="pill-count">{counts[f]||0}</span></button>
          ))}
        </div>
        <div style={{display:'flex',gap:8,alignItems:'center'}}>
          <div className="search-box" style={{width:190}}>
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            <input type="text" placeholder="Search documents…" value={search} onChange={e=>changeSearch(e.target.value)} />
          </div>
          <div className="view-toggle">
            <button className={`vt-btn ${view==='grid'?'active':''}`} onClick={()=>setView('grid')} title="Grid"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg></button>
            <button className={`vt-btn ${view==='list'?'active':''}`} onClick={()=>setView('list')} title="List"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg></button>
          </div>
          <button className="btn-primary" onClick={()=>setShowUpload(true)}>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0018 9h-1.26A8 8 0 103 16.3"/></svg>
            Upload
          </button>
        </div>
      </div>

      {view === 'grid'
        ? <div className="docs-grid">
            {paginated.map(d => (
              <div className="doc-card" key={d.id} onClick={()=>setSelected(d)}>
                <div style={{display:'flex',alignItems:'flex-start',gap:12}}>
                  <div className={`doc-icon ${d.file_type}`}>{EXT_ICON[d.file_type]||'📁'}</div>
                  <div style={{flex:1,minWidth:0,paddingRight:20}}>
                    <div style={{fontSize:13,fontWeight:600,lineHeight:1.35,wordBreak:'break-word'}}>{d.name}</div>
                    <div style={{fontSize:'11.5px',color:'var(--text-muted)',marginTop:3}}>{d.unit_ref&&d.unit_ref!=='—'?`Unit ${d.unit_ref} · `:''}{d.file_size}</div>
                  </div>
                </div>
                <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',paddingTop:10,borderTop:'1px solid var(--border-subtle)'}}>
                  <span className={`doc-tag ${resolveDocType(d)}`}>{TAG_LABELS[resolveDocType(d)] || resolveDocType(d)}</span>
                  <span style={{fontSize:'11.5px',color:'var(--text-muted)'}}>{formatDisplayDate(d.created_at)}</span>
                </div>
              </div>
            ))}
          </div>
        : <div className="card">
            <table className="data-table">
              <thead><tr><th style={{width:36}}></th><th>Name</th><th>Type</th><th>Unit</th><th>Size</th><th>Date Added</th><th></th></tr></thead>
              <tbody>
                {paginated.map(d => (
                  <tr key={d.id} onClick={()=>setSelected(d)}>
                    <td style={{paddingLeft:20}}><div className={`doc-icon ${d.file_type}`} style={{width:30,height:30,borderRadius:7,fontSize:14}}>{EXT_ICON[d.file_type]||'📁'}</div></td>
                    <td><div style={{fontWeight:600,fontSize:'13.5px'}}>{d.name}</div></td>
                    <td><span className={`doc-tag ${resolveDocType(d)}`}>{TAG_LABELS[resolveDocType(d)] || resolveDocType(d)}</span></td>
                    <td style={{color:'var(--text-secondary)',fontWeight:500}}>{d.unit_ref&&d.unit_ref!=='—'?d.unit_ref:'—'}</td>
                    <td style={{color:'var(--text-muted)',fontSize:'12.5px'}}>{d.file_size}</td>
                    <td style={{color:'var(--text-muted)',fontSize:'12.5px'}}>{formatDisplayDate(d.created_at)}</td>
                    <td><button className="action-dots" onClick={e=>{e.stopPropagation();router.delete(`/documents/${d.id}`);}}>···</button></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
      }

      {/* Pagination */}
      {totalPages > 1 && (
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginTop: 16, padding: '0 2px' }}>
          <div style={{ fontSize: 12.5, color: 'var(--text-muted)' }}>
            Showing {(safePage - 1) * PER_PAGE + 1}–{Math.min(safePage * PER_PAGE, filtered.length)} of {filtered.length} documents
          </div>
          <div style={{ display: 'flex', gap: 4, alignItems: 'center' }}>
            <button
              className="btn-ghost"
              style={{ padding: '4px 10px', fontSize: 12.5 }}
              disabled={safePage === 1}
              onClick={() => setPage(safePage - 1)}
            >
              ← Prev
            </button>
            {Array.from({ length: totalPages }, (_, i) => i + 1).map(p => (
              <button
                key={p}
                onClick={() => setPage(p)}
                style={{
                  padding: '4px 10px',
                  fontSize: 12.5,
                  border: 'none',
                  borderRadius: 6,
                  cursor: 'pointer',
                  background: p === safePage ? 'var(--accent)' : 'transparent',
                  color: p === safePage ? '#fff' : 'var(--text-secondary)',
                  fontWeight: p === safePage ? 600 : 400,
                }}
              >
                {p}
              </button>
            ))}
            <button
              className="btn-ghost"
              style={{ padding: '4px 10px', fontSize: 12.5 }}
              disabled={safePage === totalPages}
              onClick={() => setPage(safePage + 1)}
            >
              Next →
            </button>
          </div>
        </div>
      )}

      {/* Document Drawer */}
      <div className={`drawer-overlay ${selected?'open':''}`} onClick={e=>e.target===e.currentTarget&&setSelected(null)}>
        <div className="drawer">
          {selected && <>
            <div className="drawer-header">
              <div style={{display:'flex',alignItems:'center',gap:14}}>
                <div className={`doc-icon ${selected.file_type}`} style={{width:48,height:48,borderRadius:12,fontSize:22}}>{EXT_ICON[selected.file_type]||'📁'}</div>
                <div><div style={{fontSize:15,fontWeight:600,letterSpacing:'-.2px',lineHeight:1.35}}>{selected.name}</div><div style={{fontSize:12,color:'var(--text-muted)',marginTop:4}}>{selected.file_type?.toUpperCase()} · {selected.file_size}</div></div>
              </div>
              <button className="drawer-close" onClick={()=>setSelected(null)}>✕</button>
            </div>
            <div className="drawer-body">
              <div className="drawer-section">
                <div className="drawer-section-title">Preview</div>
                <div style={{background:'var(--bg-elevated)',border:'1px solid var(--border)',borderRadius:10,padding:'32px 20px',textAlign:'center',marginBottom:10}}>
                  <div style={{fontSize:52,marginBottom:10}}>{EXT_ICON[selected.file_type]||'📁'}</div>
                  <div style={{fontSize:12,color:'var(--text-muted)'}}>No preview available — open file to view</div>
                </div>
              </div>
              <div className="drawer-section">
                <div className="drawer-section-title">File Details</div>
                <div className="kv-grid">
                  <div className="kv"><div className="kv-label">File Type</div><div className="kv-value">{selected.file_type?.toUpperCase()}</div></div>
                  <div className="kv"><div className="kv-label">File Size</div><div className="kv-value">{selected.file_size}</div></div>
                  <div className="kv"><div className="kv-label">Category</div><div className="kv-value accent">{TAG_LABELS[resolveDocType(selected)]||resolveDocType(selected)}</div></div>
                  <div className="kv"><div className="kv-label">Date Added</div><div className="kv-value">{formatDisplayDate(selected.created_at)}</div></div>
                  <div className="kv"><div className="kv-label">Uploaded By</div><div className="kv-value" style={{fontSize:12}}>{selected.uploaded_by}</div></div>
                  {selected.unit_ref && <div className="kv"><div className="kv-label">Linked Unit</div><div className="kv-value accent">{selected.unit_ref}</div></div>}
                </div>
              </div>
            </div>
            <div className="drawer-footer">
              <a href={selected.invoice_id ? `/invoices/${selected.invoice_id}/pdf` : `/storage/${selected.file_path}`} download target="_blank" rel="noreferrer" className="btn-primary" style={{flex:1,justifyContent:'center',display:'flex',alignItems:'center',gap:6,textDecoration:'none'}}>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Download
              </a>
              <button className="btn-secondary" onClick={()=>setSelected(null)}>Rename</button>
              <button className="btn-danger" onClick={()=>{router.delete(`/documents/${selected.id}`);setSelected(null);}}>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
              </button>
            </div>
          </>}
        </div>
      </div>

      {/* Upload Modal */}
      <div className={`modal-overlay ${showUpload?'open':''}`} onClick={e=>e.target===e.currentTarget&&setShowUpload(false)}>
        <div className="modal" style={{width:500}}>
          <div className="modal-header"><div className="modal-title">Upload Document</div><button className="modal-close" onClick={()=>setShowUpload(false)}>✕</button></div>
          <form onSubmit={submit} encType="multipart/form-data">
            <div className="modal-body">
              {submitError && (
                <div style={{ marginBottom: 14, background: 'var(--red-dim)', border: '1px solid var(--red)', borderRadius: 8, padding: '10px 14px', fontSize: 13, color: 'var(--red)' }}>
                  {submitError}
                </div>
              )}
              <div
                style={{border:`2px dashed ${dragOver?'var(--accent)':'var(--border)'}`,borderRadius:10,padding:'28px 20px',textAlign:'center',cursor:'pointer',marginBottom:14,transition:'border-color .15s,background .15s',background:dragOver?'var(--accent-dim)':'transparent'}}
                onClick={()=>document.getElementById('fileInput').click()}
                onDragOver={e=>{e.preventDefault();setDragOver(true);}}
                onDragLeave={()=>setDragOver(false)}
                onDrop={e=>{e.preventDefault();setDragOver(false);const f=e.dataTransfer.files[0];if(f)setData('file',f);}}
              >
                <div style={{fontSize:28,marginBottom:8}}>📂</div>
                <div style={{fontSize:'13.5px',fontWeight:500,marginBottom:4}}>Click to browse or drag & drop</div>
                <div style={{fontSize:12,color:'var(--text-muted)'}}>PDF, DOCX, JPG, PNG — max 20 MB</div>
              </div>
              <input id="fileInput" type="file" style={{display:'none'}} accept=".pdf,.docx,.doc,.jpg,.jpeg,.png" onChange={e=>setData('file',e.target.files[0])} />
              {data.file && <div style={{background:'var(--bg-elevated)',borderRadius:8,padding:'9px 12px',marginBottom:10,fontSize:13,display:'flex',alignItems:'center',gap:10}}><span>📄</span><span style={{flex:1,overflow:'hidden',textOverflow:'ellipsis',whiteSpace:'nowrap'}}>{data.file.name}</span></div>}
              <div className="form-row">
                <div className="form-group"><label className="form-label">Document Type</label><select className="form-input form-select" value={data.document_type} onChange={e=>setData('document_type',e.target.value)}><option value="lease_agreement">Lease Agreement</option><option value="invoice">Invoice</option><option value="deposit">Deposit</option><option value="national_id">National ID</option><option value="handover">Handover</option></select></div>
                <div className="form-group"><label className="form-label">Linked Unit</label><select className="form-input form-select" value={data.unit_ref} onChange={e=>setData('unit_ref',e.target.value)}><option value="">— None —</option>{units.map(u=><option key={u.id} value={u.unit_number}>{u.unit_number}</option>)}</select></div>
              </div>
              <div className="form-row">
                <div className="form-group"><label className="form-label">Tenant</label><select className="form-input form-select" value={data.tenant_id} onChange={e=>setData('tenant_id',e.target.value)}><option value="">— None —</option>{tenants.map(t=><option key={t.id} value={t.id}>{t.name}</option>)}</select></div>
              </div>
            </div>
            <div className="modal-footer">
              <button type="button" className="btn-ghost" onClick={()=>setShowUpload(false)}>Cancel</button>
              <button type="submit" className="btn-primary" disabled={processing}>
                {processing ? <><span className="btn-spinner" />Uploading…</> : 'Upload Files'}
              </button>
            </div>
          </form>
        </div>
      </div>
    </AppLayout>
  );
}
