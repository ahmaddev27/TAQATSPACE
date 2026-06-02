/* TAQAT — Freelancer / Member screens */
const { useState:useStateM } = React;

function MemberHome({ lang, go }) {
  const ar = lang==='ar';
  return (
    <div className="stack" style={{gap:24}}>
      <div className="dash-2col">
        {/* subscription status hero */}
        <div className="card card-pad" style={{background:'linear-gradient(135deg, var(--blue-600), var(--blue-800))', color:'#fff', borderColor:'transparent'}}>
          <div className="between"><span className="badge" style={{background:'rgba(255,255,255,.18)', color:'#fff'}}><span className="dot"></span>{ar?'اشتراك نشط':'Active subscription'}</span><Icon name="card" size={22} style={{opacity:.7}} /></div>
          <div style={{marginTop:18}}><div style={{opacity:.8, fontSize:'var(--fs-sm)'}}>{ar?'الخطة الحالية':'Current plan'}</div><div className="h1" style={{color:'#fff', marginTop:4}}>{ar?'اشتراك شهري':'Monthly plan'}</div></div>
          <div className="row" style={{gap:32, marginTop:20, paddingTop:18, borderTop:'1px solid rgba(255,255,255,.18)'}}>
            <div><div style={{opacity:.8, fontSize:'var(--fs-xs)'}}>{ar?'المساحة':'Space'}</div><div style={{fontWeight:600, marginTop:2}}>{ar?'مساحة المنارة':'Al-Manara'}</div></div>
            <div><div style={{opacity:.8, fontSize:'var(--fs-xs)'}}>{ar?'يتجدد في':'Renews on'}</div><div className="tnum ltr" style={{fontWeight:600, marginTop:2}}>2026-06-28</div></div>
          </div>
        </div>
        {/* assigned seat */}
        <div className="card card-pad stack" style={{gap:14}}>
          <h3 className="h3">{ar?'مقعدك المخصّص':'Your seat'}</h3>
          <div className="row" style={{gap:18}}>
            <div className="seat is-selected" style={{width:84, height:84, fontSize:'1.5rem', flex:'none', cursor:'default'}}>A04</div>
            <div className="stack" style={{gap:8}}>
              <div className="row muted" style={{gap:6}}><Icon name="pin" size={15} />{ar?'الطابق الأول · منطقة A':'Floor 1 · Zone A'}</div>
              <div className="row muted" style={{gap:6}}><Icon name="wifi" size={15} />{ar?'باقة احترافي · 100 ميجا':'Pro · 100 Mbps'}</div>
              <span className="badge badge-success" style={{alignSelf:'flex-start'}}><span className="dot"></span>{ar?'متاح اليوم':'Available today'}</span>
            </div>
          </div>
        </div>
      </div>

      <div className="dash-3col">
        <StatTile icon="calendar" label={ar?'أيام الحضور':'Days attended'} value="18" foot={ar?'هذا الشهر':'this month'} />
        <StatTile icon="receipt" amber label={ar?'الفاتورة القادمة':'Next invoice'} value={`${NIS}35`} foot={ar?'تستحق 2026-06-05':'due 2026-06-05'} />
        <StatTile icon="wallet" label={ar?'إجمالي المدفوع':'Total paid'} value={`${NIS}210`} foot={ar?'منذ الانضمام':'since joining'} />
      </div>

      <div className="dash-2col">
        <div className="card card-pad">
          <div className="between" style={{marginBottom:16}}><h3 className="h3">{ar?'أحدث الإشعارات':'Latest notifications'}</h3><a className="link" onClick={()=>go('member:notifications')}>{ar?'عرض الكل':'View all'}</a></div>
          <div className="activity">
            {[['receipt','info',ar?'فاتورة جديدة بانتظار الدفع':'New invoice awaiting payment',ar?'INV-2603 · ₪35':'INV-2603 · ₪35',ar?'قبل ساعة':'1h ago'],['megaphone','warn',ar?'إعلان: صيانة الإنترنت الجمعة':'Notice: Internet maintenance Fri',ar?'٩ – ١١ مساءً':'9–11pm','أمس'],['grid','ok',ar?'تم تأكيد مقعدك A-04':'Your seat A-04 confirmed',ar?'مساحة المنارة':'Al-Manara',ar?'قبل ٣ أيام':'3d ago']].map((a,i)=> (
              <div key={i} className="act-row"><span className={`act-ico ${a[1]}`}><Icon name={a[0]} size={16} /></span><div className="grow"><div style={{fontSize:'var(--fs-sm)', fontWeight:500}}>{a[2]}</div><div className="muted-3" style={{fontSize:'var(--fs-xs)'}}>{a[3]}</div></div><span className="muted-3" style={{fontSize:'var(--fs-xs)'}}>{a[4]}</span></div>
            ))}
          </div>
        </div>
        <div className="card card-pad stack" style={{gap:14}}>
          <h3 className="h3">{ar?'إجراءات سريعة':'Quick actions'}</h3>
          <Button variant="secondary" block icon="receipt" onClick={()=>go('member:invoices')}>{ar?'دفع الفاتورة القادمة':'Pay next invoice'}</Button>
          <Button variant="secondary" block icon="refresh" onClick={()=>go('member:subscription')}>{ar?'تجديد الاشتراك':'Renew subscription'}</Button>
          <Button variant="secondary" block icon="search" onClick={()=>go('member:explore')}>{ar?'استكشاف مساحات أخرى':'Explore other spaces'}</Button>
        </div>
      </div>
    </div>
  );
}

function MemberSubscription({ lang, toast }) {
  const ar = lang==='ar';
  const [cancel, setCancel] = useStateM(false);
  return (
    <div className="stack" style={{gap:18}}>
      <div className="card card-pad">
        <div className="between wrap" style={{gap:16}}>
          <div className="row" style={{gap:16}}>
            <span className="st-ico amber" style={{width:52, height:52, borderRadius:'var(--r-tile)'}}><Icon name="card" size={24} /></span>
            <div><div className="h3">{ar?'اشتراك شهري':'Monthly plan'}</div><div className="muted">{ar?'مساحة المنارة · غزة':'Al-Manara · Gaza'}</div></div>
          </div>
          <StatusBadge status="active" lang={lang} />
        </div>
        <div className="divider" style={{margin:'20px 0'}}></div>
        <div className="dash-3col">
          {[[ar?'السعر':'Price',`${NIS}35 ${ar?'/ شهر':'/mo'}`],[ar?'تاريخ التجديد':'Renews on','2026-06-28'],[ar?'طريقة الدفع':'Payment','••• 4291']].map((r,i)=>
            <div key={i}><div className="muted-3" style={{fontSize:'var(--fs-sm)'}}>{r[0]}</div><div className="tnum" style={{fontWeight:600, fontSize:'var(--fs-lg)', marginTop:4}}>{r[1]}</div></div>)}
        </div>
        <div className="row wrap" style={{gap:10, marginTop:20}}>
          <Button variant="primary" icon="refresh" onClick={()=>toast({tone:'ok',title:ar?'تم تجديد الاشتراك':'Subscription renewed'})}>{ar?'تجديد الآن':'Renew now'}</Button>
          <Button variant="secondary" icon="card">{ar?'تغيير الخطة':'Change plan'}</Button>
          <Button variant="ghost" onClick={()=>setCancel(true)}>{ar?'إلغاء الاشتراك':'Cancel subscription'}</Button>
        </div>
      </div>

      <h3 className="h3" style={{marginTop:6}}>{ar?'الخطط المتاحة':'Available plans'}</h3>
      <div className="plan-grid">{PLANS.map(p=> <PlanCard key={p.id} p={p} lang={lang} t={T[lang]} onChoose={()=>toast({tone:'info',title:ar?`تم اختيار خطة ${p.name}`:`Selected ${p.nameEn} plan`})} />)}</div>

      {cancel && <Modal title={ar?'تأكيد إلغاء الاشتراك':'Cancel subscription?'} icon="alert" onClose={()=>setCancel(false)}
        footer={<><Button variant="ghost" onClick={()=>setCancel(false)}>{ar?'تراجع':'Keep plan'}</Button><Button variant="danger" onClick={()=>{setCancel(false);toast({tone:'info',title:ar?'تم إلغاء الاشتراك':'Subscription cancelled'});}}>{ar?'تأكيد الإلغاء':'Confirm cancel'}</Button></>}>
        <p className="muted" style={{lineHeight:1.7}}>{ar?'سيبقى اشتراكك فعّالاً حتى 2026-06-28، وبعدها سيتوقّف الوصول إلى مقعدك A-04. هل أنت متأكد؟':'Your plan stays active until 2026-06-28, after which access to seat A-04 stops. Are you sure?'}</p>
      </Modal>}
    </div>
  );
}

function MemberInvoices({ lang, toast }) {
  const ar = lang==='ar';
  const mine = INVOICES.slice(0,5);
  return (
    <div className="table-wrap">
      <table className="tbl">
        <thead><tr><th>{ar?'رقم الفاتورة':'Invoice'}</th><th>{ar?'الوصف':'Description'}</th><th>{ar?'التاريخ':'Date'}</th><th>{ar?'المبلغ':'Amount'}</th><th>{ar?'الحالة':'Status'}</th><th></th></tr></thead>
        <tbody>
          {mine.map(inv=> (
            <tr key={inv.id}>
              <td className="cell-num" style={{fontWeight:600}}>{inv.id}</td>
              <td className="muted">{inv.plan}</td>
              <td className="cell-num ltr">{inv.date}</td>
              <td className="cell-num" style={{fontWeight:600}}>{NIS}{inv.amount}</td>
              <td><StatusBadge status={inv.status} lang={lang} /></td>
              <td><div className="row-actions">
                {inv.status!=='paid' && <Button variant="primary" size="sm" onClick={()=>toast({tone:'ok',title:ar?'تم الدفع بنجاح':'Payment successful'})}>{ar?'ادفع':'Pay'}</Button>}
                <button className="btn btn-ghost btn-sm"><Icon name="download" size={15} />PDF</button>
              </div></td>
            </tr>
          ))}
        </tbody>
      </table>
      <div className="table-foot"><span>{ar?`${mine.length} فواتير`:`${mine.length} invoices`}</span><span className="tnum">{ar?'إجمالي مدفوع':'Total paid'}: {NIS}210</span></div>
    </div>
  );
}

function MemberNotifications({ lang }) {
  const ar = lang==='ar';
  const items = [
    {ico:'receipt', tone:'info', unread:true, t:ar?'فاتورة جديدة بانتظار الدفع':'New invoice awaiting payment', d:ar?'فاتورة INV-2603 بقيمة ₪35 تستحق 2026-06-05':'Invoice INV-2603 of ₪35 due 2026-06-05', time:ar?'قبل ساعة':'1h ago'},
    {ico:'megaphone', tone:'warn', unread:true, t:ar?'صيانة مجدولة للإنترنت':'Scheduled internet maintenance', d:ar?'الجمعة من ٩ إلى ١١ مساءً في مساحة المنارة':'Friday 9–11pm at Al-Manara', time:ar?'أمس':'Yesterday'},
    {ico:'grid', tone:'ok', unread:false, t:ar?'تم تأكيد مقعدك':'Your seat is confirmed', d:ar?'المقعد A-04 · الطابق الأول':'Seat A-04 · Floor 1', time:ar?'قبل ٣ أيام':'3d ago'},
    {ico:'checkCircle', tone:'ok', unread:false, t:ar?'تم استلام دفعتك':'Payment received', d:ar?'فاتورة INV-2600 · ₪35':'Invoice INV-2600 · ₪35', time:ar?'قبل أسبوع':'1w ago'},
  ];
  return (
    <div className="stack" style={{gap:12}}>
      <div className="between"><span className="muted" style={{fontSize:'var(--fs-sm)'}}>{ar?'إشعاران غير مقروءين':'2 unread'}</span><Button variant="ghost" size="sm" icon="check">{ar?'تعليم الكل كمقروء':'Mark all read'}</Button></div>
      {items.map((n,i)=> (
        <div key={i} className={`notif-item ${n.unread?'unread':''}`}>
          <span className={`act-ico ${n.tone}`}><Icon name={n.ico} size={16} /></span>
          <div className="grow"><div className="between"><div style={{fontWeight:600, fontSize:'var(--fs-sm)'}}>{n.t}</div><span className="muted-3" style={{fontSize:'var(--fs-xs)'}}>{n.time}</span></div><div className="muted" style={{fontSize:'var(--fs-sm)', marginTop:3}}>{n.d}</div></div>
          {n.unread && <span className="notif-dot"></span>}
        </div>
      ))}
    </div>
  );
}

function MemberProfile({ lang, toast }) {
  const ar = lang==='ar';
  return (
    <div style={{maxWidth:720}}>
      <div className="card" style={{overflow:'hidden'}}>
        <div className="profile-cover"></div>
        <div className="card-pad" style={{paddingTop:0}}>
          <div className="profile-head" style={{marginTop:-32}}>
            <span className="avatar lg round" style={{width:80, height:80, fontSize:'1.6rem', border:'4px solid var(--surface)'}}>أ</span>
            <div style={{paddingTop:34}}><div className="h3">{ar?'أحمد الفرا':'Ahmad Al-Farra'}</div><div className="muted">{ar?'مطوّر واجهات':'Frontend developer'}</div></div>
          </div>
          <div className="divider" style={{margin:'24px 0'}}></div>
          <div className="grid2">
            <Field label={ar?'الاسم الكامل':'Full name'}><Input defaultValue={ar?'أحمد الفرا':'Ahmad Al-Farra'} /></Field>
            <Field label={ar?'التخصص':'Specialty'}><Input defaultValue={ar?'مطوّر واجهات':'Frontend developer'} /></Field>
            <Field label={ar?'البريد الإلكتروني':'Email'}><Input className="ltr" defaultValue="ahmad.f@mail.ps" /></Field>
            <Field label={ar?'رقم الجوال':'Phone'}><Input className="ltr" defaultValue="+970 599 123 456" /></Field>
          </div>
          <div className="divider" style={{margin:'24px 0'}}></div>
          <div className="between wrap" style={{gap:12}}>
            <Button variant="ghost" icon="lock">{ar?'تغيير كلمة المرور':'Change password'}</Button>
            <div className="row" style={{gap:10}}><Button variant="secondary">{ar?'إلغاء':'Cancel'}</Button><Button variant="primary" icon="check" onClick={()=>toast({tone:'ok',title:ar?'تم حفظ التغييرات':'Changes saved'})}>{ar?'حفظ':'Save'}</Button></div>
          </div>
        </div>
      </div>
    </div>
  );
}

Object.assign(window, { MemberHome, MemberSubscription, MemberInvoices, MemberNotifications, MemberProfile });
