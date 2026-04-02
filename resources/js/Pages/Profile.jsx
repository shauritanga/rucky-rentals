import { useMemo, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

const NOTIF_PREFS_SEED = [
  { key: 'lease_approved', label: 'Lease approved', sub: 'When a lease completes full approval', on: true },
  { key: 'lease_expiring', label: 'Lease expiring soon', sub: '60 days before a lease ends', on: true },
  { key: 'payment_received', label: 'Payment received', sub: 'When a tenant makes a payment', on: true },
  { key: 'payment_overdue', label: 'Payment overdue', sub: 'When rent is past the grace period', on: true },
  { key: 'maint_assigned', label: 'Maintenance ticket assigned', sub: 'When a ticket is assigned to me', on: true },
  { key: 'maint_resolved', label: 'Maintenance ticket resolved', sub: 'When a ticket I opened is closed', on: false },
  { key: 'new_tenant', label: 'New tenant added', sub: 'When a tenant is onboarded', on: false },
  { key: 'doc_uploaded', label: 'Document uploaded', sub: 'When a new document is added', on: false },
];

const PROFILE_ACTIVITY = [
  { dot: 'green', text: 'Lease L-A103-NEW approved', time: 'Today 14:32' },
  { dot: 'accent', text: 'Maintenance ticket TK-016 created', time: 'Today 10:15' },
  { dot: 'accent', text: 'Signed in - MacBook Pro, Chrome', time: 'Today 09:32' },
  { dot: 'green', text: 'Payment recorded - INV-1042 (Unit B-204)', time: 'Yesterday 16:08' },
  { dot: 'amber', text: 'Lease L-F601 submitted for approval', time: 'Yesterday 13:44' },
  { dot: 'accent', text: 'Tenant Sarah Rutto profile updated', time: 'Mar 18, 11:20' },
  { dot: 'green', text: 'Journal entry JE-014 posted', time: 'Mar 18, 09:55' },
  { dot: 'red', text: 'Outage logged - Load shedding (14.5 hrs)', time: 'Mar 17, 17:05' },
  { dot: 'green', text: 'Electricity bills issued - 25 units', time: 'Mar 16, 14:30' },
  { dot: 'accent', text: 'Document "Lease Agreement F-601" uploaded', time: 'Mar 15, 10:00' },
];

const SESSION_SEED = [
  { id: 'this-device', title: 'MacBook Pro - Chrome', sub: 'Dar es Salaam, TZ - Active now', current: true },
  { id: 'iphone', title: 'iPhone 15 - Safari', sub: 'Dar es Salaam, TZ - 2 hours ago', current: false },
];

function pwdStrength(password) {
  let score = 0;
  if (password.length >= 8) score += 1;
  if (/[A-Z]/.test(password)) score += 1;
  if (/[0-9]/.test(password)) score += 1;
  if (/[^A-Za-z0-9]/.test(password)) score += 1;
  if (password.length >= 12) score += 1;

  const map = [
    { width: '0%', color: 'var(--border)', text: '' },
    { width: '25%', color: 'var(--red)', text: 'Weak' },
    { width: '50%', color: 'var(--amber)', text: 'Fair' },
    { width: '75%', color: 'var(--accent)', text: 'Good' },
    { width: '100%', color: 'var(--green)', text: 'Strong' },
  ];

  return map[Math.min(score, 4)];
}

export default function Profile() {
  const { props } = usePage();
  const user = props?.auth?.user;
  const userName = user?.name || 'User';
  const userParts = userName.split(' ');
  const roleLabel = user?.role
    ? (user.role === 'lease_manager'
      ? 'Lease Assistant'
      : user.role.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()))
    : 'Property Manager';

  const [tab, setTab] = useState('personal');
  const [firstName, setFirstName] = useState(userParts[0] || 'User');
  const [lastName, setLastName] = useState(userParts.slice(1).join(' '));
  const [email, setEmail] = useState(user?.email || '');
  const [phone, setPhone] = useState('+255 754 111 222');
  const [bio, setBio] = useState('Managing all operations for the assigned property since 2022. Responsible for leases, maintenance coordination and tenant relations.');
  const [avatarSrc, setAvatarSrc] = useState(user?.avatar_url || '');
  const [avatarUploading, setAvatarUploading] = useState(false);
  const [twofaEnabled, setTwofaEnabled] = useState(false);
  const [notifPrefs, setNotifPrefs] = useState(NOTIF_PREFS_SEED);
  const [sessions, setSessions] = useState(SESSION_SEED);
  const [curPw, setCurPw] = useState('');
  const [newPw, setNewPw] = useState('');
  const [confirmPw, setConfirmPw] = useState('');
  const [toast, setToast] = useState('');
  const [logoutOpen, setLogoutOpen] = useState(false);

  const displayName = useMemo(() => {
    const full = `${firstName} ${lastName}`.trim();
    return full || 'James Mwangi';
  }, [firstName, lastName]);

  const initials = useMemo(() => {
    const a = firstName?.[0] || '';
    const b = lastName?.[0] || '';
    return (a + b).toUpperCase() || 'JM';
  }, [firstName, lastName]);

  const strength = pwdStrength(newPw);

  const showToast = (message) => {
    setToast(message);
    window.clearTimeout(window.__rrToastTimer);
    window.__rrToastTimer = window.setTimeout(() => setToast(''), 1800);
  };

  const onAvatarPick = (event) => {
    const file = event.target.files?.[0];
    if (!file) return;
    // Show instant local preview
    const reader = new FileReader();
    reader.onload = (e) => setAvatarSrc(String(e.target?.result || ''));
    reader.readAsDataURL(file);
    // Upload to server
    setAvatarUploading(true);
    router.post('/profile/avatar', { avatar: file }, {
      forceFormData: true,
      preserveScroll: true,
      onSuccess: () => { setAvatarUploading(false); showToast('Profile photo saved'); },
      onError: () => { setAvatarUploading(false); showToast('Upload failed – try again'); },
    });
  };

  const onSaveProfile = () => showToast('Profile saved successfully');

  const onChangePassword = () => {
    if (!curPw) return showToast('Enter your current password');
    if (!newPw || newPw.length < 8) return showToast('New password must be at least 8 characters');
    if (newPw !== confirmPw) return showToast('Passwords do not match');
    setCurPw('');
    setNewPw('');
    setConfirmPw('');
    showToast('Password updated successfully');
  };

  const onToggle2FA = () => {
    const next = !twofaEnabled;
    setTwofaEnabled(next);
    showToast(next ? 'Two-factor authentication enabled' : 'Two-factor authentication disabled');
  };

  const togglePref = (key) => {
    setNotifPrefs((prev) => prev.map((p) => (p.key === key ? { ...p, on: !p.on } : p)));
  };

  const revokeSession = (id) => {
    setSessions((prev) => prev.filter((s) => s.id !== id));
    showToast('Session revoked');
  };

  const revokeAllOthers = () => {
    setSessions((prev) => prev.filter((s) => s.current));
    showToast('All other sessions signed out');
  };

  const signOut = () => setLogoutOpen(true);

  return (
    <AppLayout title="My Profile" subtitle={userName}>
      <Head title="My Profile" />

      <div className="profile-page">
        <div className="profile-grid">
          <div className="profile-left-col">
            <div className="card">
              <div className="profile-avatar-card-head">
                <div style={{ position: 'relative', marginBottom: 14 }}>
                  <div id="profile-avatar-display" className="profile-avatar-display" style={{ position: 'relative' }}>
                    {avatarSrc ? <img src={avatarSrc} alt="Profile" className="profile-avatar-img" /> : initials}
                    {avatarUploading && (
                      <div style={{ position: 'absolute', inset: 0, borderRadius: 'inherit', background: 'rgba(0,0,0,0.45)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                        <span className="btn-spinner" style={{ width: 18, height: 18, borderWidth: 2, borderColor: 'rgba(255,255,255,0.3)', borderTopColor: '#fff' }} />
                      </div>
                    )}
                  </div>
                  <button type="button" onClick={() => document.getElementById('avatar-file-input')?.click()} className="profile-avatar-edit-btn" aria-label="Edit avatar" disabled={avatarUploading}>
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4z"/></svg>
                  </button>
                  <input id="avatar-file-input" type="file" accept="image/*" style={{ display: 'none' }} onChange={onAvatarPick} />
                </div>

                <div id="profile-display-name" className="profile-display-name">{displayName}</div>
                <div className="profile-role-text">{roleLabel}</div>
                <div className="profile-property-text">Assigned Property</div>
                <span className="profile-active-pill">● Active</span>
              </div>

              <div className="profile-quick-stats">
                <div className="profile-quick-stat profile-quick-stat-right-border">
                  <div className="profile-quick-stat-value">28</div>
                  <div className="profile-quick-stat-label">Active Leases</div>
                </div>
                <div className="profile-quick-stat">
                  <div className="profile-quick-stat-value">4</div>
                  <div className="profile-quick-stat-label">Open Tickets</div>
                </div>
              </div>
            </div>

            <div className="card">
              <div style={{ padding: '14px 16px', display: 'flex', flexDirection: 'column', gap: 10 }}>
                <div className="profile-info-row"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>{email}</div>
                <div className="profile-info-row"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 9.81a19.79 19.79 0 01-3.07-8.67A2 2 0 012 .84h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L6.09 8.29a16 16 0 006.29 6.29l1.61-1.61a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 15.09v1.83z"/></svg>{phone}</div>
                <div className="profile-info-row"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>Last login: Today 09:32</div>
                <div className="profile-info-row"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>Dar es Salaam, TZ</div>
                <div style={{ height: 1, background: 'var(--border-subtle)', margin: '2px 0' }}></div>
                <div className="profile-2fa-row">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="var(--amber)" strokeWidth="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                  <span style={{ color: twofaEnabled ? 'var(--green)' : 'var(--amber)' }}>{twofaEnabled ? '2FA enabled' : '2FA not enabled'}</span>
                </div>
              </div>
            </div>

            <button className="btn-danger" onClick={signOut} style={{ width: '100%', justifyContent: 'center', padding: 9 }}>
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
              Sign Out
            </button>
          </div>

          <div style={{ display: 'flex', flexDirection: 'column', gap: 0, minWidth: 0 }}>
            <div className="profile-tabbar">
              <button className={`prof-tab ${tab === 'personal' ? 'active' : ''}`} onClick={() => setTab('personal')}>Personal</button>
              <button className={`prof-tab ${tab === 'security' ? 'active' : ''}`} onClick={() => setTab('security')}>Security</button>
              <button className={`prof-tab ${tab === 'notifications' ? 'active' : ''}`} onClick={() => setTab('notifications')}>Notifications</button>
              <button className={`prof-tab ${tab === 'activity' ? 'active' : ''}`} onClick={() => setTab('activity')}>Activity</button>
            </div>

            <div className={`prof-tab-content ${tab === 'personal' ? 'active' : ''}`}>
              <div className="card">
                <div className="profile-card-header-row">
                  <div style={{ fontSize: 14, fontWeight: 600 }}>Personal Information</div>
                  <button className="btn-primary" style={{ fontSize: 12.5, padding: '6px 14px' }} onClick={onSaveProfile}>Save Changes</button>
                </div>
                <div className="profile-form-grid">
                  <div><label className="form-label">First Name</label><input className="form-input" type="text" value={firstName} onChange={(e) => setFirstName(e.target.value)} /></div>
                  <div><label className="form-label">Last Name</label><input className="form-input" type="text" value={lastName} onChange={(e) => setLastName(e.target.value)} /></div>
                  <div><label className="form-label">Email Address</label><input className="form-input" type="email" value={email} onChange={(e) => setEmail(e.target.value)} /></div>
                  <div><label className="form-label">Phone Number</label><input className="form-input" type="text" value={phone} onChange={(e) => setPhone(e.target.value)} /></div>
                  <div><label className="form-label">Role</label><input className="form-input" type="text" value={roleLabel} readOnly style={{ opacity: .55, cursor: 'default' }} /></div>
                  <div><label className="form-label">Assigned Property</label><input className="form-input" type="text" value="Assigned Property" readOnly style={{ opacity: .55, cursor: 'default' }} /></div>
                  <div style={{ gridColumn: '1 / -1' }}><label className="form-label">Bio / Notes</label><textarea className="form-input" rows="3" style={{ resize: 'vertical' }} value={bio} onChange={(e) => setBio(e.target.value)} /></div>
                </div>
              </div>
            </div>

            <div className={`prof-tab-content ${tab === 'security' ? 'active' : ''}`}>
              <div className="card" style={{ marginBottom: 14 }}>
                <div className="profile-card-header-row"><div style={{ fontSize: 14, fontWeight: 600 }}>Change Password</div></div>
                <div className="profile-form-grid">
                  <div style={{ gridColumn: '1 / -1' }}><label className="form-label">Current Password</label><input className="form-input" type="password" value={curPw} onChange={(e) => setCurPw(e.target.value)} placeholder="Enter current password" /></div>
                  <div>
                    <label className="form-label">New Password</label>
                    <input className="form-input" type="password" value={newPw} onChange={(e) => setNewPw(e.target.value)} placeholder="Min 8 characters" />
                    <div style={{ height: 3, borderRadius: 20, marginTop: 6, background: 'var(--border)', overflow: 'hidden' }}>
                      <div style={{ height: '100%', width: strength.width, borderRadius: 20, transition: 'width .3s,background .3s', background: strength.color }}></div>
                    </div>
                    <div style={{ fontSize: 11, color: strength.color, marginTop: 3 }}>{strength.text}</div>
                  </div>
                  <div><label className="form-label">Confirm New Password</label><input className="form-input" type="password" value={confirmPw} onChange={(e) => setConfirmPw(e.target.value)} placeholder="Repeat new password" /></div>
                  <div style={{ gridColumn: '1 / -1' }}><button className="btn-ghost" onClick={onChangePassword}>Update Password</button></div>
                </div>
              </div>

              <div className="card" style={{ marginBottom: 14 }}>
                <div className="profile-card-header-row"><div style={{ fontSize: 14, fontWeight: 600 }}>Two-Factor Authentication</div></div>
                <div style={{ padding: 20 }}>
                  <div className="profile-twofa-box">
                    <div>
                      <div style={{ fontSize: 13.5, fontWeight: 500 }}>Authenticator App (TOTP)</div>
                      <div style={{ fontSize: 12, color: twofaEnabled ? 'var(--green)' : 'var(--text-muted)', marginTop: 2 }}>
                        {twofaEnabled ? 'Enabled - your account is protected' : 'Not enabled - your account is less secure'}
                      </div>
                    </div>
                    <button className="btn-primary" style={{ fontSize: 12.5, padding: '6px 14px' }} onClick={onToggle2FA}>{twofaEnabled ? 'Disable 2FA' : 'Enable 2FA'}</button>
                  </div>
                </div>
              </div>

              <div className="card">
                <div className="profile-card-header-row">
                  <div style={{ fontSize: 14, fontWeight: 600 }}>Active Sessions</div>
                  <button onClick={revokeAllOthers} style={{ background: 'none', border: 'none', fontSize: 12.5, color: 'var(--red)', cursor: 'pointer', fontFamily: 'inherit' }}>Sign out all others</button>
                </div>
                <div style={{ padding: '14px 20px', display: 'flex', flexDirection: 'column', gap: 8 }}>
                  {sessions.map((s) => (
                    <div key={s.id} className="profile-session-row">
                      {s.current ? (
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--green)" strokeWidth="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                      ) : (
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" strokeWidth="2"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                      )}
                      <div style={{ flex: 1 }}>
                        <div style={{ fontSize: 13, fontWeight: 500 }}>{s.title}</div>
                        <div style={{ fontSize: 11.5, color: 'var(--text-muted)' }}>{s.sub}{s.current ? ' · This device' : ''}</div>
                      </div>
                      {!s.current && <button onClick={() => revokeSession(s.id)} className="profile-revoke-btn">Revoke</button>}
                    </div>
                  ))}
                </div>
              </div>
            </div>

            <div className={`prof-tab-content ${tab === 'notifications' ? 'active' : ''}`}>
              <div className="card">
                <div className="profile-card-header-row">
                  <div>
                    <div style={{ fontSize: 14, fontWeight: 600 }}>Notification Preferences</div>
                    <div style={{ fontSize: 12, color: 'var(--text-muted)', marginTop: 2 }}>Choose what events trigger alerts for you</div>
                  </div>
                </div>
                <div style={{ padding: '4px 20px' }}>
                  {notifPrefs.map((p) => (
                    <div key={p.key} className="profile-pref-row">
                      <div>
                        <div style={{ fontSize: 13.5, fontWeight: 500 }}>{p.label}</div>
                        <div style={{ fontSize: 12, color: 'var(--text-muted)', marginTop: 2 }}>{p.sub}</div>
                      </div>
                      <button className={`pref-toggle ${p.on ? 'on' : 'off'}`} onClick={() => togglePref(p.key)} aria-label={`Toggle ${p.label}`}></button>
                    </div>
                  ))}
                </div>
              </div>
            </div>

            <div className={`prof-tab-content ${tab === 'activity' ? 'active' : ''}`}>
              <div className="card">
                <div className="profile-card-header-row">
                  <div>
                    <div style={{ fontSize: 14, fontWeight: 600 }}>Recent Activity</div>
                    <div style={{ fontSize: 12, color: 'var(--text-muted)', marginTop: 2 }}>Your last 10 actions in the system</div>
                  </div>
                </div>
                <div style={{ padding: '4px 20px' }}>
                  {PROFILE_ACTIVITY.map((a, idx) => (
                    <div key={idx} className="profile-activity-row">
                      <div className="profile-activity-dot" style={{ background: `var(--${a.dot})` }}></div>
                      <div style={{ flex: 1, fontSize: 13 }}>{a.text}</div>
                      <div style={{ fontSize: 11.5, color: 'var(--text-muted)', whiteSpace: 'nowrap' }}>{a.time}</div>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div className={`toast ${toast ? 'show' : ''}`}>{toast}</div>

      {/* Logout confirmation dialog */}
      <div className={`modal-overlay ${logoutOpen ? 'open' : ''}`} onClick={(e) => e.target === e.currentTarget && setLogoutOpen(false)}>
        <div className="modal" style={{ width: 'min(420px, calc(100vw - 32px))', padding: 0 }}>
          <div className="modal-header" style={{ borderBottom: '1px solid var(--border-subtle)' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
              <div style={{ width: 34, height: 34, borderRadius: 8, background: 'var(--red-dim)', display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--red)" strokeWidth="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
              </div>
              <div className="modal-title">Sign out</div>
            </div>
            <button className="modal-close" onClick={() => setLogoutOpen(false)}>✕</button>
          </div>
          <div style={{ padding: '20px 24px', fontSize: 14, color: 'var(--text-secondary)', lineHeight: 1.6 }}>
            Are you sure you want to sign out of your account? Any unsaved changes will be lost.
          </div>
          <div className="modal-footer" style={{ borderTop: '1px solid var(--border-subtle)', justifyContent: 'flex-end', gap: 8 }}>
            <button className="btn-secondary" onClick={() => setLogoutOpen(false)}>Cancel</button>
            <button className="btn-danger" onClick={() => router.post('/logout')}>
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
              Sign Out
            </button>
          </div>
        </div>
      </div>
    </AppLayout>
  );
}
