/* TAQAT — dashboard shell + Owner screens */
const { useState:useStateD } = React;

const NAV = {
  owner: {
    brandRole: { ar:'مساحة المنارة', en:'Al-Manara Space' },
    roleLabel: { ar:'صاحب مساحة', en:'Space owner' },
    avatar:'م',
    groups: [
      { items:[ {id:'owner:dashboard', icon:'home', ar:'لوحة التحكم', en:'Dashboard'} ] },
      { title:{ar:'الإدارة',en:'Manage'}, items:[
        {id:'owner:members', icon:'users', ar:'المشتركون', en:'Members'},
        {id:'owner:seats', icon:'grid', ar:'المقاعد', en:'Seats'},
        {id:'owner:requests', icon:'inbox', ar:'طلبات الحجز', en:'Booking requests', badge:3},
        {id:'owner:invoices', icon:'receipt', ar:'الفواتير', en:'Invoices'},
        {id:'owner:packages', icon:'wifi', ar:'باقات الإنترنت', en:'Internet packages'},
      ]},
      { title:{ar:'التواصل',en:'Engage'}, items:[
        {id:'owner:messages', icon:'chat', ar:'الرسائل', en:'Messages'},
        {id:'owner:announce', icon:'megaphone', ar:'الإعلانات', en:'Announcements'},
        {id:'owner:reports', icon:'chart', ar:'التقارير', en:'Reports'},
        {id:'owner:settings', icon:'settings', ar:'الإعدادات', en:'Settings'},
      ]},
    ],
  },
  member: {
    brandRole: { ar:'أحمد الفرا', en:'Ahmad Al-Farra' },
    roleLabel: { ar:'عامل مستقل', en:'Freelancer' },
    avatar:'أ',
    groups: [
      { items:[
        {id:'member:home', icon:'home', ar:'الرئيسية', en:'Home'},
        {id:'member:explore', icon:'search', ar:'استكشف المساحات', en:'Explore'},
      ]},
      { title:{ar:'حسابي',en:'My account'}, items:[
        {id:'member:subscription', icon:'card', ar:'اشتراكي وحجوزاتي', en:'Subscription'},
        {id:'member:invoices', icon:'receipt', ar:'فواتيري', en:'My invoices'},
        {id:'member:notifications', icon:'bell', ar:'الإشعارات', en:'Notifications', badge:2},
        {id:'member:profile', icon:'user', ar:'الملف الشخصي', en:'Profile'},
      ]},
    ],
  },
  admin: {
    brandRole: { ar:'لوحة الأدمن', en:'Admin console' },
    roleLabel: { ar:'مشرف المنصة', en:'Super admin' },
    avatar:'A',
    groups: [
      { items:[ {id:'admin:analytics', icon:'chart', ar:'إحصائيات المنصة', en:'Analytics'} ] },
      { title:{ar:'الإدارة',en:'Management'}, items:[
        {id:'admin:workspaces', icon:'building', ar:'إدارة المساحات', en:'Workspaces'},
        {id:'admin:users', icon:'users', ar:'إدارة المستخدمين', en:'Users'},
        {id:'admin:finance', icon:'wallet', ar:'المالية والعمولات', en:'Finance'},
      ]},
      { title:{ar:'النظام',en:'System'}, items:[
        {id:'admin:announce', icon:'megaphone', ar:'الإعلانات العامة', en:'Announcements'},
        {id:'admin:settings', icon:'settings', ar:'إعدادات المنصة', en:'Settings'},
      ]},
    ],
  },
};

function DashShell({ role, active, go, lang, t, theme, setTheme, setLang, title, subtitle, actions, search, children }) {
  const cfg = NAV[role];
  const [collapsed, setCollapsed] = useStateD(false);
  const [navOpen, setNavOpen] = useStateD(false);
  const onMenu = ()=>{ if (window.innerWidth <= 860) { setCollapsed(false); setNavOpen(o=>!o); } else { setCollapsed(c=>!c); } };
  const navGo = (id)=>{ go(id); setNavOpen(false); };
  return (
    <div className="dash" style={{gridTemplateColumns: collapsed?'72px 1fr':'var(--nav-w) 1fr'}}>
      {navOpen && <div className="nav-backdrop" onClick={()=>setNavOpen(false)}></div>}
      <aside className={`dash-nav ${collapsed?'is-collapsed':''} ${navOpen?'mobile-open':''}`}>
        <div className="dash-brand">
          <a className="logo" onClick={()=>go('home')} style={{cursor:'pointer'}}>
            {collapsed ? <span className="tile-glyph" style={{fontSize:24}}>T</span> : <img src="assets/logo.png" alt="TAQAT" />}
          </a>
        </div>
        <div className="dash-nav-scroll">
          {cfg.groups.map((g,gi)=> (
            <div key={gi}>
              {g.title && !collapsed && <div className="nav-section">{lang==='ar'?g.title.ar:g.title.en}</div>}
              {g.title && collapsed && <div className="nav-sep"></div>}
              {g.items.map(it=> (
                <a key={it.id} className={`nav-item ${active===it.id?'active':''}`} onClick={()=>navGo(it.id)} title={lang==='ar'?it.ar:it.en}>
                  <Icon name={it.icon} />
                  {!collapsed && <span>{lang==='ar'?it.ar:it.en}</span>}
                  {!collapsed && it.badge && <span className="nav-badge tnum">{it.badge}</span>}
                </a>
              ))}
            </div>
          ))}
        </div>
        <div className="dash-nav-foot">
          <a className="nav-item" onClick={()=>navGo('login')}><Icon name="logout" />{!collapsed && <span>{lang==='ar'?'تسجيل الخروج':'Log out'}</span>}</a>
        </div>
      </aside>

      <div className="dash-main">
        <div className="topbar">
          <button className="icon-btn" onClick={onMenu} aria-label="menu"><Icon name="menu" /></button>
          {search!==false && <div className="search input-icon"><Icon name="search" /><input className="input" placeholder={lang==='ar'?'بحث…':'Search…'} /></div>}
          <div className="grow"></div>
          <button className="lang-toggle" onClick={()=>setLang(lang==='ar'?'en':'ar')}><Icon name="globe" size={16} />{t.other}</button>
          <button className="icon-btn" onClick={()=>setTheme(theme==='dark'?'light':'dark')}><Icon name={theme==='dark'?'sun':'moon'} /></button>
          <button className="icon-btn" onClick={()=>go(role+':notifications')}><Icon name="bell" /><span className="ind"></span></button>
          <div className="topbar-user">
            <Avatar initial={cfg.avatar} round />
            <div className="tb-user-meta">
              <div className="tb-user-name">{lang==='ar'?cfg.brandRole.ar:cfg.brandRole.en}</div>
              <div className="tb-user-role">{lang==='ar'?cfg.roleLabel.ar:cfg.roleLabel.en}</div>
            </div>
          </div>
        </div>

        <div className="dash-scroll">
          {(title||actions) && <div className="page-head" style={{padding:'24px 32px 0'}}>
            <div>{title && <h1 className="h1">{title}</h1>}{subtitle && <p className="muted" style={{marginTop:5}}>{subtitle}</p>}</div>
            {actions && <div className="row wrap" style={{gap:10}}>{actions}</div>}
          </div>}
          <div className="page">{children}</div>
        </div>
      </div>
    </div>
  );
}

/* =========================================================== OWNER: DASHBOARD */
function OwnerDashboard({ lang, go }) {
  const ar = lang==='ar';
  return (
    <div className="stack" style={{gap:24}}>
      <div className="grid-stats">
        <StatTile icon="grid" amber label={ar?'نسبة الإشغال':'Occupancy'} value="84%" delta="6%" deltaDir="up" foot={ar?'هذا الأسبوع':'this week'} />
        <StatTile icon="wallet" label={ar?'إيراد اليوم':'Today’s revenue'} value={`${NIS}640`} delta="12%" deltaDir="up" foot={ar?'مقابل أمس':'vs yesterday'} />
        <StatTile icon="users" label={ar?'الأعضاء النشطون':'Active members'} value="42" delta="3" deltaDir="up" foot={ar?'هذا الشهر':'this month'} />
        <StatTile icon="inbox" label={ar?'طلبات معلّقة':'Pending requests'} value="3" foot={ar?'بانتظار الرد':'awaiting reply'} />
      </div>

      <div className="dash-2col">
        <div className="card card-pad">
          <div className="between" style={{marginBottom:18}}>
            <div><h3 className="h3">{ar?'الإيرادات الشهرية':'Monthly revenue'}</h3><p className="muted-3" style={{fontSize:'var(--fs-sm)', marginTop:2}}>{ar?'آخر ٦ أشهر · بالألف شيكل':'Last 6 months · ₪ thousands'}</p></div>
            <span className="badge badge-success"><Icon name="trend" size={13} />+9.5%</span>
          </div>
          <LineChart data={REVENUE_SERIES} lang={lang} />
        </div>
        <div className="card card-pad">
          <h3 className="h3" style={{marginBottom:18}}>{ar?'النشاط الأخير':'Recent activity'}</h3>
          <div className="activity">
            {ACTIVITY.map((a,i)=> (
              <div key={i} className="act-row">
                <span className={`act-ico ${a.tone}`}><Icon name={a.ico} size={16} /></span>
                <div className="grow"><div style={{fontSize:'var(--fs-sm)', fontWeight:500}}>{a.text}</div><div className="muted-3" style={{fontSize:'var(--fs-xs)'}}>{a.sub}</div></div>
                <div className="stack" style={{alignItems:'flex-end', gap:2}}>
                  {a.amt && <span className={`tnum act-amt ${a.amt[0]==='+'?'pos':'neg'}`}>{a.amt} {NIS}</span>}
                  <span className="muted-3" style={{fontSize:'var(--fs-xs)'}}>{a.time}</span>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>

      <div className="dash-2col">
        <div className="card card-pad">
          <div className="between" style={{marginBottom:6}}><h3 className="h3">{ar?'إشغال الأسبوع':'Weekly occupancy'}</h3></div>
          <BarChart data={OCCUPANCY_SERIES} lang={lang} accentLast />
        </div>
        <div className="card card-pad">
          <div className="between" style={{marginBottom:16}}><h3 className="h3">{ar?'تنبيهات الفواتير':'Invoice alerts'}</h3><a className="link" onClick={()=>go('owner:invoices')}>{ar?'عرض الكل':'View all'}</a></div>
          <div className="stack" style={{gap:10}}>
            {INVOICES.filter(i=>i.status!=='paid').slice(0,3).map(inv=> (
              <div key={inv.id} className="between mini-inv">
                <div className="row" style={{gap:10}}><span className={`st-ico ${inv.status==='overdue'?'':'amber'}`} style={inv.status==='overdue'?{background:'var(--danger-bg)',color:'var(--danger)'}:{}}><Icon name="receipt" size={16} /></span><div><div style={{fontSize:'var(--fs-sm)', fontWeight:500}}>{inv.member}</div><div className="muted-3 tnum" style={{fontSize:'var(--fs-xs)'}}>{inv.id}</div></div></div>
                <div className="stack" style={{alignItems:'flex-end', gap:4}}><span className="tnum" style={{fontWeight:600}}>{NIS}{inv.amount}</span><StatusBadge status={inv.status} lang={lang} /></div>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}

/* =========================================================== OWNER: MEMBERS */
function OwnerMembers({ lang, toast }) {
  const ar = lang==='ar';
  const [q, setQ] = useStateD('');
  const [filter, setFilter] = useStateD('all');
  const [sort, setSort] = useStateD({ key:'name', dir:'asc' });
  const [sel, setSel] = useStateD(()=>new Set());
  const [page, setPage] = useStateD(1);
  const [drawer, setDrawer] = useStateD(null);
  const pageSize = 5;

  const nameOf = m => ar?m.name:m.nameEn;
  let rows = MEMBERS.filter(m=> filter==='all'||m.status===filter).filter(m=> !q || nameOf(m).includes(q) || m.email.includes(q));
  rows = sortRows(rows, sort, { name:nameOf, status:m=>m.status, seat:m=>m.seat, plan:m=>m.plan, spec:m=>m.spec });
  const pages = Math.max(1, Math.ceil(rows.length/pageSize));
  const curPage = Math.min(page, pages);
  const pageRows = rows.slice((curPage-1)*pageSize, curPage*pageSize);

  const statusLabel = { all:ar?'الكل':'All', active:ar?'نشط':'Active', pending:ar?'قيد المراجعة':'Pending', suspended:ar?'موقوف':'Suspended' };
  const chips = [];
  if (filter!=='all') chips.push({ key:'st', label:`${ar?'الحالة':'Status'}: ${statusLabel[filter]}`, onRemove:()=>setFilter('all') });
  if (q) chips.push({ key:'q', label:`${ar?'بحث':'Search'}: "${q}"`, onRemove:()=>setQ('') });
  const clearAll = ()=>{ setFilter('all'); setQ(''); };

  const allOnPageSel = pageRows.length>0 && pageRows.every(m=>sel.has(m.id));
  const someSel = pageRows.some(m=>sel.has(m.id));
  const toggleAll = ()=>{ const n=new Set(sel); allOnPageSel ? pageRows.forEach(m=>n.delete(m.id)) : pageRows.forEach(m=>n.add(m.id)); setSel(n); };
  const toggleOne = id =>{ const n=new Set(sel); n.has(id)?n.delete(id):n.add(id); setSel(n); };
  const bulkAct = (msg)=>{ toast({tone:'ok', title: msg}); setSel(new Set()); };

  return (
    <div className="dt">
      {/* toolbar */}
      <div className="dt-toolbar">
        <div className="seg">
          {['all','active','pending','suspended'].map(k=>
            <button key={k} className={filter===k?'active':''} onClick={()=>{setFilter(k);setPage(1);}}>{statusLabel[k]}</button>)}
        </div>
        <div className="spacer"></div>
        <div className="dt-search input-icon"><Icon name="search" /><input className="input" value={q} onChange={e=>{setQ(e.target.value);setPage(1);}} placeholder={ar?'ابحث بالاسم أو البريد…':'Search name or email…'} /></div>
        <Button variant="secondary" size="sm" icon="download">{ar?'تصدير':'Export'}</Button>
        <Button variant="primary" size="sm" icon="plus">{ar?'إضافة عضو':'Add member'}</Button>
      </div>

      {(chips.length>0 || sel.size>0) && <div className="between wrap" style={{gap:10}}>
        <FilterChips chips={chips} onClear={clearAll} lang={lang} />
        <span className="dt-count">{ar?'النتائج:':'Results:'} <strong>{rows.length}</strong> {ar?`من ${MEMBERS.length}`:`of ${MEMBERS.length}`}</span>
      </div>}

      {/* bulk bar */}
      {sel.size>0 && <div className="bulk-bar">
        <span className="bb-count"><span className="tnum">{sel.size}</span> {ar?'محدّد':'selected'}</span>
        <div className="row" style={{gap:8}}>
          <Button variant="ghost" size="sm" icon="chat" onClick={()=>bulkAct(ar?'تم إرسال رسالة جماعية':'Broadcast sent')}>{ar?'رسالة':'Message'}</Button>
          <Button variant="ghost" size="sm" icon="check" onClick={()=>bulkAct(ar?'تم تفعيل الأعضاء':'Members activated')}>{ar?'تفعيل':'Activate'}</Button>
          <Button variant="danger" size="sm" icon="x" onClick={()=>bulkAct(ar?'تم إيقاف الأعضاء':'Members suspended')}>{ar?'إيقاف':'Suspend'}</Button>
        </div>
      </div>}

      <div className="table-wrap">
        <div className="tbl-scroll">
        <table className="tbl">
          <thead><tr>
            <th className="col-check"><Cbx checked={allOnPageSel} indeterminate={someSel} onChange={toggleAll} /></th>
            <Th id="name" sort={sort} setSort={setSort}>{ar?'العضو':'Member'}</Th>
            <Th id="spec" sort={sort} setSort={setSort}>{ar?'التخصص':'Specialty'}</Th>
            <Th id="seat" sort={sort} setSort={setSort} num>{ar?'المقعد':'Seat'}</Th>
            <Th id="plan" sort={sort} setSort={setSort}>{ar?'الخطة':'Plan'}</Th>
            <Th id="status" sort={sort} setSort={setSort}>{ar?'الحالة':'Status'}</Th>
            <th></th>
          </tr></thead>
          <tbody>
            {pageRows.map(m=> (
              <tr key={m.id} className={sel.has(m.id)?'is-selected':''} onClick={()=>setDrawer(m)} style={{cursor:'pointer'}}>
                <td className="col-check"><Cbx checked={sel.has(m.id)} onChange={()=>toggleOne(m.id)} /></td>
                <td><div className="row" style={{gap:10}}><Avatar initial={m.avatar} round /><div><div style={{fontWeight:600}}>{nameOf(m)}</div><div className="muted-3 ltr" style={{fontSize:'var(--fs-xs)'}}>{m.email}</div></div></div></td>
                <td className="muted">{m.spec}</td>
                <td className="cell-num">{m.seat}</td>
                <td>{m.plan}</td>
                <td><StatusBadge status={m.status} lang={lang} /></td>
                <td><div className="row-actions"><button className="icon-btn" onClick={e=>{e.stopPropagation();setDrawer(m);}}><Icon name="eye" /></button><button className="icon-btn" onClick={e=>e.stopPropagation()}><Icon name="more" /></button></div></td>
              </tr>
            ))}
            {rows.length===0 && <tr><td colSpan="7"><div className="empty-state"><EmptyArt /><div><div style={{fontWeight:600, color:'var(--text-2)'}}>{ar?'لا يوجد أعضاء مطابقون':'No matching members'}</div><div style={{fontSize:'var(--fs-sm)'}}>{ar?'جرّب تعديل البحث أو التصفية':'Try adjusting your search or filter'}</div></div><Button variant="secondary" size="sm" onClick={clearAll}>{ar?'إعادة ضبط':'Reset filters'}</Button></div></td></tr>}
          </tbody>
        </table>
        </div>
        {rows.length>0 && <div className="table-foot">
          <span>{ar?`عرض ${(curPage-1)*pageSize+1}–${Math.min(curPage*pageSize,rows.length)} من `:`Showing ${(curPage-1)*pageSize+1}–${Math.min(curPage*pageSize,rows.length)} of `}<strong style={{color:'var(--text-2)'}}>{rows.length}</strong></span>
          <Pager page={curPage} pages={pages} setPage={setPage} lang={lang} />
        </div>}
      </div>

      {drawer && <Drawer title={ar?'تفاصيل العضو':'Member details'} onClose={()=>setDrawer(null)}
        footer={<><Button variant="ghost" onClick={()=>setDrawer(null)}>{ar?'إغلاق':'Close'}</Button>{drawer.status==='pending'
          ? <><Button variant="danger">{ar?'رفض':'Reject'}</Button><Button variant="primary" onClick={()=>{toast({tone:'ok',title:ar?'تم قبول العضو':'Member approved'});setDrawer(null);}}>{ar?'قبول':'Approve'}</Button></>
          : <Button variant="danger" icon="x">{ar?'إيقاف العضو':'Suspend'}</Button>}</>}>
        <div className="stack" style={{gap:0}}>
          <div className="drawer-section row" style={{gap:14}}>
            <Avatar initial={drawer.avatar} size="lg" round /><div><div className="h3">{nameOf(drawer)}</div><div className="muted">{drawer.spec}</div><div style={{marginTop:6}}><StatusBadge status={drawer.status} lang={lang} /></div></div>
          </div>
          <div className="drawer-section stack" style={{gap:12}}>
            {[[ar?'البريد':'Email',drawer.email,'ltr'],[ar?'المقعد':'Seat',drawer.seat,'tnum'],[ar?'الخطة':'Plan',drawer.plan],[ar?'عضو منذ':'Member since',drawer.since,'tnum ltr']].map(([k,v,c],i)=>
              <div key={i} className="between"><span className="muted">{k}</span><span className={c||''} style={{fontWeight:500}}>{v}</span></div>)}
          </div>
          <div className="drawer-section">
            <div className="muted" style={{marginBottom:10}}>{ar?'سجل الفواتير':'Invoice history'}</div>
            {INVOICES.slice(0,2).map(inv=> <div key={inv.id} className="between mini-inv"><span className="tnum muted-3" style={{fontSize:'var(--fs-sm)'}}>{inv.id}</span><div className="row" style={{gap:10}}><span className="tnum" style={{fontWeight:600}}>{NIS}{inv.amount}</span><StatusBadge status={inv.status} lang={lang} /></div></div>)}
          </div>
        </div>
      </Drawer>}
    </div>
  );
}

function EmptyArt() {
  return <div className="es-art" style={{display:'grid',placeItems:'center'}}><div className="tile-glyph is-bulb" style={{fontSize:48,opacity:.5}}><Icon name="bulb" /></div></div>;
}

Object.assign(window, { NAV, DashShell, OwnerDashboard, OwnerMembers, EmptyArt });
