import { useMemo, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import ReportTabs from './ReportTabs';

const TYPE_LABEL = { string: 'Text', number: 'Number', currency: 'Currency', date: 'Date', enum: 'Choice', boolean: 'Yes/No' };

function columnKey(entity, field) {
  return `${entity}.${field}`;
}

function FilterInput({ type, value = {}, options = [], onChange }) {
  if (type === 'string') {
    return (
      <input
        className="form-input"
        placeholder="Contains…"
        value={value.contains ?? ''}
        onChange={(e) => onChange({ contains: e.target.value })}
      />
    );
  }
  if (type === 'number' || type === 'currency') {
    return (
      <div style={{ display: 'flex', gap: 6 }}>
        <input className="form-input" type="number" placeholder="Min" value={value.min ?? ''} onChange={(e) => onChange({ ...value, min: e.target.value })} />
        <input className="form-input" type="number" placeholder="Max" value={value.max ?? ''} onChange={(e) => onChange({ ...value, max: e.target.value })} />
      </div>
    );
  }
  if (type === 'date') {
    return (
      <div style={{ display: 'flex', gap: 6 }}>
        <input className="form-input" type="date" value={value.from ?? ''} onChange={(e) => onChange({ ...value, from: e.target.value })} />
        <input className="form-input" type="date" value={value.to ?? ''} onChange={(e) => onChange({ ...value, to: e.target.value })} />
      </div>
    );
  }
  if (type === 'enum' || type === 'boolean') {
    const opts = options?.length ? options : ['true', 'false'];
    const selected = value.in ?? [];
    return (
      <select
        className="form-input"
        multiple
        value={selected}
        onChange={(e) => onChange({ in: Array.from(e.target.selectedOptions).map((o) => o.value) })}
      >
        {opts.map((o) => <option key={o} value={o}>{o}</option>)}
      </select>
    );
  }
  return null;
}

export default function ReportBuilder({ registry = {}, templates = [], properties = [] }) {
  const groups = useMemo(() => {
    const g = {};
    Object.entries(registry).forEach(([key, ent]) => {
      (g[ent.group] ||= []).push({ key, ...ent });
    });
    return g;
  }, [registry]);

  const [selectedEntities, setSelectedEntities] = useState([]);
  const [primaryEntity, setPrimaryEntity] = useState('');
  const [selectedColumns, setSelectedColumns] = useState([]);
  const [filters, setFilters] = useState({});
  const [propertyId, setPropertyId] = useState('');

  const [preview, setPreview] = useState(null);
  const [previewError, setPreviewError] = useState('');
  const [loadingPreview, setLoadingPreview] = useState(false);

  const [templateName, setTemplateName] = useState('');
  const [templateDescription, setTemplateDescription] = useState('');
  const [templateShared, setTemplateShared] = useState(false);
  const [activeTemplateId, setActiveTemplateId] = useState('');
  const [savingTemplate, setSavingTemplate] = useState(false);
  const [templateMessage, setTemplateMessage] = useState('');

  function toggleEntity(key) {
    setSelectedEntities((prev) => {
      const removing = prev.includes(key);
      const next = removing ? prev.filter((k) => k !== key) : [...prev, key];
      if (removing) {
        setSelectedColumns((cols) => cols.filter((c) => c.entity !== key));
        setFilters((f) => Object.fromEntries(Object.entries(f).filter(([k]) => !k.startsWith(`${key}.`))));
        if (primaryEntity === key) setPrimaryEntity(next[0] || '');
      } else if (!primaryEntity) {
        setPrimaryEntity(key);
      }
      return next;
    });
  }

  function toggleColumn(entity, field) {
    setSelectedColumns((prev) => {
      const exists = prev.some((c) => c.entity === entity && c.field === field);
      return exists ? prev.filter((c) => !(c.entity === entity && c.field === field)) : [...prev, { entity, field }];
    });
  }

  function moveColumn(index, dir) {
    setSelectedColumns((prev) => {
      const target = index + dir;
      if (target < 0 || target >= prev.length) return prev;
      const next = [...prev];
      [next[index], next[target]] = [next[target], next[index]];
      return next;
    });
  }

  function setFilterValue(entity, field, patch) {
    setFilters((prev) => ({ ...prev, [columnKey(entity, field)]: patch }));
  }

  function buildConfig() {
    return {
      primary_entity: primaryEntity,
      entities: selectedEntities,
      columns: selectedColumns,
      filters,
      sort: null,
    };
  }

  async function runPreview() {
    if (!primaryEntity || selectedColumns.length === 0) {
      setPreviewError('Pick a primary entity and at least one column first.');
      return;
    }
    setLoadingPreview(true);
    setPreviewError('');
    try {
      const { data } = await axios.post(route('reports.builder.preview'), {
        config: buildConfig(),
        property_id: propertyId || undefined,
      });
      setPreview(data);
    } catch (e) {
      setPreview(null);
      setPreviewError(e?.response?.data?.message || 'Could not build this report — check your entity/column selection.');
    } finally {
      setLoadingPreview(false);
    }
  }

  function exportReport(format) {
    const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = route('reports.builder.export');
    form.style.display = 'none';

    const fields = {
      _token: token,
      format,
      title: templateName || 'Custom Report',
      config: JSON.stringify(buildConfig()),
      property_id: propertyId || '',
    };
    Object.entries(fields).forEach(([name, value]) => {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = name;
      input.value = value;
      form.appendChild(input);
    });
    document.body.appendChild(form);
    form.submit();
    form.remove();
  }

  function loadTemplate(id) {
    setActiveTemplateId(id);
    const template = templates.find((t) => String(t.id) === String(id));
    if (!template) return;
    const cfg = template.config || {};
    setSelectedEntities(cfg.entities || []);
    setPrimaryEntity(cfg.primary_entity || '');
    setSelectedColumns(cfg.columns || []);
    setFilters(cfg.filters || {});
    setTemplateName(template.name);
    setTemplateDescription(template.description || '');
    setTemplateShared(!!template.is_shared);
    setPreview(null);
    setPreviewError('');
  }

  async function saveTemplate(asNew) {
    if (!templateName.trim()) {
      setTemplateMessage('Give the template a name first.');
      return;
    }
    setSavingTemplate(true);
    setTemplateMessage('');
    try {
      const payload = {
        name: templateName,
        description: templateDescription,
        is_shared: templateShared,
        config: buildConfig(),
      };
      if (!asNew && activeTemplateId) {
        await axios.put(route('reports.templates.update', activeTemplateId), payload);
        setTemplateMessage('Template updated.');
      } else {
        const { data } = await axios.post(route('reports.templates.store'), payload);
        setActiveTemplateId(data.id);
        setTemplateMessage('Template saved.');
      }
    } catch (e) {
      setTemplateMessage(e?.response?.data?.message || 'Could not save the template.');
    } finally {
      setSavingTemplate(false);
    }
  }

  async function deleteTemplate() {
    if (!activeTemplateId) return;
    if (!confirm('Delete this template?')) return;
    try {
      await axios.delete(route('reports.templates.destroy', activeTemplateId));
      window.location.reload();
    } catch (e) {
      setTemplateMessage(e?.response?.data?.message || 'Could not delete the template.');
    }
  }

  const hasFanOut = selectedEntities.length > 1;

  return (
    <AppLayout title="Reports" subtitle="Custom report builder">
      <Head title="Custom Reports" />

      <ReportTabs
        active="builder"
        tabs={[
          { key: 'overview', label: 'Overview', href: route('reports') },
          { key: 'ar_aging', label: 'AR Aging', href: route('reports') },
          { key: 'leases', label: 'Lease Expiry', href: route('reports') },
          { key: 'tenants', label: 'Tenant Summary', href: route('reports') },
          { key: 'builder', label: 'Custom Reports', onClick: () => {} },
        ]}
      />

      <div style={{ display: 'grid', gridTemplateColumns: 'minmax(0, 1.1fr) minmax(0, 1.6fr)', gap: 20, alignItems: 'start' }}>
        {/* ── Left: builder controls ── */}
        <div>
          {/* Templates */}
          <div className="card" style={{ marginBottom: 16 }}>
            <div className="card-header"><h3>Templates</h3></div>
            <div style={{ padding: 16, display: 'flex', flexDirection: 'column', gap: 10 }}>
              <select className="form-input" value={activeTemplateId} onChange={(e) => loadTemplate(e.target.value)}>
                <option value="">— Load a saved template —</option>
                {templates.map((t) => (
                  <option key={t.id} value={t.id}>{t.name}{t.is_shared ? ' (shared)' : ''}</option>
                ))}
              </select>
              <input className="form-input" placeholder="Report name" value={templateName} onChange={(e) => setTemplateName(e.target.value)} />
              <textarea className="form-input" placeholder="Description (optional)" rows={2} value={templateDescription} onChange={(e) => setTemplateDescription(e.target.value)} />
              <label style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 13 }}>
                <input type="checkbox" checked={templateShared} onChange={(e) => setTemplateShared(e.target.checked)} />
                Shared with my team
              </label>
              <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                <button className="btn-primary" disabled={savingTemplate} onClick={() => saveTemplate(true)}>Save as new</button>
                {activeTemplateId && (
                  <>
                    <button className="btn-secondary" disabled={savingTemplate} onClick={() => saveTemplate(false)}>Update</button>
                    <button className="btn-secondary" onClick={deleteTemplate}>Delete</button>
                  </>
                )}
              </div>
              {templateMessage && <div style={{ fontSize: 12.5, color: 'var(--text-muted)' }}>{templateMessage}</div>}
            </div>
          </div>

          {/* Entities */}
          <div className="card" style={{ marginBottom: 16 }}>
            <div className="card-header"><h3>Entities</h3></div>
            <div style={{ padding: 16, display: 'flex', flexDirection: 'column', gap: 14 }}>
              {Object.entries(groups).map(([group, ents]) => (
                <div key={group}>
                  <div style={{ fontSize: 11.5, fontWeight: 700, color: 'var(--text-muted)', textTransform: 'uppercase', marginBottom: 6 }}>{group}</div>
                  <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                    {ents.map((ent) => (
                      <label key={ent.key} style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 13, padding: '5px 10px', borderRadius: 8, border: '1px solid var(--border)', cursor: 'pointer' }}>
                        <input type="checkbox" checked={selectedEntities.includes(ent.key)} onChange={() => toggleEntity(ent.key)} />
                        {ent.label}
                      </label>
                    ))}
                  </div>
                </div>
              ))}

              {selectedEntities.length > 1 && (
                <div>
                  <div style={{ fontSize: 11.5, fontWeight: 700, color: 'var(--text-muted)', textTransform: 'uppercase', marginBottom: 6 }}>Primary entity</div>
                  <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                    {selectedEntities.map((key) => (
                      <label key={key} style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 13 }}>
                        <input type="radio" name="primary" checked={primaryEntity === key} onChange={() => setPrimaryEntity(key)} />
                        {registry[key]?.label ?? key}
                      </label>
                    ))}
                  </div>
                </div>
              )}
            </div>
          </div>

          {/* Columns */}
          {selectedEntities.length > 0 && (
            <div className="card" style={{ marginBottom: 16 }}>
              <div className="card-header"><h3>Columns</h3></div>
              <div style={{ padding: 16, display: 'flex', flexDirection: 'column', gap: 14 }}>
                {selectedEntities.map((entKey) => {
                  const ent = registry[entKey];
                  if (!ent) return null;
                  return (
                    <div key={entKey}>
                      <div style={{ fontSize: 12.5, fontWeight: 700, marginBottom: 6 }}>{ent.label}</div>
                      <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                        {Object.entries(ent.columns).map(([field, col]) => (
                          <label key={field} style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 12.5, padding: '4px 8px', borderRadius: 6, border: '1px solid var(--border)', cursor: 'pointer' }}>
                            <input
                              type="checkbox"
                              checked={selectedColumns.some((c) => c.entity === entKey && c.field === field)}
                              onChange={() => toggleColumn(entKey, field)}
                            />
                            {col.label}
                          </label>
                        ))}
                      </div>
                    </div>
                  );
                })}

                {selectedColumns.length > 0 && (
                  <div>
                    <div style={{ fontSize: 11.5, fontWeight: 700, color: 'var(--text-muted)', textTransform: 'uppercase', marginBottom: 6 }}>Column order</div>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
                      {selectedColumns.map((c, i) => (
                        <div key={`${c.entity}.${c.field}`} style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 12.5 }}>
                          <span style={{ flex: 1 }}>{registry[c.entity]?.label} — {registry[c.entity]?.columns?.[c.field]?.label}</span>
                          <button className="btn-secondary" style={{ padding: '2px 8px' }} disabled={i === 0} onClick={() => moveColumn(i, -1)}>↑</button>
                          <button className="btn-secondary" style={{ padding: '2px 8px' }} disabled={i === selectedColumns.length - 1} onClick={() => moveColumn(i, 1)}>↓</button>
                        </div>
                      ))}
                    </div>
                  </div>
                )}
              </div>
            </div>
          )}

          {/* Filters */}
          {selectedEntities.length > 0 && (
            <div className="card" style={{ marginBottom: 16 }}>
              <div className="card-header"><h3>Filters</h3></div>
              <div style={{ padding: 16, display: 'flex', flexDirection: 'column', gap: 12 }}>
                {properties.length > 0 && (
                  <div>
                    <div style={{ fontSize: 12, color: 'var(--text-muted)', marginBottom: 4 }}>Property</div>
                    <select className="form-input" value={propertyId} onChange={(e) => setPropertyId(e.target.value)}>
                      <option value="">All properties</option>
                      {properties.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                    </select>
                  </div>
                )}
                {selectedEntities.map((entKey) => {
                  const ent = registry[entKey];
                  if (!ent) return null;
                  return (
                    <div key={entKey}>
                      <div style={{ fontSize: 12.5, fontWeight: 700, marginBottom: 6 }}>{ent.label}</div>
                      <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                        {Object.entries(ent.columns).map(([field, col]) => (
                          <div key={field}>
                            <div style={{ fontSize: 12, color: 'var(--text-muted)', marginBottom: 3 }}>{col.label} <span style={{ opacity: .6 }}>({TYPE_LABEL[col.type]})</span></div>
                            <FilterInput
                              type={col.type}
                              options={col.options}
                              value={filters[columnKey(entKey, field)] || {}}
                              onChange={(v) => setFilterValue(entKey, field, v)}
                            />
                          </div>
                        ))}
                      </div>
                    </div>
                  );
                })}
              </div>
            </div>
          )}
        </div>

        {/* ── Right: preview & export ── */}
        <div className="card" style={{ position: 'sticky', top: 16 }}>
          <div className="card-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
            <h3>Preview</h3>
            <div style={{ display: 'flex', gap: 8 }}>
              <button className="btn-primary" onClick={runPreview} disabled={loadingPreview}>
                {loadingPreview ? 'Running…' : 'Run Preview'}
              </button>
              <button className="btn-secondary" onClick={() => exportReport('csv')} disabled={selectedColumns.length === 0}>Export CSV</button>
              <button className="btn-secondary" onClick={() => exportReport('pdf')} disabled={selectedColumns.length === 0}>Export PDF</button>
            </div>
          </div>

          <div style={{ padding: 16 }}>
            {previewError && (
              <div style={{ padding: 10, borderRadius: 8, background: 'var(--red-bg, rgba(239,68,68,.1))', color: 'var(--red)', fontSize: 13, marginBottom: 12 }}>
                {previewError}
              </div>
            )}

            {hasFanOut && (
              <div style={{ fontSize: 12, color: 'var(--text-muted)', marginBottom: 12 }}>
                Combining entities can repeat rows when a record has several related rows (e.g. a unit with multiple payments) — this is expected.
              </div>
            )}

            {!preview && !previewError && (
              <div style={{ color: 'var(--text-muted)', fontSize: 13 }}>Select entities and columns, then run the preview.</div>
            )}

            {preview && (
              <>
                <div style={{ overflowX: 'auto' }}>
                  <table className="data-table">
                    <thead>
                      <tr>
                        {preview.columns.map((c) => <th key={c.key}>{c.label}</th>)}
                      </tr>
                    </thead>
                    <tbody>
                      {preview.rows.map((row, i) => (
                        <tr key={i}>
                          {preview.columns.map((c) => <td key={c.key}>{String(row[c.key] ?? '')}</td>)}
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
                <div style={{ fontSize: 12, color: 'var(--text-muted)', marginTop: 10 }}>
                  Showing {preview.shown} of {preview.total} row(s).
                </div>
              </>
            )}
          </div>
        </div>
      </div>
    </AppLayout>
  );
}
