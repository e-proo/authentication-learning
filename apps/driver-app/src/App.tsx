import { useCallback, useEffect, useMemo, useState } from 'react';

const API_BASE = (import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000/api/v1').replace(/\/$/, '');
type Profile = { id: number; display_name: string; driver_code: string; status: string; organization?: { name: string }; active_shift?: Shift | null };
type Shift = { id: number; shift_code: string; status: string; started_at: string; vehicle?: { registration_plate?: string; vehicle_code?: string } | null };
type Task = { id: number; task_number: string; task_type: string; status: string; location?: unknown; recipient?: { name?: string; phone?: string }; proof_reference?: string | null; failure_reason?: string | null; notes?: string | null; shipment?: { shipment_number?: string; tracking_number?: string; status?: string } | null; trip?: { trip_number?: string; origin?: unknown; destination?: unknown } | null };
type ApiResponse<T> = { data: T; message?: string };
const statusNames: Record<string, string> = { queued: 'بانتظار الإسناد', assigned: 'مُسندة', en_route: 'في الطريق', arrived: 'وصل السائق', completed: 'مكتملة', failed: 'متعذرة', cancelled: 'ملغاة', active: 'نشطة', ended: 'منتهية', needs_review: 'تحتاج مراجعة' };
const typeNames: Record<string, string> = { collection: 'استلام', hub_delivery: 'تسليم إلى مركز', last_mile: 'توصيل للعميل', return_pickup: 'استلام مرتجع' };

async function api<T>(path: string, token: string, init: RequestInit = {}): Promise<T> {
  const headers = new Headers(init.headers || {});
  headers.set('Accept', 'application/json');
  headers.set('Authorization', `Bearer ${token}`);
  if (init.body && !(init.body instanceof FormData)) headers.set('Content-Type', 'application/json');
  const response = await fetch(`${API_BASE}${path}`, { ...init, headers });
  const body = await response.json().catch(() => ({}));
  if (!response.ok) {
    const errors = body.errors ? Object.values(body.errors).flat().join(' ') : '';
    throw new Error(errors || body.message || `تعذر تنفيذ الطلب (${response.status})`);
  }
  return body as T;
}
const json = (value: unknown): string => JSON.stringify(value);

export default function App() {
  const [token, setToken] = useState(() => sessionStorage.getItem('driver_token') || '');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [profiles, setProfiles] = useState<Profile[]>([]);
  const [profileId, setProfileId] = useState('');
  const [tasks, setTasks] = useState<Task[]>([]);
  const [summary, setSummary] = useState<Record<string, unknown> | null>(null);
  const [shift, setShift] = useState<Shift | null>(null);
  const [selected, setSelected] = useState<Task | null>(null);
  const [filter, setFilter] = useState('active');
  const [loading, setLoading] = useState(false);
  const [notice, setNotice] = useState('');
  const [error, setError] = useState('');

  const activeCount = useMemo(() => tasks.filter(t => ['assigned', 'en_route', 'arrived'].includes(t.status)).length, [tasks]);
  const message = (fn: () => Promise<void>) => async () => {
    setLoading(true); setError(''); setNotice('');
    try { await fn(); } catch (e) { setError(e instanceof Error ? e.message : 'حدث خطأ غير متوقع'); }
    finally { setLoading(false); }
  };

  const refresh = useCallback(async (auth = token, selectedProfile = profileId) => {
    const me = await api<ApiResponse<{ driver_profiles: Profile[] }>>('/driver/me', auth);
    setProfiles(me.data.driver_profiles);
    const usable = me.data.driver_profiles.filter(p => p.status === 'active');
    const chosen = usable.find(p => String(p.id) === selectedProfile) || usable[0];
    if (!chosen) throw new Error('لا يوجد ملف سائق نشط مرتبط بهذا الحساب. تواصل مع مسؤول المؤسسة.');
    setProfileId(String(chosen.id));
    const query = `?driver_profile_id=${chosen.id}`;
    const [taskData, summaryData, shiftData] = await Promise.all([
      api<ApiResponse<{ data: Task[] }>>(`/driver/tasks${query}&per_page=100`, auth),
      api<ApiResponse<Record<string, unknown>>>(`/driver/tasks/summary${query}`, auth),
      api<ApiResponse<Shift[]>>(`/driver/shifts/current${query}`, auth),
    ]);
    const list = Array.isArray(taskData.data) ? taskData.data : (taskData.data as unknown as { data: Task[] }).data;
    setTasks(list || []);
    setSummary(summaryData.data);
    setShift(shiftData.data?.[0] || null);
    setSelected(current => current ? (list || []).find(t => t.id === current.id) || null : null);
  }, [token, profileId]);

  useEffect(() => {
    if (!token) return;
    void message(async () => { await refresh(token, profileId); })();
  // refresh runs on explicit reload/profile selection; token is the session boundary.
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [token]);

  const signIn = message(async () => {
    const result = await api<{ token: string }>('/auth/login', '', { method: 'POST', body: json({ email, password }) });
    sessionStorage.setItem('driver_token', result.token);
    setToken(result.token);
    setPassword('');
    setNotice('تم تسجيل الدخول. جارٍ تحميل بيانات السائق.');
  });

  const signOut = message(async () => {
    try { await api('/auth/logout', token, { method: 'POST' }); } finally {
      sessionStorage.removeItem('driver_token'); setToken(''); setProfiles([]); setTasks([]); setSelected(null); setShift(null); setSummary(null);
    }
  });

  const doRefresh = message(async () => { await refresh(); setNotice('تم تحديث البيانات.'); });
  const changeProfile = (id: string) => {
    setProfileId(id);
    void message(async () => { await refresh(token, id); setNotice('تم تغيير ملف السائق.'); })();
  };

  const transition = (task: Task, status: string) => message(async () => {
    const payload: Record<string, unknown> = { status, metadata: { source: 'driver_app' } };
    if (status === 'completed') {
      if (!task.proof_reference) throw new Error('ارفع إثبات التسليم أولًا.');
      payload.proof_reference = task.proof_reference;
      if (task.task_type === 'last_mile') {
        const recipient = window.prompt('اكتب اسم المستلم كما استلم الشحنة:');
        if (!recipient?.trim()) throw new Error('اسم المستلم مطلوب لإكمال توصيل العميل.');
        payload.recipient_name = recipient.trim();
      }
    }
    if (status === 'failed') {
      const reason = window.prompt('اذكر سبب تعذر المهمة:');
      if (!reason?.trim()) throw new Error('سبب التعذر مطلوب.');
      payload.failure_reason = reason.trim();
    }
    await api(`/driver/tasks/${task.id}/events`, token, { method: 'POST', body: json(payload) });
    await refresh(); setNotice('تم تحديث حالة المهمة.');
  });

  const uploadEvidence = (task: Task) => message(async () => {
    const picker = document.createElement('input');
    picker.type = 'file'; picker.accept = 'image/jpeg,image/png,image/webp,application/pdf';
    const file = await new Promise<File | null>(resolve => {
      picker.onchange = () => resolve(picker.files?.[0] || null);
      picker.click();
    });
    if (!file) return;
    if (file.size > 10 * 1024 * 1024) throw new Error('حجم الملف يتجاوز 10 ميغابايت.');
    const form = new FormData(); form.append('file', file); form.append('evidence_type', file.type === 'application/pdf' ? 'document' : 'photo');
    form.append('driver_profile_id', profileId);
    const result = await api<ApiResponse<{ proof_reference: string }>>(`/driver/tasks/${task.id}/evidence`, token, { method: 'POST', body: form });
    setTasks(current => current.map(t => t.id === task.id ? { ...t, proof_reference: result.data.proof_reference } : t));
    setSelected(current => current?.id === task.id ? { ...current, proof_reference: result.data.proof_reference } : current);
    setNotice('تم رفع الإثبات إلى التخزين الخاص. أكمل المهمة بعد التأكد من بيانات التسليم.');
  });

  const startShift = message(async () => {
    await api('/driver/shifts/start', token, { method: 'POST', body: json({ driver_profile_id: Number(profileId) }) });
    await refresh(); setNotice('بدأت الوردية.');
  });
  const endShift = message(async () => {
    if (!shift) return;
    const payload: Record<string, unknown> = {};
    if (shift.vehicle) {
      const rawOdometer = window.prompt('أدخل قراءة عداد الكيلومترات عند نهاية الوردية:');
      if (rawOdometer === null || rawOdometer.trim() === '' || !/^\\d+$/.test(rawOdometer.trim())) {
        throw new Error('قراءة العداد النهائية مطلوبة ويجب أن تكون رقمًا صحيحًا.');
      }
      payload.odometer_end = Number(rawOdometer);
      const checklist: Record<string, boolean> = {};
      for (const [key, label] of [['tires', 'الإطارات'], ['brakes', 'الفرامل'], ['lights', 'الأنوار'], ['mirrors', 'المرايا'], ['safety_equipment', 'معدات السلامة'], ['cargo_area', 'منطقة الحمولة']]) {
        checklist[key] = window.confirm(`هل ${label} سليمة بعد الوردية؟ اضغط إلغاء إذا وُجد عيب.`);
      }
      payload.post_trip_checklist = checklist;
      if (Object.values(checklist).some(value => !value)) {
        payload.defect_notes = window.prompt('صف العيوب المكتشفة (مطلوب للمراجعة):') || 'تم رصد عيب في فحص ما بعد الوردية.';
      }
    }
    await api(`/driver/shifts/${shift.id}/end`, token, { method: 'POST', body: json(payload) });
    await refresh(); setNotice('تم إنهاء الوردية.');
  });

  const visibleTasks = tasks.filter(t => filter === 'all' || (filter === 'active' ? ['assigned', 'en_route', 'arrived'].includes(t.status) : t.status === filter));

  if (!token) return <main className="login-page">
    <section className="login-card">
      <div className="brand-mark">ل</div><p className="eyebrow">LOGISTER / DRIVER</p>
      <h1>مرحبًا بعودتك</h1><p className="muted">سجّل الدخول لعرض مهامك وورديتك.</p>
      <form onSubmit={e => { e.preventDefault(); void signIn(); }}>
        <label>البريد الإلكتروني<input type="email" autoComplete="username" required value={email} onChange={e => setEmail(e.target.value)} /></label>
        <label>كلمة المرور<input type="password" autoComplete="current-password" required value={password} onChange={e => setPassword(e.target.value)} /></label>
        <button className="primary full" disabled={loading}>{loading ? 'جارٍ الدخول…' : 'تسجيل الدخول'}</button>
      </form>
      <p className="tiny">يتطلب الحساب ملف سائق نشطًا مرتبطًا به من قبل المؤسسة.</p>
      {error && <p className="alert error">{error}</p>}
    </section>
  </main>;

  return <main className="app-shell">
    <header className="topbar">
      <div className="brand-lockup"><div className="brand-mark small">ل</div><div><strong>اللوجستر</strong><span>تطبيق السائقين</span></div></div>
      <div className="header-actions">
        <button className="ghost" onClick={() => void doRefresh()} disabled={loading}>↻ تحديث</button>
        <button className="ghost" onClick={() => void signOut()} disabled={loading}>خروج</button>
      </div>
    </header>
    <section className="welcome">
      <div><p className="eyebrow">مساحة السائق المستقلة</p><h1>جاهز لرحلتك؟</h1><p className="muted">تابع مهامك وسجّل أحداث التسليم من مكان واحد.</p></div>
      <div className="profile-select"><label>ملف السائق</label><select value={profileId} onChange={e => void changeProfile(e.target.value)}>{profiles.map(p => <option key={p.id} value={p.id}>{p.display_name} · {p.driver_code}</option>)}</select></div>
    </section>
    {error && <p className="alert error">{error}</p>}{notice && <p className="alert success">{notice}</p>}
    <section className="metrics">
      <article><span>كل المهام</span><strong>{String(summary?.total ?? tasks.length)}</strong></article>
      <article><span>مهام نشطة</span><strong>{String(summary?.active ?? activeCount)}</strong></article>
      <article><span>مكتملة</span><strong>{String((summary?.by_status as Record<string, number> | undefined)?.completed ?? 0)}</strong></article>
      <article><span>الوردية</span><strong className="metric-word">{shift ? 'نشطة' : 'غير نشطة'}</strong></article>
    </section>
    <section className="shift-panel">
      <div><p className="eyebrow">الوردية الحالية</p><h2>{shift ? shift.shift_code : 'لا توجد وردية نشطة'}</h2><p className="muted">{shift ? `بدأت ${new Date(shift.started_at).toLocaleString('ar')}` : 'ابدأ ورديتك عند الاستعداد للعمل.'}</p></div>
      {shift ? <button className="outline" disabled={loading} onClick={() => void endShift()}>إنهاء الوردية</button> : <button className="primary" disabled={loading} onClick={() => void startShift()}>بدء الوردية</button>}
    </section>
    <div className="content-grid">
      <section className="task-panel">
        <div className="section-heading"><div><p className="eyebrow">قائمة التشغيل</p><h2>مهامي</h2></div><span className="count-pill">{visibleTasks.length} مهمة</span></div>
        <div className="filters">
          <button className={filter === 'active' ? 'filter active' : 'filter'} onClick={() => setFilter('active')}>النشطة</button>
          <button className={filter === 'all' ? 'filter active' : 'filter'} onClick={() => setFilter('all')}>الكل</button>
          <button className={filter === 'completed' ? 'filter active' : 'filter'} onClick={() => setFilter('completed')}>المكتملة</button>
          <button className={filter === 'failed' ? 'filter active' : 'filter'} onClick={() => setFilter('failed')}>المتعذرة</button>
        </div>
        {visibleTasks.length === 0 ? <div className="empty"><span>✓</span><h3>لا توجد مهام هنا</h3><p>حدّث القائمة أو اختر تصنيفًا آخر.</p></div> : <div className="task-list">{visibleTasks.map(task => <button className={selected?.id === task.id ? 'task-card selected' : 'task-card'} key={task.id} onClick={() => setSelected(task)}>
          <div className="task-card-top"><span className={`status-dot ${task.status}`}></span><span className="task-code">{task.task_number}</span><span className={`status-chip ${task.status}`}>{statusNames[task.status] || task.status}</span></div>
          <h3>{typeNames[task.task_type] || task.task_type}</h3>
          <p>{task.shipment?.shipment_number || task.trip?.trip_number || 'مهمة تشغيلية'}</p>
          <span className="task-open">عرض التفاصيل ←</span>
        </button>)}</div>}
      </section>
      <aside className="detail-panel">
        {selected ? <><div className="section-heading"><div><p className="eyebrow">تفاصيل المهمة</p><h2>{selected.task_number}</h2></div><button className="icon-button" onClick={() => setSelected(null)} aria-label="إغلاق">×</button></div>
          <div className="detail-line"><span>النوع</span><strong>{typeNames[selected.task_type] || selected.task_type}</strong></div>
          <div className="detail-line"><span>الحالة</span><strong>{statusNames[selected.status] || selected.status}</strong></div>
          <div className="detail-line"><span>رقم الشحنة</span><strong>{selected.shipment?.shipment_number || '—'}</strong></div>
          <div className="detail-line"><span>رقم التتبع</span><strong>{selected.shipment?.tracking_number || '—'}</strong></div>
          <div className="detail-line"><span>المستلم</span><strong>{selected.recipient?.name || 'يُحدد عند التسليم'}</strong></div>
          <div className="detail-line"><span>إثبات التسليم</span><strong>{selected.proof_reference ? 'تم الرفع ✓' : 'غير مرفوع'}</strong></div>
          <div className="action-stack">
            {selected.status === 'assigned' && <button className="primary full" disabled={loading} onClick={() => void transition(selected, 'en_route')}>بدء التوجه</button>}
            {selected.status === 'en_route' && <button className="primary full" disabled={loading} onClick={() => void transition(selected, 'arrived')}>تسجيل الوصول</button>}
            {['en_route', 'arrived'].includes(selected.status) && <button className="outline full" disabled={loading} onClick={() => void uploadEvidence(selected)}>＋ رفع إثبات (صورة / PDF)</button>}
            {selected.status === 'arrived' && <button className="primary full" disabled={loading} onClick={() => void transition(selected, 'completed')}>تأكيد إكمال المهمة</button>}
            {['en_route', 'arrived'].includes(selected.status) && <button className="danger full" disabled={loading} onClick={() => void transition(selected, 'failed')}>تعذّر التسليم</button>}
          </div>
          <p className="tiny">تُطبّق الخوادم قواعد الإسناد والحالة وإثبات التسليم قبل قبول أي تغيير.</p>
        </> : <div className="detail-empty"><div className="detail-illustration">↙</div><h2>اختر مهمة</h2><p>حدد مهمة من القائمة لعرض تفاصيلها والإجراءات المتاحة.</p></div>}
      </aside>
    </div>
    <footer>اللوجستر · تطبيق السائقين المستقل <span>نسخة أولية متصلة بالـ API</span></footer>
  </main>;
}
