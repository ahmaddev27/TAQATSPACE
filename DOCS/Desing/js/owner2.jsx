/* TAQAT — Owner screens part 2: Invoices, Seats, Requests, Reports, Packages */
const { useState:useStateO, useEffect:useEffectO } = React;

/* seat generator */
function makeSeats(prefix, n, pattern) {
  return Array.from({length:n}, (_,i)=> {
    const num = String(i+1).padStart(2,'0');
    let state = 'available';
    const p = pattern[i%pattern.length];
    if (p) state = p;
    return { id:`${prefix}-${num}`, label:`${prefix}${num}`, state };
  });
}

/* =========================================================== INVOICES */
function OwnerInvoices({ lang, toast }) {
  const ar = lang==='ar';
  const [tab, setTab] = useStateO('all');
  const [sort, setSort] = useStateO({ key:'id', dir:'desc' });
  const [page, setPage] = useStateO(1);
  const [issue, setIssue] = useStateO(false);
  const [loading, setLoading] = useStateO(false);
  const pageSize = 5;
  let rows = INVOICES.filter(i=> tab==='all'||i.status===tab);
  rows = sortRows(rows, sort, { amount:i=>i.amount });
  const pages = Math.max(1, Math.ceil(rows.length/pageSize));
  const curPage = Math.min(page, pages);
  const pageRows = rows.slice((curPage-1)*pageSize, curPage*pageSize);
  const totals = { paid: INVOICES.filter(i=>i.status==='paid').reduce((a,b)=>a+b.amount,0), pending: INVOICES.filter(i=>i.status==='pending').reduce((a,b)=>a+b.amount,0), overdue: INVOICES.filter(i=>i.status==='overdue').reduce((a,b)=>a+b.amount,0) };
  const reload = ()=>{ setLoading(true); setTimeout(()=>setLoading(false), 1400); };
  return (
    <div className="dt" style={{gap:18}}>
      <div className="grid-stats" style={{gridTemplateColumns:'repeat(3,1fr)'}}>
        <StatTile icon="checkCircle" label={ar?'محصّل هذا الشهر':'Collected this month'} value={`${NIS}${totals.paid}`} foot={ar?'٣ فواتير مدفوعة':'3 paid'} />
        <StatTile icon="clock" amber label={ar?'قيد الانتظار':'Pending'} value={`${NIS}${totals.pending}`} foot={ar?'فاتورتان':'2 invoices'} />
        <StatTile icon="alert" label={ar?'متأخرة':'Overdue'} value={`${NIS}${totals.overdue}`} foot={ar?'فاتورة واحدة':'1 invoice'} />
      </div>

      <div className="dt-toolbar">
        <Tabs items={[{id:'all',label:ar?'الكل':'All'},{id:'paid',label:ar?'مدفوعة':'Paid'},{id:'pending',label:ar?'قيد الانتظار':'Pending'},{id:'overdue',label:ar?'متأخرة':'Overdue'}]} value={tab} onChange={v=>{setTab(v);setPage(1);}} />
        <div className="spacer"></div>
        <span className="dt-count">{ar?'العدد:':'Total:'} <strong>{rows.length}</strong></span>
        <div className="dt-tools">
          <Button variant="secondary" size="sm" icon="refresh" onClick={reload}>{ar?'تحديث':'Refresh'}</Button>
          <Button variant="secondary" size="sm" icon="download">{ar?'تصدير PDF':'Export PDF'}</Button>
          <Button variant="primary" size="sm" icon="plus" onClick={()=>setIssue(true)}>{ar?'إصدار فاتورة':'New invoice'}</Button>
        </div>
      </div>

      <div className="table-wrap">
        <div className="tbl-scroll">
        <table className="tbl">
          <thead><tr>
            <Th id="id" sort={sort} setSort={setSort}>{ar?'رقم الفاتورة':'Invoice'}</Th>
            <Th id="member" sort={sort} setSort={setSort}>{ar?'العضو':'Member'}</Th>
            <th>{ar?'الوصف':'Description'}</th>
            <Th id="due" sort={sort} setSort={setSort} num>{ar?'تاريخ الاستحقاق':'Due date'}</Th>
            <Th id="amount" sort={sort} setSort={setSort} num>{ar?'المبلغ':'Amount'}</Th>
            <Th id="status" sort={sort} setSort={setSort}>{ar?'الحالة':'Status'}</Th>
            <th></th>
          </tr></thead>
          <tbody>
            {loading ? Array.from({length:5}).map((_,i)=> <tr key={i}>{Array.from({length:7}).map((_,j)=> <td key={j}><div className="skel" style={{height:16, width: j===0?70:j===6?40:'80%'}}></div></td>)}</tr>)
            : pageRows.map(inv=> (
              <tr key={inv.id}>
                <td className="cell-num" style={{fontWeight:600}}>{inv.id}</td>
                <td>{inv.member}</td>
                <td className="muted">{inv.plan}</td>
                <td className="cell-num ltr">{inv.due}</td>
                <td className="cell-num" style={{fontWeight:600}}>{NIS}{inv.amount}</td>
                <td><StatusBadge status={inv.status} lang={lang} /></td>
                <td><div className="row-actions">
                  {inv.status!=='paid' && <button className="btn btn-ghost btn-sm" onClick={()=>toast({tone:'ok',title:ar?'تم إرسال التذكير':'Reminder sent'})}><Icon name="send" size={15} />{ar?'تذكير':'Remind'}</button>}
                  <button className="icon-btn"><Icon name="printer" /></button>
                  <button className="icon-btn"><Icon name="more" /></button>
                </div></td>
              </tr>
            ))}
          </tbody>
        </table>
        </div>
        <div className="table-foot">
          <span>{ar?`عرض ${rows.length?(curPage-1)*pageSize+1:0}–${Math.min(curPage*pageSize,rows.length)} من `:`Showing ${rows.length?(curPage-1)*pageSize+1:0}–${Math.min(curPage*pageSize,rows.length)} of `}<strong style={{color:'var(--text-2)'}}>{rows.length}</strong></span>
          <Pager page={curPage} pages={pages} setPage={setPage} lang={lang} />
        </div>
      </div>

      {issue && <Modal title={ar?'إصدار فاتورة جديدة':'Issue new invoice'} icon="receipt" onClose={()=>setIssue(false)}
        footer={<><Button variant="ghost" onClick={()=>setIssue(false)}>{ar?'إلغاء':'Cancel'}</Button><Button variant="primary" icon="send" onClick={()=>{setIssue(false);toast({tone:'ok',title:ar?'تم إصدار الفاتورة':'Invoice issued',body:ar?'INV-2606 · أحمد الفرا':'INV-2606 · Ahmad'});}}>{ar?'إصدار وإرسال':'Issue & send'}</Button></>}>
        <div className="stack" style={{gap:16}}>
          <Field label={ar?'العضو':'Member'} req><Select>{MEMBERS.map(m=> <option key={m.id}>{ar?m.name:m.nameEn}</option>)}</Select></Field>
          <Field label={ar?'البند':'Item'} req><Select><option>{ar?'اشتراك شهري':'Monthly subscription'}</option><option>{ar?'اشتراك ربع سنوي':'Quarterly'}</option><option>{ar?'حجز قاعة اجتماعات':'Meeting room'}</option></Select></Field>
          <div className="grid2">
            <Field label={ar?'المبلغ':'Amount'} req>
              <div className="input-group"><span className="ig-addon lead">{NIS}</span><input className="input ltr tnum" defaultValue="35" /></div>
            </Field>
            <Field label={ar?'تاريخ الاستحقاق':'Due date'}><Input className="ltr" defaultValue="2026-06-08" /></Field>
          </div>
        </div>
      </Modal>}
    </div>
  );
}

/* =========================================================== SEATS */
function OwnerSeats({ lang, toast }) {
  const ar = lang==='ar';
  const floorA = makeSeats('A', 16, ['', 'occupied','', '', 'occupied','reserved','', '']);
  const floorB = makeSeats('B', 16, ['occupied','', '', 'reserved','', '', 'disabled','']);
  const [seats, setSeats] = useStateO([...floorA, ...floorB]);
  const [sel, setSel] = useStateO(null);
  const stats = { avail: seats.filter(s=>s.state==='available').length, occ: seats.filter(s=>s.state==='occupied').length, res: seats.filter(s=>s.state==='reserved').length };
  const selSeat = seats.find(s=>s.id===sel);
  const assign = (mid)=> {
    setSeats(seats.map(s=> s.id===sel ? {...s, state:'occupied'} : s));
    setSel(null);
    toast({tone:'ok', title:ar?'تم تعيين المقعد':'Seat assigned', body: `${selSeat.label} · ${ar?MEMBERS.find(m=>m.id===mid).name:MEMBERS.find(m=>m.id===mid).nameEn}`});
  };
  return (
    <div className="seat-page">
      <div className="seat-floor">
        <div className="between" style={{marginBottom:18}}>
          <Segmented items={[{id:'1',label:ar?'الطابق الأول':'Floor 1'},{id:'2',label:ar?'الطابق الثاني':'Floor 2'}]} value="1" onChange={()=>{}} />
          <div className="row" style={{gap:8}}><span className="badge badge-success"><span className="dot"></span>{stats.avail} {ar?'متاح':'free'}</span><span className="badge badge-neutral">{stats.occ} {ar?'مشغول':'taken'}</span></div>
        </div>
        <div className="floor-zone">{ar?'منطقة المكاتب المفتوحة — A':'OPEN DESKS — ZONE A'}</div>
        <SeatMap seats={seats.slice(0,16)} selected={sel} onSelect={setSel} cols={8} />
        <div className="floor-zone" style={{marginTop:22}}>{ar?'المكاتب الثابتة — B':'FIXED DESKS — ZONE B'}</div>
        <SeatMap seats={seats.slice(16)} selected={sel} onSelect={setSel} cols={8} />
        <div className="divider" style={{margin:'22px 0 16px'}}></div>
        <SeatLegend lang={lang} />
      </div>

      <div className="stack" style={{gap:16}}>
        <div className="card card-pad stack" style={{gap:14}}>
          <h3 className="h3">{ar?'تعيين مقعد':'Assign seat'}</h3>
          {!selSeat ? <div className="empty-state" style={{padding:'28px 12px'}}><div className="st-ico amber" style={{width:44,height:44}}><Icon name="grid" /></div><div style={{fontSize:'var(--fs-sm)'}}>{ar?'اختر مقعداً متاحاً من الخريطة لتعيينه لعضو':'Pick an available seat from the map to assign it'}</div></div>
          : <div className="stack" style={{gap:14}}>
              <div className="between"><span className="muted">{ar?'المقعد المختار':'Selected seat'}</span><span className="badge badge-info tnum" style={{fontSize:'var(--fs-sm)'}}>{selSeat.label}</span></div>
              <Field label={ar?'العضو':'Member'}><Select id="assign-sel">{MEMBERS.filter(m=>m.status==='active').map(m=> <option key={m.id} value={m.id}>{ar?m.name:m.nameEn}</option>)}</Select></Field>
              <Button variant="primary" block icon="check" onClick={()=>assign(document.getElementById('assign-sel').value)}>{ar?'تأكيد التعيين':'Confirm assignment'}</Button>
              <Button variant="ghost" block onClick={()=>setSel(null)}>{ar?'إلغاء':'Cancel'}</Button>
            </div>}
        </div>
        <div className="card card-pad stack" style={{gap:12}}>
          <div className="between"><h3 className="h3">{ar?'الحضور اليوم':'Today’s attendance'}</h3><span className="tnum muted">{stats.occ}/{seats.length}</span></div>
          <div className="bar-track"><div className="bar-fill" style={{width:`${(stats.occ/seats.length)*100}%`}}></div></div>
          <div className="stack" style={{gap:8, marginTop:4}}>
            {MEMBERS.filter(m=>m.seat!=='—').slice(0,4).map(m=> <div key={m.id} className="row" style={{gap:10}}><Avatar initial={m.avatar} size="sm" round /><span className="grow" style={{fontSize:'var(--fs-sm)'}}>{ar?m.name:m.nameEn}</span><span className="badge badge-success" style={{height:20}}><span className="dot"></span>{m.seat}</span></div>)}
          </div>
        </div>
      </div>
    </div>
  );
}

/* =========================================================== REQUESTS */
function OwnerRequests({ lang, toast }) {
  const ar = lang==='ar';
  const [list, setList] = useStateO(REQUESTS);
  const [assignFor, setAssignFor] = useStateO(null);
  const act = (id, ok)=> { setList(list.filter(r=>r.id!==id)); setAssignFor(null); toast({tone: ok?'ok':'info', title: ok?(ar?'تم قبول الطلب وتعيين المقعد':'Approved & seat assigned'):(ar?'تم رفض الطلب':'Request rejected')}); };
  if (list.length===0) return (
    <div className="empty-state" style={{minHeight:340}}><EmptyArt /><div><div className="h3">{ar?'لا توجد طلبات حالياً':'No pending requests'}</div><div style={{fontSize:'var(--fs-sm)', marginTop:4}}>{ar?'ستظهر طلبات الحجز الجديدة هنا':'New booking requests will appear here'}</div></div></div>
  );
  return (
    <div className="stack" style={{gap:14}}>
      <p className="muted" style={{fontSize:'var(--fs-sm)'}}>{ar?`${list.length} طلبات بانتظار ردّك`:`${list.length} requests awaiting your reply`}</p>
      {list.map(r=> (
        <div key={r.id} className="card card-pad">
          <div className="between wrap" style={{gap:16}}>
            <div className="row" style={{gap:14}}>
              <Avatar initial={r.avatar} size="lg" round />
              <div><div className="h3" style={{fontSize:'var(--fs-lg)'}}>{r.name}</div><div className="muted">{r.spec}</div>
                <div className="row" style={{gap:14, marginTop:8}}><span className="row muted-3" style={{gap:5, fontSize:'var(--fs-sm)'}}><Icon name="calendar" size={15} /><span className="tnum ltr">{r.date}</span></span><span className="row muted-3" style={{gap:5, fontSize:'var(--fs-sm)'}}><Icon name="grid" size={15} />{r.seatPref}</span></div>
              </div>
            </div>
            <div className="row" style={{gap:10}}>
              <Button variant="secondary" icon="x" onClick={()=>act(r.id,false)}>{ar?'رفض':'Reject'}</Button>
              <Button variant="primary" icon="check" onClick={()=>setAssignFor(r)}>{ar?'قبول وتعيين مقعد':'Approve & assign'}</Button>
            </div>
          </div>
        </div>
      ))}
      {assignFor && <Modal title={ar?`تعيين مقعد لـ ${assignFor.name}`:`Assign seat to ${assignFor.name}`} icon="grid" onClose={()=>setAssignFor(null)}
        footer={<><Button variant="ghost" onClick={()=>setAssignFor(null)}>{ar?'إلغاء':'Cancel'}</Button><Button variant="primary" icon="check" onClick={()=>act(assignFor.id,true)}>{ar?'تأكيد':'Confirm'}</Button></>}>
        <p className="muted" style={{marginBottom:16, fontSize:'var(--fs-sm)'}}>{ar?'اختر مقعداً متاحاً:':'Pick an available seat:'}</p>
        <SeatMap seats={makeSeats('A',16,['','occupied','','','reserved','','occupied',''])} selected="A-03" onSelect={()=>{}} cols={8} />
      </Modal>}
    </div>
  );
}

/* =========================================================== REPORTS */
function OwnerReports({ lang }) {
  const ar = lang==='ar';
  const top = MEMBERS.slice(0,5);
  return (
    <div className="stack" style={{gap:18}}>
      <div className="dash-2col">
        <div className="card card-pad"><div className="between" style={{marginBottom:16}}><h3 className="h3">{ar?'الإيراد الشهري':'Monthly revenue'}</h3><span className="badge badge-success"><Icon name="trend" size={13} />+9.5%</span></div><LineChart data={REVENUE_SERIES} lang={lang} /></div>
        <div className="card card-pad"><h3 className="h3" style={{marginBottom:16}}>{ar?'معدّل الإشغال':'Occupancy rate'}</h3><div style={{display:'grid',placeItems:'center',padding:'10px 0'}}><Gauge value={84} size={170} label={ar?'هذا الشهر':'this month'} /></div></div>
      </div>
      <div className="card card-pad">
        <div className="between" style={{marginBottom:8}}><h3 className="h3">{ar?'أكثر الأعضاء نشاطاً':'Top members'}</h3><Button variant="secondary" size="sm" icon="download">{ar?'تصدير Excel':'Export Excel'}</Button></div>
        {top.map((m,i)=> (
          <div key={m.id} className="report-row">
            <span className={`rank ${i===0?'top':''}`}>{i+1}</span>
            <Avatar initial={m.avatar} size="sm" round /><div className="grow"><div style={{fontWeight:500, fontSize:'var(--fs-sm)'}}>{ar?m.name:m.nameEn}</div><div className="muted-3" style={{fontSize:'var(--fs-xs)'}}>{m.spec}</div></div>
            <div className="grow" style={{maxWidth:160}}><div className="bar-track"><div className={`bar-fill ${i===0?'amber':''}`} style={{width:`${100-i*16}%`}}></div></div></div>
            <span className="tnum muted" style={{fontSize:'var(--fs-sm)', minWidth:60, textAlign:'end'}}>{22-i*3} {ar?'يوم':'days'}</span>
          </div>
        ))}
      </div>
    </div>
  );
}

/* =========================================================== PACKAGES */
function OwnerPackages({ lang, toast }) {
  const ar = lang==='ar';
  return (
    <div className="stack" style={{gap:18}}>
      <div className="pkg-grid">
        {PACKAGES.map(p=> (
          <div key={p.id} className="card card-pad stack" style={{gap:14}}>
            <div className="between"><div className="row" style={{gap:10}}><span className="st-ico"><Icon name="wifi" /></span><h3 className="h3" style={{fontSize:'var(--fs-lg)'}}>{p.name}</h3></div><button className="icon-btn"><Icon name="more" size={18} /></button></div>
            <div className="row" style={{gap:6, alignItems:'baseline'}}><span className="tnum" style={{fontSize:'1.8rem', fontWeight:700}}>{p.speed}</span></div>
            <div className="stack" style={{gap:8}}>
              <div className="between"><span className="muted" style={{fontSize:'var(--fs-sm)'}}>{ar?'حد البيانات':'Data cap'}</span><span style={{fontSize:'var(--fs-sm)', fontWeight:500}}>{p.cap}</span></div>
              <div className="between"><span className="muted" style={{fontSize:'var(--fs-sm)'}}>{ar?'سعر إضافي':'Add-on price'}</span><span className="tnum" style={{fontWeight:600}}>{p.price?`${NIS}${p.price}`:(ar?'مشمول':'Included')}</span></div>
              <div className="between"><span className="muted" style={{fontSize:'var(--fs-sm)'}}>{ar?'الأعضاء':'Members'}</span><span className="tnum" style={{fontWeight:500}}>{p.members}</span></div>
            </div>
            <div className="bar-track"><div className="bar-fill" style={{width:`${(p.members/48)*100}%`}}></div></div>
            <Button variant="secondary" block icon="edit">{ar?'تعديل الباقة':'Edit package'}</Button>
          </div>
        ))}
      </div>
      <Button variant="primary" icon="plus" onClick={()=>toast({tone:'info',title:ar?'إنشاء باقة جديدة':'New package'})} style={{alignSelf:'flex-start'}}>{ar?'إضافة باقة إنترنت':'Add internet package'}</Button>
    </div>
  );
}

Object.assign(window, { makeSeats, OwnerInvoices, OwnerSeats, OwnerRequests, OwnerReports, OwnerPackages });
