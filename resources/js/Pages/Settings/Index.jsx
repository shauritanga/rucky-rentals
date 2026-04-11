import { useEffect, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, router, usePage } from '@inertiajs/react';

export default function SettingsIndex({ property }) {
    const { props } = usePage();
    const flash = props?.flash ?? {};

    const [processing, setProcessing] = useState(false);
    const [successMsg, setSuccessMsg] = useState('');
    const [errors, setErrors] = useState({});

    const [info, setInfo] = useState({
        address:  property?.address  ?? '',
        city:     property?.city     ?? '',
        country:  property?.country  ?? 'Tanzania',
        phone:    property?.phone    ?? '',
    });

    const [bank, setBank] = useState({
        bank_name:         property?.bank_name         ?? '',
        bank_account:      property?.bank_account      ?? '',
        bank_account_name: property?.bank_account_name ?? '',
        swift_code:        property?.swift_code        ?? '',
    });

    // Auto-dismiss success
    useEffect(() => {
        if (!successMsg) return;
        const t = setTimeout(() => setSuccessMsg(''), 4000);
        return () => clearTimeout(t);
    }, [successMsg]);

    // Show server flash on first load
    useEffect(() => {
        if (flash.success) setSuccessMsg(flash.success);
    }, [flash.success]);

    const save = () => {
        setProcessing(true);
        setErrors({});
        router.patch('/settings', { ...info, ...bank }, {
            preserveScroll: true,
            onSuccess: () => setSuccessMsg('Settings saved successfully.'),
            onError: (errs) => setErrors(errs),
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <AppLayout title="Property Settings" subtitle={property?.name ?? ''}>
            <Head title="Property Settings" />

            <div className="page-header">
                <div>
                    <div className="page-title">Property Settings</div>
                    <div className="page-sub">Configure address, contact details and payment information for {property?.name ?? 'this property'}</div>
                </div>
                <button
                    type="button"
                    className="btn-primary"
                    onClick={save}
                    disabled={processing}
                >
                    {processing ? <><span className="btn-spinner" />Saving…</> : 'Save Settings'}
                </button>
            </div>

            {successMsg && (
                <div className="alert-success" style={{ marginBottom: 16 }}>
                    {successMsg}
                </div>
            )}

            <div className="grid-2" style={{ gridTemplateColumns: '1fr 1fr', alignItems: 'start' }}>

                {/* ── Property Information ── */}
                <div className="card" style={{ padding: 20 }}>
                    <div className="card-title" style={{ marginBottom: 4 }}>Property Information</div>
                    <div style={{ fontSize: 12.5, color: 'var(--text-muted)', marginBottom: 16 }}>
                        Address and contact details printed on invoices and correspondence.
                    </div>

                    <div className="form-row">
                        <div className="form-group">
                            <label className="form-label">Address</label>
                            <input
                                className="form-input"
                                value={info.address}
                                onChange={e => setInfo(p => ({ ...p, address: e.target.value }))}
                                placeholder="Street / building name"
                            />
                            {errors.address && <div className="form-error">{errors.address}</div>}
                        </div>
                    </div>

                    <div className="form-row">
                        <div className="form-group">
                            <label className="form-label">City</label>
                            <input
                                className="form-input"
                                value={info.city}
                                onChange={e => setInfo(p => ({ ...p, city: e.target.value }))}
                                placeholder="e.g. Dar es Salaam"
                            />
                            {errors.city && <div className="form-error">{errors.city}</div>}
                        </div>
                        <div className="form-group">
                            <label className="form-label">Country</label>
                            <input
                                className="form-input"
                                value={info.country}
                                onChange={e => setInfo(p => ({ ...p, country: e.target.value }))}
                                placeholder="e.g. Tanzania"
                            />
                            {errors.country && <div className="form-error">{errors.country}</div>}
                        </div>
                    </div>

                    <div className="form-row">
                        <div className="form-group">
                            <label className="form-label">Phone Number</label>
                            <input
                                className="form-input"
                                value={info.phone}
                                onChange={e => setInfo(p => ({ ...p, phone: e.target.value }))}
                                placeholder="+255 000 000 000"
                            />
                            {errors.phone && <div className="form-error">{errors.phone}</div>}
                        </div>
                    </div>
                </div>

                {/* ── Payment Details ── */}
                <div className="card" style={{ padding: 20 }}>
                    <div className="card-title" style={{ marginBottom: 4 }}>Payment Details</div>
                    <div style={{ fontSize: 12.5, color: 'var(--text-muted)', marginBottom: 16 }}>
                        Bank account details printed in the Payment Details section of every proforma invoice PDF sent to tenants.
                    </div>

                    <div className="form-row">
                        <div className="form-group">
                            <label className="form-label">Bank Name</label>
                            <input
                                className="form-input"
                                value={bank.bank_name}
                                onChange={e => setBank(p => ({ ...p, bank_name: e.target.value }))}
                                placeholder="e.g. Diamond Trust Bank"
                            />
                            {errors.bank_name && <div className="form-error">{errors.bank_name}</div>}
                        </div>
                        <div className="form-group">
                            <label className="form-label">Account Number</label>
                            <input
                                className="form-input"
                                value={bank.bank_account}
                                onChange={e => setBank(p => ({ ...p, bank_account: e.target.value }))}
                                placeholder="e.g. 0353 2012 002"
                            />
                            {errors.bank_account && <div className="form-error">{errors.bank_account}</div>}
                        </div>
                    </div>

                    <div className="form-row">
                        <div className="form-group">
                            <label className="form-label">Account Name</label>
                            <input
                                className="form-input"
                                value={bank.bank_account_name}
                                onChange={e => setBank(p => ({ ...p, bank_account_name: e.target.value }))}
                                placeholder="e.g. Property Management Company"
                            />
                            {errors.bank_account_name && <div className="form-error">{errors.bank_account_name}</div>}
                        </div>
                        <div className="form-group">
                            <label className="form-label">Swift Code</label>
                            <input
                                className="form-input"
                                value={bank.swift_code}
                                onChange={e => setBank(p => ({ ...p, swift_code: e.target.value }))}
                                placeholder="e.g. DTKETZTZ"
                            />
                            {errors.swift_code && <div className="form-error">{errors.swift_code}</div>}
                        </div>
                    </div>

                    {/* Preview */}
                    {(bank.bank_name || bank.bank_account) && (
                        <div style={{
                            marginTop: 16,
                            padding: '12px 14px',
                            borderRadius: 8,
                            background: 'var(--bg-elevated)',
                            border: '1px solid var(--border)',
                        }}>
                            <div style={{ fontSize: 10, fontWeight: 700, textTransform: 'uppercase', letterSpacing: '.06em', color: 'var(--text-muted)', marginBottom: 8 }}>
                                PDF Preview
                            </div>
                            {bank.bank_name && (
                                <div style={{ display: 'flex', gap: 8, fontSize: 12, marginBottom: 3 }}>
                                    <span style={{ color: 'var(--text-muted)', width: 110, flexShrink: 0 }}>Bank Name:</span>
                                    <span style={{ fontWeight: 600, color: 'var(--text)' }}>{bank.bank_name}</span>
                                </div>
                            )}
                            {bank.bank_account && (
                                <div style={{ display: 'flex', gap: 8, fontSize: 12, marginBottom: 3 }}>
                                    <span style={{ color: 'var(--text-muted)', width: 110, flexShrink: 0 }}>Account Number:</span>
                                    <span style={{ fontWeight: 600, color: 'var(--text)' }}>{bank.bank_account}</span>
                                </div>
                            )}
                            {bank.bank_account_name && (
                                <div style={{ display: 'flex', gap: 8, fontSize: 12, marginBottom: 3 }}>
                                    <span style={{ color: 'var(--text-muted)', width: 110, flexShrink: 0 }}>Account Name:</span>
                                    <span style={{ fontWeight: 600, color: 'var(--text)' }}>{bank.bank_account_name}</span>
                                </div>
                            )}
                            {bank.swift_code && (
                                <div style={{ display: 'flex', gap: 8, fontSize: 12 }}>
                                    <span style={{ color: 'var(--text-muted)', width: 110, flexShrink: 0 }}>Swift Code:</span>
                                    <span style={{ fontWeight: 600, color: 'var(--text)' }}>{bank.swift_code}</span>
                                </div>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
