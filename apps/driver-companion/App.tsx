import React, { useMemo, useState } from 'react';
import {
  Pressable, ScrollView, StatusBar, StyleSheet, Text, TextInput,
  View, SafeAreaView,
} from 'react-native';

type Screen = 'today' | 'route' | 'tasks' | 'cash' | 'more' | 'task' | 'pickup' | 'transfer' | 'returns' | 'vehicle' | 'sync' | 'messages' | 'performance';
type Task = {
  id: string;
  type: string;
  customer: string;
  address: string;
  details: string;
  status: 'pending' | 'complete';
};
const C = {
  navy: '#10223C', navy2: '#1C385B', blue: '#3765DF', blueSoft: '#EDF2FF',
  ink: '#192940', muted: '#77859A', line: '#E8EDF4', canvas: '#F3F6FA',
  green: '#128567', greenSoft: '#E7F7F0', orange: '#BD7A19', orangeSoft: '#FFF4DF',
  red: '#C24652', redSoft: '#FFF0F1', white: '#FFFFFF',
};
const initialTasks: Task[] = [
  { id: 'WS-24091', type: 'تسليم شحنة', customer: 'سارة محمد', address: 'حدة، شارع المقالح', details: 'طردان · تحصيل 48,500 ر.ي', status: 'pending' },
  { id: 'WS-24092', type: 'تسليم تجاري', customer: 'مؤسسة النخبة', address: 'شارع الزبيري', details: '3 طرود · 10:00–11:00', status: 'pending' },
  { id: 'TR-018', type: 'نقل بين الفروع', customer: 'فرع حدة ← مركز التوزيع', address: 'فرع حدة', details: 'مانيفست مختوم · يتطلب مسح الاستلام', status: 'pending' },
  { id: 'PU-014', type: 'استلام من تاجر', customer: 'متجر الإلكترونيات', address: 'شارع تعز', details: '6 طرود متوقعة · فحص العدد والحالة', status: 'pending' },
  { id: 'RT-041', type: 'مرتجع', customer: 'WS-24079', address: 'العودة إلى فرع حدة', details: 'تسجيل السبب والحالة والإثبات', status: 'pending' },
];
const checks = [
  { id: 'tires', label: 'الإطارات والعجلات', hint: 'الضغط، التلف والتثبيت' },
  { id: 'brakes', label: 'الفرامل والأنوار', hint: 'اختبار التشغيل والاستجابة' },
  { id: 'visibility', label: 'المرايا والزجاج والأحزمة', hint: 'الرؤية وسلامة الركاب' },
  { id: 'safety', label: 'أدوات السلامة والمستندات', hint: 'التجهيزات والوثائق المطلوبة' },
  { id: 'cargo', label: 'المقصورة والأبواب والحمولة', hint: 'تثبيت الطرود وسلامة الأقفال' },
];
const money = (n: number) => new Intl.NumberFormat('en-US').format(n);

function Pill({ children, tone = 'blue' }: { children: React.ReactNode; tone?: 'blue' | 'green' | 'orange' | 'red' | 'gray' }) {
  const tones = {
    blue: [C.blueSoft, C.blue], green: [C.greenSoft, C.green],
    orange: [C.orangeSoft, C.orange], red: [C.redSoft, C.red],
    gray: ['#EFF2F6', '#68778C'],
  } as const;
  const chosen = tones[tone];
  return <View style={[S.pill, { backgroundColor: chosen[0] }]}><Text style={[S.pillText, { color: chosen[1] }]}>{children}</Text></View>;
}
function Button({ title, onPress, kind = 'primary' }: { title: string; onPress: () => void; kind?: 'primary' | 'outline' | 'danger' | 'success' | 'light' }) {
  const theme = {
    primary: [C.blue, C.blue, C.white], outline: [C.white, C.line, C.ink],
    danger: [C.redSoft, '#F6D8DC', C.red], success: [C.green, C.green, C.white],
    light: ['#27476A', '#456488', C.white],
  }[kind];
  return <Pressable onPress={onPress} style={({ pressed }) => [S.button, { backgroundColor: theme[0], borderColor: theme[1] }, pressed && { opacity: 0.82 }]}><Text style={[S.buttonText, { color: theme[2] }]}>{title}</Text></Pressable>;
}
function Card({ children, style }: { children: React.ReactNode; style?: any }) {
  return <View style={[S.card, style]}>{children}</View>;
}
function Heading({ title, sub, action }: { title: string; sub?: string; action?: React.ReactNode }) {
  return <View style={S.heading}><View style={{ flex: 1, gap: 4 }}><Text style={S.h2}>{title}</Text>{sub ? <Text style={S.sub}>{sub}</Text> : null}</View>{action}</View>;
}
function Metric({ label, value, hint, icon, tone = 'blue' }: { label: string; value: string; hint: string; icon: string; tone?: 'blue' | 'green' | 'orange' | 'red' }) {
  const bg = { blue: C.blueSoft, green: C.greenSoft, orange: C.orangeSoft, red: C.redSoft }[tone];
  const fg = { blue: C.blue, green: C.green, orange: C.orange, red: C.red }[tone];
  return <Card style={S.metric}><View style={S.metricTop}><Text style={S.metricLabel}>{label}</Text><View style={[S.metricIcon, { backgroundColor: bg }]}><Text style={{ color: fg, fontSize: 16 }}>{icon}</Text></View></View><Text style={S.metricValue}>{value}</Text><Text style={S.metricHint}>{hint}</Text></Card>;
}

export default function App() {
  const [screen, setScreen] = useState<Screen>('today');
  const [tasks, setTasks] = useState<Task[]>(initialTasks);
  const [selectedTask, setSelectedTask] = useState('WS-24091');
  const [shiftStarted, setShiftStarted] = useState(false);
  const [vehicleChecked, setVehicleChecked] = useState(false);
  const [checked, setChecked] = useState<Record<string, boolean>>({});
  const [odometer, setOdometer] = useState('');
  const [defect, setDefect] = useState('');
  const [scanComplete, setScanComplete] = useState(false);
  const [recipientPin, setRecipientPin] = useState('');
  const [proofPhoto, setProofPhoto] = useState(false);
  const [codCollected, setCodCollected] = useState(86500);
  const [cashHanded, setCashHanded] = useState(0);
  const [cashier, setCashier] = useState('');
  const [pendingSync, setPendingSync] = useState(0);
  const [notice, setNotice] = useState('نسخة أولية ببيانات تجريبية غير متصلة بالخادم.');
  const [returnReason, setReturnReason] = useState('العميل غير متاح');
  const [message, setMessage] = useState('');

  const remaining = tasks.filter(t => t.status === 'pending').length;
  const completed = tasks.filter(t => t.status === 'complete').length + 3;
  const activeTask = tasks.find(t => t.id === selectedTask) || tasks[0];

  const notify = (text: string) => setNotice(text);
  const go = (next: Screen) => {
    setScreen(next);
    setScanComplete(false);
    setRecipientPin('');
    setProofPhoto(false);
  };
  const openTask = (task: Task) => {
    setSelectedTask(task.id);
    setReturnReason('العميل غير متاح');
    if (task.id.startsWith('PU')) go('pickup');
    else if (task.id.startsWith('TR')) go('transfer');
    else if (task.id.startsWith('RT')) go('returns');
    else go('task');
  };
  const markComplete = () => {
    if (!scanComplete) { notify('تحقق من الطرد أولًا عبر المسح التجريبي.'); return; }
    if (activeTask.id.startsWith('WS') && (recipientPin.trim().length < 4 || !proofPhoto)) {
      notify('أدخل رمز المستلم وحدد إثبات الصورة لإكمال سيناريو العرض.');
      return;
    }
    setTasks(prev => prev.map(t => t.id === activeTask.id ? { ...t, status: 'complete' } : t));
    if (activeTask.id === 'WS-24091') setCodCollected(v => v + 48500);
    setPendingSync(v => v + 1);
    setScreen('tasks');
    notify('حُفظت النتيجة محليًا للعرض فقط؛ لم يؤكد الخادم العملية.');
  };
  const startShift = () => {
    if (!vehicleChecked) { go('vehicle'); notify('أكمل فحص المركبة قبل بدء الوردية.'); return; }
    setShiftStarted(v => !v);
    notify(shiftStarted ? 'تمت محاكاة إنهاء الوردية.' : 'تمت محاكاة بدء الوردية.');
  };
  const submitVehicleCheck = () => {
    if (!checks.every(item => checked[item.id])) { notify('تحقق من جميع عناصر فحص المركبة.'); return; }
    if (!odometer.trim()) { notify('أدخل قراءة عداد المسافة.'); return; }
    if (defect.trim()) { setPendingSync(v => v + 1); notify('سُجل عيب تجريبي. يلزم فتح بلاغ وتطبيق سياسة السلامة قبل السماح بالتشغيل.'); return; }
    setVehicleChecked(true);
    setPendingSync(v => v + 1);
    setScreen('today');
    notify('اكتمل الفحص محليًا في وضع العرض فقط.');
  };

  const taskRow = (task: Task, index: number) => (
    <View key={task.id} style={S.taskRow}>
      <View style={[S.step, task.status === 'complete' && S.stepDone]}><Text style={[S.stepText, task.status === 'complete' && { color: C.green }]}>{task.status === 'complete' ? '✓' : String(index + 5).padStart(2, '0')}</Text></View>
      <View style={{ flex: 1, gap: 4 }}><Text style={S.taskTitle}>{task.id} · {task.type}</Text><Text style={S.taskMeta}>{task.customer}</Text><Text style={S.taskMeta}>{task.address} · {task.details}</Text></View>
      {task.status === 'complete' ? <Pill tone="green">مكتمل</Pill> : <Pressable style={S.smallAction} onPress={() => openTask(task)}><Text style={S.smallActionText}>فتح</Text></Pressable>}
    </View>
  );

  const renderToday = () => <>
    <Heading title="ملخص اليوم" sub="السبت، 24 أكتوبر 2026 · وردية التوصيل" action={<Pill>صنعاء</Pill>} />
    <View style={S.metrics}>{[
      { label: 'مهام متبقية', value: String(remaining), hint: 'في خط السير', icon: '⇢', tone: 'blue' as const },
      { label: 'تم الإنجاز', value: String(completed), hint: 'مهام اليوم', icon: '✓', tone: 'green' as const },
      { label: 'تحصيل COD', value: money(codCollected), hint: 'ريال يمني · تجريبي', icon: '﷼', tone: 'orange' as const },
      { label: 'المزامنة', value: pendingSync ? String(pendingSync) + ' معلّق' : 'مكتمل', hint: 'حالة محلية فقط', icon: '⟳', tone: pendingSync ? 'orange' as const : 'green' as const },
    ].map(m => <Metric key={m.label} {...m} />)}</View>
    <Card>
      <Heading title="خط السير الحالي" sub="R-104 · صنعاء جنوب" action={<Pill tone="green">جاهز</Pill>} />
      <View style={S.routeHero}>
        <Label text="المسار النشط" />
        <Text style={S.routeTitle}>الحدة والمنطقة التجارية</Text>
        <View style={S.routeStats}><View><Text style={S.routeStatLabel}>المحطات</Text><Text style={S.routeStatValue}>12</Text></View><View><Text style={S.routeStatLabel}>المتبقي</Text><Text style={S.routeStatValue}>{remaining + 3}</Text></View><View><Text style={S.routeStatLabel}>الوقت المتوقع</Text><Text style={S.routeStatValue}>3 س 20 د</Text></View></View>
        <View style={S.row}><Button title="عرض المسار" kind="light" onPress={() => go('route')} /><Button title="متابعة المهمة" onPress={() => openTask(tasks.find(t => t.id === 'WS-24091') || tasks[0])} /></View>
      </View>
    </Card>
    <Card><Heading title="المهمة التالية" sub="موعد متوقع 09:40" action={<Pill tone="orange">أولوية عالية</Pill>} />{taskRow(tasks.find(t => t.id === 'WS-24091') || tasks[0], 0)}</Card>
    <Card><Heading title="تنبيهات تشغيلية" sub="تذكيرات وردية السائق" action={<Pill tone="red">2 تنبيه</Pill>} />
      <Pressable style={S.linkRow} onPress={() => go('cash')}><Text style={S.linkIcon}>﷼</Text><View style={{ flex: 1, gap: 4 }}><Text style={S.taskTitle}>تسليم النقدية في نهاية الوردية</Text><Text style={S.taskMeta}>راجع المبالغ ومحضر التسليم مع أمين الصندوق.</Text></View><Text style={S.chevron}>‹</Text></Pressable>
      <Pressable style={S.linkRow} onPress={() => go('vehicle')}><Text style={[S.linkIcon, { backgroundColor: C.redSoft, color: C.red }]}>⚠</Text><View style={{ flex: 1, gap: 4 }}><Text style={S.taskTitle}>فحص المركبة</Text><Text style={S.taskMeta}>{vehicleChecked ? 'الفحص مكتمل في هذه الجلسة.' : 'أكمل الفحص قبل بدء الوردية.'}</Text></View><Text style={S.chevron}>‹</Text></Pressable>
    </Card>
  </>;

  const renderRoute = () => <>
    <Heading title="خط السير والمحطات" sub="R-104 · خريطة تخطيطية بلا GPS فعلي" action={<Button title="تحديث" kind="outline" onPress={() => notify('تحديث المسار يتطلب ربط تطبيق التوزيع.')} />} />
    <Card><View style={S.map}><View style={S.roadOne}/><View style={S.roadTwo}/><View style={S.pathLine}/><View style={S.pinOne}><Text style={S.pinText}>5</Text></View><View style={S.pinTwo}><Text style={S.pinText}>6</Text></View><Text style={S.mapNote}>خريطة توضيحية وليست ملاحة حقيقية</Text></View><Heading title="المحطات المقررة" sub="خطة يعتمدها الموزع" />
      {[['مركز توزيع صنعاء','تحميل الشحنات · مغادرة 08:20','تم'],['متجر النور','استلام مرتجع RT-039','تم'],['سارة محمد · WS-24091','حدة، شارع المقالح · تحصيل 48,500 ر.ي','التالية'],['مؤسسة النخبة · WS-24092','شارع الزبيري · 3 طرود','10:15'],['فرع حدة','تسليم النقدية والمرتجعات','12:00']].map((s,i)=><View key={s[0]} style={S.timeline}><View style={[S.dot,i<2&&S.dotDone,i===2&&S.dotNext]}/><View style={{flex:1,gap:4}}><Text style={S.taskTitle}>{s[0]}</Text><Text style={S.taskMeta}>{s[1]}</Text>{i===2&&<Pressable onPress={()=>openTask(tasks[0])}><Text style={S.inlineLink}>فتح المهمة ←</Text></Pressable>}</View><Text style={S.timeText}>{s[2]}</Text></View>)}
    </Card><Text style={S.sub}>تخطيط المسارات وإعادة التوزيع ملك تطبيق Dispatch مستقل؛ تطبيق السائق ينفذ المسار ويبلغ عن التقدم.</Text>
  </>;

  const renderTasks = () => <>
    <Heading title="مهامي" sub="توصيل · استلام · نقل بين الفروع · مرتجعات" action={<Pill>{tasks.length} مهام</Pill>} />
    <View style={S.row}><Pill tone="gray">كل الحالات</Pill><Pill tone="green">المكتملة</Pill><Pill tone="orange">بانتظار التنفيذ</Pill></View>
    <Card>{tasks.map((task, i) => taskRow(task, i))}</Card>
    <Card><Heading title="قواعد تنفيذ المهمة" sub="التطبيق الفعلي يتحقق منها على الخادم" />{['تطابق الشحنة مع المهمة المسندة للسائق.','إثباتات التسليم والعدد والدفعات حسب نوع الخدمة.','سبب معتمد لكل محاولة فاشلة أو اختلاف.','حفظ الحدث مرة واحدة ومزامنته مع سجل العهدة.'].map(t=><Text key={t} style={S.bullet}>•  {t}</Text>)}</Card>
  </>;

  const renderCash = () => <>
    <Heading title="النقدية والتحصيل" sub="COD · مصروفات الوردية · محضر التسليم" action={<Pill tone="orange">تحت التسوية</Pill>} />
    <Card><View style={S.cashHero}><Label text="التحصيل المسجل في نموذج العرض" /><Text style={S.cashAmount}>{money(codCollected)} ر.ي</Text><Text style={S.taskMeta}>بيانات تجريبية فقط</Text></View>
      {[['التحصيل المسجل', money(codCollected) + ' ر.ي'],['سُلّم لأمين الصندوق', money(cashHanded) + ' ر.ي'],['المتبقي للتسليم', money(codCollected-cashHanded) + ' ر.ي']].map(x=><View key={x[0]} style={S.amountRow}><Text style={S.amountLabel}>{x[0]}</Text><Text style={S.amountValue}>{x[1]}</Text></View>)}
      <Button title="تسجيل تحصيل تجريبي +48,500" onPress={() => { setCodCollected(v => v + 48500); setPendingSync(v => v + 1); notify('تمت إضافة تحصيل محلي تجريبي؛ دون قيد مالي حقيقي.'); }} />
      <View style={S.inputGroup}><Label text="اسم أمين الصندوق" /><TextInput value={cashier} onChangeText={setCashier} placeholder="اسم المستلم للمحاكاة" style={S.input}/></View>
      <Button title="محاكاة محضر تسليم النقدية" kind="success" onPress={() => { if (!cashier.trim()) { notify('أدخل اسم أمين الصندوق أولًا.'); return; } setCashHanded(codCollected); setPendingSync(v => v + 1); notify('محضر محلي تجريبي وليس إقرارًا فعليًا من أمين الصندوق.'); }} />
    </Card>
    <Card><Heading title="ضوابط النقدية" />{['الفصل بين ما حصّله السائق وما استلمه أمين الصندوق وما تمت مطابقته.','الاختلاف يحتاج سببًا ودليلًا ومراجعة من دور مستقل.','تكرار إرسال الحدث لا يجوز أن يكرر التحصيل أو القيد المالي.'].map(t=><Text key={t} style={S.bullet}>•  {t}</Text>)}</Card>
  </>;

  const renderMore = () => <>
    <Heading title="خدمات السائق" sub="وظائف مخصصة داخل تطبيق مستقل" />
    <Card>{[
      ['استلام من العميل أو التاجر','المسح والعدد والحالة وإيصال الاستلام','⇤','pickup' as Screen],
      ['النقل بين الفروع والمراكز','المانيفست والأختام والعهدة والاستلام المقابل','⇄','transfer' as Screen],
      ['المرتجعات والرفض','سبب الرفض وإثبات الحالة والإعادة للفرع','↶','returns' as Screen],
      ['فحص المركبة والحوادث','قائمة فحص وعيوب وحوادث ووثائق','▰','vehicle' as Screen],
      ['التواصل والدعم','تعليمات المهمة والرسائل والمساعدة','✉','messages' as Screen],
      ['العمل دون اتصال والمزامنة','الأحداث المحلية والملفات والتعارضات','⟳','sync' as Screen],
      ['الأداء والتسويات','المهام والمحاولات والتحصيل ومحاضر التسليم','◷','performance' as Screen],
    ].map(item=><Pressable key={item[0]} style={S.linkRow} onPress={() => { if(item[3]==='pickup') setSelectedTask('PU-014'); if(item[3]==='transfer') setSelectedTask('TR-018'); if(item[3]==='returns') setSelectedTask('RT-041'); go(item[3] as Screen); }}><Text style={S.linkIcon}>{item[2]}</Text><View style={{flex:1,gap:4}}><Text style={S.taskTitle}>{item[0]}</Text><Text style={S.taskMeta}>{item[1]}</Text></View><Text style={S.chevron}>‹</Text></Pressable>)}</Card>
    <Card><Heading title="الخصوصية والأمان" />{['الوصول إلى بيانات المهام المسندة فقط.','تتبع موقع معلن ومحدود بالوردية/المهمة وفق السياسة.','إثباتات التسليم خاصة ولا تُنشر بروابط عامة.'].map(t=><Text key={t} style={S.bullet}>•  {t}</Text>)}</Card>
  </>;

  const renderDetail = () => {
    if (screen === 'vehicle') return <>
      <Heading title="فحص المركبة" sub="VAN-024 · افحص قبل اعتماد الوردية" action={<Pill tone={vehicleChecked?'green':'orange'}>{vehicleChecked?'مكتمل':'مطلوب'}</Pill>} />
      <Card>{checks.map(item=><Pressable key={item.id} style={S.checkRow} onPress={() => setChecked(v => ({...v,[item.id]:!v[item.id]}))}><View style={[S.checkbox,checked[item.id]&&S.checkboxOn]}><Text style={S.checkboxMark}>{checked[item.id]?'✓':''}</Text></View><View style={{flex:1,gap:4}}><Text style={S.taskTitle}>{item.label}</Text><Text style={S.taskMeta}>{item.hint}</Text></View></Pressable>)}
        <View style={S.inputGroup}><Label text="عداد المسافة (كم)" /><TextInput value={odometer} onChangeText={setOdometer} keyboardType="numeric" placeholder="مثال: 41780" style={S.input}/></View>
        <View style={S.inputGroup}><Label text="ملاحظات أو عيب مكتشف" /><TextInput value={defect} onChangeText={setDefect} multiline placeholder="اكتب العيب؛ ستحتاج لبلاغ في التطبيق الحقيقي" style={[S.input,{height:75,textAlignVertical:'top'}]}/></View>
        <Button title="حفظ نتيجة الفحص التجريبية" onPress={submitVehicleCheck} />
        <Text style={S.formNote}>العطل الحرج يجب أن يمنع التشغيل حسب سياسة السلامة؛ هذا النموذج لا يعتمد سلامة المركبة فعليًا.</Text>
      </Card>
    </>;
    if (screen === 'task') return <>
      <Heading title="تنفيذ التسليم" sub={activeTask.id + ' · ' + activeTask.customer} action={<Pill tone="orange">COD</Pill>} />
      <Card><Text style={S.taskTitle}>{activeTask.address}</Text><Text style={S.taskMeta}>{activeTask.details}</Text>
        <Button title={scanComplete?'✓ تم المسح (محاكاة)':'▦ محاكاة مسح الباركود'} kind="outline" onPress={() => { setScanComplete(true); notify('مسح محلي تجريبي؛ لا توجد كاميرا فعلية.'); }} />
        <View style={S.inputGroup}><Label text="رمز المستلم التجريبي (4 أرقام على الأقل)" /><TextInput value={recipientPin} onChangeText={setRecipientPin} keyboardType="numeric" placeholder="رمز تحقق تجريبي" style={S.input}/></View>
        <Pressable style={S.checkRow} onPress={() => setProofPhoto(v => !v)}><View style={[S.checkbox,proofPhoto&&S.checkboxOn]}><Text style={S.checkboxMark}>{proofPhoto?'✓':''}</Text></View><View style={{flex:1,gap:4}}><Text style={S.taskTitle}>تمت إضافة صورة إثبات (محاكاة)</Text><Text style={S.taskMeta}>لا يتم التقاط أو رفع صورة حقيقية.</Text></View></Pressable>
        <Button title="تأكيد التسليم التجريبي" onPress={markComplete} /><Button title="تعذر التسليم / تسجيل السبب" kind="danger" onPress={() => go('returns')} />
      </Card>
      <Text style={S.formNote}>حالة التسليم الفعلية لا تكتمل إلا بعد تحقق الخادم من الإسناد والحالة والأدلة والسياسات.</Text>
    </>;
    if (screen === 'pickup') return <>
      <Heading title="استلام من التاجر" sub={activeTask.id + ' · ' + activeTask.customer} />
      <Card><Text style={S.taskMeta}>تحقق من العدد والحالة قبل قبول العهدة. المتوقع: 6 طرود في هذا المثال.</Text><View style={S.inputGroup}><Label text="عدد الطرود" /><TextInput defaultValue="6" keyboardType="numeric" style={S.input}/></View><Button title={scanComplete?'✓ تم المسح':'محاكاة مسح الباركود'} kind="outline" onPress={() => setScanComplete(true)} /><Button title="تأكيد الاستلام المحلي" onPress={markComplete} /></Card>
    </>;
    if (screen === 'transfer') return <>
      <Heading title="نقل بين الفروع" sub={activeTask.id + ' · فرع حدة ← مركز التوزيع'} />
      <Card><Text style={S.taskMeta}>افحص المانيفست والختم وامسح الطرود أو وحدات المناولة؛ وصول المركبة لا يثبت الاستلام المقابل.</Text><View style={S.inputGroup}><Label text="رقم الختم" /><TextInput defaultValue="SEAL-0918" style={S.input}/></View><Button title={scanComplete?'✓ تم مسح المانيفست':'محاكاة مسح المانيفست'} kind="outline" onPress={() => setScanComplete(true)} /><Button title="تأكيد التسليم المحلي" onPress={markComplete} /></Card>
    </>;
    if (screen === 'returns') return <>
      <Heading title="مرتجع أو محاولة فاشلة" sub="اختر السبب واحفظ الحدث مع الدليل في التطبيق الحقيقي." />
      <Card>{['العميل غير متاح','العنوان غير صحيح','رفض الاستلام','مشكلة في الدفع','الطرد تالف','تعذر الوصول'].map(reason=><Pressable key={reason} style={S.checkRow} onPress={() => setReturnReason(reason)}><View style={[S.radio,returnReason===reason&&S.radioOn]}>{returnReason===reason&&<View style={S.radioDot}/>}</View><Text style={S.taskTitle}>{reason}</Text></Pressable>)}<Button title="حفظ الاستثناء التجريبي" kind="danger" onPress={() => { setPendingSync(v => v + 1); go('tasks'); notify('حُفظ السبب محليًا: ' + returnReason + ' — دون تحديث الخادم.'); }} /></Card>
    </>;
    if (screen === 'messages') return <>
      <Heading title="التواصل والدعم" sub="رسائل وتعليمات مرتبطة بالوردية أو المهمة" />
      <Card><View style={S.inputGroup}><Label text="رسالة تجريبية إلى الموزع" /><TextInput value={message} onChangeText={setMessage} multiline placeholder="اكتب رسالتك" style={[S.input,{height:100,textAlignVertical:'top'}]}/></View><Button title="محاكاة إرسال الرسالة" onPress={() => { if(!message.trim()){notify('اكتب رسالة أولًا.');return;} setPendingSync(v=>v+1); setMessage(''); notify('تمت محاكاة الرسالة محليًا فقط.'); }} /></Card>
    </>;
    if (screen === 'sync') return <>
      <Heading title="العمل دون اتصال" sub="قائمة الأحداث وحالة المزامنة" action={<Pill tone={pendingSync?'orange':'green'}>{pendingSync?'معلق':'نظيف'}</Pill>} />
      <Card><View style={S.amountRow}><Text style={S.amountLabel}>أحداث محلية غير مؤكدة</Text><Text style={S.amountValue}>{pendingSync}</Text></View><View style={S.amountRow}><Text style={S.amountLabel}>اتصال الخادم</Text><Text style={S.amountValue}>غير متصل</Text></View><Text style={S.formNote}>التنفيذ الحقيقي يحتاج outbox محليًا، ومفاتيح idempotency، وإقرارًا من الخادم، وإعادة رفع الوسائط وحل التعارضات.</Text><Button title="إضافة حدث محلي تجريبي" kind="outline" onPress={() => setPendingSync(v=>v+1)} /><Button title="محاكاة المزامنة (محلي فقط)" onPress={() => {setPendingSync(0);notify('تغير العدّاد المحلي فقط؛ لم ترسل أي بيانات للخادم.');}} /></Card>
    </>;
    return <>
      <Heading title="الأداء والتسويات" sub="ملخص محلي غير محاسبي" />
      <Card><View style={S.amountRow}><Text style={S.amountLabel}>المهام المنجزة</Text><Text style={S.amountValue}>{completed}</Text></View><View style={S.amountRow}><Text style={S.amountLabel}>المهام المتبقية</Text><Text style={S.amountValue}>{remaining}</Text></View><View style={S.amountRow}><Text style={S.amountLabel}>التحصيل غير المسلّم</Text><Text style={S.amountValue}>{money(codCollected-cashHanded)} ر.ي</Text></View><View style={S.inputGroup}><Label text="اسم أمين الصندوق (للمحاكاة)" /><TextInput value={cashier} onChangeText={setCashier} placeholder="اسم المستلم" style={S.input}/></View><Button kind="success" title="محاكاة محضر تسليم كامل" onPress={() => {if(!cashier.trim()){notify('أدخل اسم أمين الصندوق.');return;}setCashHanded(codCollected);setPendingSync(v=>v+1);go('cash');notify('محضر محلي تجريبي لا يمثل إقرارًا فعليًا.');}} /></Card>
    </>;
  };

  const titles: Record<Screen,string> = {
    today: 'تطبيق السائق', route: 'خط السير', tasks: 'المهام', cash: 'النقدية والتحصيل', more: 'خدمات السائق',
    task: 'تنفيذ التسليم', pickup: 'استلام من التاجر', transfer: 'نقل بين الفروع', returns: 'مرتجع أو فشل',
    vehicle: 'فحص المركبة', sync: 'المزامنة والعمل دون اتصال', messages: 'الدعم والتواصل', performance: 'الأداء والتسويات',
  };
  const isDetail = !(['today','route','tasks','cash','more'] as Screen[]).includes(screen);

  return <SafeAreaView style={S.safe}>
    <StatusBar barStyle="light-content" backgroundColor={C.navy}/>
    <View style={S.root}>
      <View style={S.topbar}>
        {isDetail && <Pressable onPress={() => go('more')} style={S.back}><Text style={S.backText}>→</Text></Pressable>}
        <View style={S.logo}><Text style={S.logoText}>و</Text></View>
        <View style={{ flex: 1 }}><Text style={S.brandSmall}>WASL DRIVER COMPANION</Text><Text style={S.brandTitle}>{titles[screen]}</Text></View>
        <Pressable onPress={() => go('sync')} style={S.syncButton}><Text style={S.syncText}>⟳</Text></Pressable>
      </View>
      {!isDetail && <View style={S.profile}>
        <View style={S.avatar}><Text style={S.avatarText}>م</Text></View>
        <View style={{ flex: 1, gap: 4 }}><Text style={S.greeting}>مرحبًا، محمد 👋</Text><Text style={S.profileMeta}>السائق #DRV-028 · فرع صنعاء</Text></View>
        <Pill tone="green">● متصل</Pill>
      </View>}
      <ScrollView contentContainerStyle={S.scroll} keyboardShouldPersistTaps="handled">
        {!isDetail && <View style={S.shiftBox}><View style={{flex:1,gap:5}}><Text style={S.shiftLabel}>حالة الوردية</Text><Text style={S.shiftValue}>{shiftStarted?'الوردية بدأت · محاكاة':'لم تبدأ الوردية'}</Text></View><Button title={shiftStarted?'إنهاء الوردية':'بدء الوردية'} kind={shiftStarted?'danger':'primary'} onPress={startShift}/></View>}
        <View style={S.notice}><Text style={S.noticeIcon}>ⓘ</Text><Text style={S.noticeText}>نسخة أولية غير متصلة. البيانات والتفاعلات محلية تجريبية؛ لا يوجد GPS أو API أو تحصيل مالي فعلي.</Text></View>
        {screen === 'today' ? renderToday() : screen === 'route' ? renderRoute() : screen === 'tasks' ? renderTasks() : screen === 'cash' ? renderCash() : screen === 'more' ? renderMore() : renderDetail()}
        <Text style={S.bottomDisclaimer}>هذا تطبيق السائق وحده. المكتب والمستودع والأسطول والتوزيع تطبيقات مستقلة أخرى.</Text>
      </ScrollView>
      {!isDetail && <View style={S.tabs}>
        {([{id:'today',icon:'⌂',label:'الرئيسية'},{id:'route',icon:'⌖',label:'المسار'},{id:'tasks',icon:'▤',label:'المهام'},{id:'cash',icon:'﷼',label:'التحصيل'},{id:'more',icon:'☷',label:'المزيد'}] as {id:Screen;icon:string;label:string}[]).map(item=><Pressable key={item.id} onPress={() => go(item.id)} style={[S.tab,screen===item.id&&S.tabActive]}><Text style={[S.tabIcon,screen===item.id&&S.tabIconActive]}>{item.icon}</Text><Text style={[S.tabLabel,screen===item.id&&S.tabLabelActive]}>{item.label}</Text></Pressable>)}
      </View>}
      <View style={S.statusBar}><Text style={S.statusText}>{notice}</Text></View>
    </View>
  </SafeAreaView>;
}
function Label({ text, color = '#AFC0D8' }: { text: string; color?: string }) { return <Text style={[S.label,{color}]}>{text}</Text>; }

const S = StyleSheet.create({
  safe:{flex:1,backgroundColor:C.canvas,direction:'rtl'},
  root:{flex:1,width:'100%',maxWidth:560,alignSelf:'center',backgroundColor:C.canvas,direction:'rtl'},
  topbar:{minHeight:68,paddingHorizontal:15,paddingVertical:13,backgroundColor:C.navy,flexDirection:'row',alignItems:'center',gap:9},
  back:{width:34,height:34,borderRadius:10,backgroundColor:'#1D3451',alignItems:'center',justifyContent:'center'},
  backText:{color:'white',fontSize:18},
  logo:{width:39,height:39,borderRadius:12,backgroundColor:'#5D7FE7',alignItems:'center',justifyContent:'center'},
  logoText:{color:'white',fontSize:22,fontWeight:'900'},
  brandSmall:{color:'#AFC1DB',fontSize:8,letterSpacing:1,textAlign:'right',marginBottom:3},
  brandTitle:{color:'white',fontSize:14,fontWeight:'900',textAlign:'right'},
  syncButton:{width:36,height:36,borderRadius:11,backgroundColor:'#1D3451',borderWidth:1,borderColor:'#35506F',alignItems:'center',justifyContent:'center'},
  syncText:{color:'white',fontSize:20},
  profile:{backgroundColor:C.navy,paddingHorizontal:15,paddingBottom:16,flexDirection:'row',alignItems:'center',gap:10},
  avatar:{width:43,height:43,borderRadius:14,backgroundColor:'#E0E9FF',alignItems:'center',justifyContent:'center'},
  avatarText:{color:'#3156B9',fontSize:18,fontWeight:'900'},
  greeting:{color:'white',fontSize:14,fontWeight:'900',textAlign:'right'},
  profileMeta:{color:'#B7C8DD',fontSize:9,textAlign:'right'},
  scroll:{paddingHorizontal:13,paddingBottom:15,gap:12},
  shiftBox:{marginTop:12,padding:12,borderRadius:13,backgroundColor:'#19314E',flexDirection:'row',alignItems:'center',justifyContent:'space-between',gap:10},
  shiftLabel:{color:'#AFC0D8',fontSize:10,textAlign:'right'},
  shiftValue:{color:'white',fontSize:12,fontWeight:'800',textAlign:'right'},
  notice:{marginTop:2,flexDirection:'row',alignItems:'flex-start',gap:8,backgroundColor:'#EEF3FF',borderColor:'#DAE5FF',borderWidth:1,borderRadius:10,padding:10},
  noticeIcon:{color:'#4C68B6',fontWeight:'900',fontSize:12},
  noticeText:{flex:1,color:'#49609E',fontSize:9,lineHeight:16,textAlign:'right'},
  heading:{paddingHorizontal:2,paddingTop:3,paddingBottom:4,flexDirection:'row',alignItems:'center',justifyContent:'space-between',gap:8},
  h2:{color:C.ink,fontSize:16,fontWeight:'900',textAlign:'right'},
  sub:{color:C.muted,fontSize:10,lineHeight:16,textAlign:'right'},
  card:{backgroundColor:C.white,borderWidth:1,borderColor:C.line,borderRadius:14,padding:12,gap:10},
  pill:{alignSelf:'flex-start',borderRadius:7,paddingHorizontal:8,paddingVertical:6},
  pillText:{fontSize:9,fontWeight:'900'},
  metrics:{flexDirection:'row',flexWrap:'wrap',gap:8},
  metric:{width:'48%',flexGrow:1,flexBasis:'45%',padding:10,gap:5},
  metricTop:{flexDirection:'row',alignItems:'center',justifyContent:'space-between',gap:4},
  metricLabel:{color:C.muted,fontSize:9,textAlign:'right',flex:1},
  metricIcon:{width:27,height:27,borderRadius:9,alignItems:'center',justifyContent:'center'},
  metricValue:{fontSize:21,fontWeight:'900',color:C.ink,textAlign:'right',marginTop:2},
  metricHint:{color:C.muted,fontSize:8,lineHeight:14,textAlign:'right'},
  routeHero:{backgroundColor:C.navy2,borderRadius:12,padding:13,gap:12},
  routeTitle:{color:'white',fontSize:13,fontWeight:'900',textAlign:'right'},
  label:{fontSize:9,textAlign:'right'},
  routeStats:{flexDirection:'row',justifyContent:'space-between',gap:6},
  routeStatLabel:{color:'#BDCEE3',fontSize:9,textAlign:'right'},
  routeStatValue:{color:'white',fontSize:14,fontWeight:'900',textAlign:'right',marginTop:4},
  row:{flexDirection:'row',gap:7,flexWrap:'wrap'},
  button:{minHeight:39,paddingHorizontal:11,paddingVertical:9,borderRadius:9,borderWidth:1,alignItems:'center',justifyContent:'center',flexGrow:1,flexBasis:100},
  buttonText:{fontSize:10,fontWeight:'900',textAlign:'center'},
  taskRow:{flexDirection:'row',alignItems:'flex-start',gap:8,paddingVertical:10,borderTopWidth:1,borderTopColor:'#F0F2F7'},
  step:{width:29,height:29,borderRadius:9,backgroundColor:'#F0F3F9',alignItems:'center',justifyContent:'center'},
  stepDone:{backgroundColor:C.greenSoft},
  stepText:{color:'#5A6C85',fontSize:9,fontWeight:'900'},
  taskTitle:{color:'#26364F',fontSize:10,fontWeight:'900',textAlign:'right'},
  taskMeta:{color:C.muted,fontSize:9,textAlign:'right',lineHeight:15},
  smallAction:{backgroundColor:'#EEF3FB',borderRadius:7,paddingHorizontal:9,paddingVertical:7},
  smallActionText:{color:'#506689',fontSize:9,fontWeight:'900'},
  linkRow:{paddingVertical:10,borderTopWidth:1,borderTopColor:'#F0F2F7',flexDirection:'row',alignItems:'center',gap:9},
  linkIcon:{width:32,height:32,borderRadius:10,textAlign:'center',textAlignVertical:'center',backgroundColor:C.orangeSoft,color:C.orange,overflow:'hidden',fontSize:15},
  chevron:{color:'#9AA6B7',fontSize:21},
  map:{height:145,borderRadius:11,backgroundColor:'#E5ECEE',overflow:'hidden',position:'relative'},
  roadOne:{position:'absolute',width:400,height:58,left:-90,top:20,borderRadius:32,borderWidth:6,borderColor:'white',transform:[{rotate:'-24deg'}]},
  roadTwo:{position:'absolute',width:300,height:57,right:-80,bottom:10,borderRadius:30,borderWidth:6,borderColor:'white',transform:[{rotate:'40deg'}]},
  pathLine:{position:'absolute',width:210,left:75,top:74,borderTopWidth:4,borderColor:C.blue,borderStyle:'dashed',transform:[{rotate:'-24deg'}]},
  pinOne:{position:'absolute',right:52,top:28,width:27,height:27,borderRadius:14,backgroundColor:C.blue,alignItems:'center',justifyContent:'center'},
  pinTwo:{position:'absolute',left:90,bottom:27,width:27,height:27,borderRadius:14,backgroundColor:C.green,alignItems:'center',justifyContent:'center'},
  pinText:{color:'white',fontSize:9,fontWeight:'900'},
  mapNote:{position:'absolute',right:7,bottom:7,backgroundColor:'#FFFFFFE8',padding:5,borderRadius:6,fontSize:8,color:'#64748B'},
  timeline:{flexDirection:'row',gap:9,paddingVertical:10,borderTopWidth:1,borderTopColor:'#F0F2F7',alignItems:'flex-start'},
  dot:{width:11,height:11,marginTop:3,borderRadius:7,borderWidth:3,borderColor:'#8498C7',backgroundColor:'white'},
  dotDone:{borderColor:C.green,backgroundColor:C.green},
  dotNext:{borderColor:C.blue,backgroundColor:'#DCE6FF'},
  timeText:{color:'#8997AA',fontSize:9},
  inlineLink:{color:C.blue,fontWeight:'900',fontSize:10,marginTop:5,textAlign:'right'},
  cashHero:{backgroundColor:'#EFF5FF',borderRadius:11,padding:13,gap:5},
  cashAmount:{fontSize:23,fontWeight:'900',color:'#244FAE',textAlign:'right'},
  amountRow:{flexDirection:'row',alignItems:'center',justifyContent:'space-between',gap:10,borderTopWidth:1,borderTopColor:'#F0F2F7',paddingVertical:10},
  amountLabel:{color:'#65748A',fontSize:10,textAlign:'right',flex:1},
  amountValue:{color:C.ink,fontSize:10,fontWeight:'900',textAlign:'left'},
  bullet:{color:'#5C6B80',fontSize:10,lineHeight:18,textAlign:'right'},
  checkRow:{flexDirection:'row',alignItems:'center',gap:10,paddingVertical:11,borderTopWidth:1,borderTopColor:'#F0F2F7'},
  checkbox:{width:20,height:20,borderWidth:1.5,borderColor:'#C5CEDC',borderRadius:6,alignItems:'center',justifyContent:'center'},
  checkboxOn:{backgroundColor:C.blue,borderColor:C.blue},
  checkboxMark:{color:'white',fontWeight:'900',fontSize:12},
  radio:{width:18,height:18,borderWidth:1.5,borderColor:'#B9C3D2',borderRadius:10,alignItems:'center',justifyContent:'center'},
  radioOn:{borderColor:C.blue},
  radioDot:{width:8,height:8,borderRadius:4,backgroundColor:C.blue},
  inputGroup:{gap:6,marginVertical:4},
  input:{width:'100%',backgroundColor:'white',borderWidth:1,borderColor:'#DDE4EF',borderRadius:9,paddingHorizontal:10,paddingVertical:10,textAlign:'right',color:C.ink,fontSize:11},
  formNote:{color:C.muted,fontSize:9,lineHeight:16,textAlign:'right',marginTop:4},
  tabs:{flexDirection:'row',paddingHorizontal:4,paddingTop:6,paddingBottom:6,backgroundColor:C.white,borderTopWidth:1,borderTopColor:C.line,justifyContent:'space-around'},
  tab:{flex:1,maxWidth:85,borderRadius:9,paddingVertical:6,alignItems:'center',justifyContent:'center',gap:3},
  tabActive:{backgroundColor:'#EFF3FF'},
  tabIcon:{color:'#8A96A8',fontSize:17,lineHeight:21},
  tabIconActive:{color:C.blue},
  tabLabel:{color:'#8A96A8',fontSize:8},
  tabLabelActive:{color:C.blue,fontWeight:'900'},
  bottomDisclaimer:{textAlign:'center',color:'#94A0B1',fontSize:8,lineHeight:14,paddingVertical:8},
  statusBar:{backgroundColor:'#172A43',paddingHorizontal:10,paddingVertical:8},
  statusText:{color:'white',fontSize:9,textAlign:'center',lineHeight:14},
});
