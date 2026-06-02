/* TAQAT — Super Admin screens */
const { useState:useStateAd } = React;

function AdminAnalytics({ lang }) {
  const ar = lang==='ar';
  return (
    <div className="stack" style={{gap:24}}>
      <div className="grid-stats">
        <StatTile icon="building" label={ar?'إجمالي المساحات':'Total spaces'} value="128" delta="8" deltaDir="up" foot={ar?'هذا الشهر':'this month'} />
        <StatTile icon="users" label={ar?'إجمالي الأعضاء':'Total members'} value="3,412" delta="214" deltaDir="up" foot={ar?'هذا الشهر':'this month'} />
        <StatTile icon="wallet" amber label={ar?'إجمالي الإيرادات':'Gross revenue'} value={`${NIS}96.4k`} delta="11%" deltaDir="up" foot={ar?'مايو':'May'} />
        <StatTile icon="grid" label={ar?'متوسط الإشغال':'Avg. occupancy'} value="72%" delta="2%" deltaDir="down" foot={ar?'مقابل أبريل':'vs April'} />
      </div>
      <div className="dash-2col">
        <div className="card card-pad"><div className="between" style={{marginBottom:16}}><div><h3 className="h3">{ar?'نمو الإيرادات':'Revenue growth'}</h3><p className="muted-3" style={{fontSize:'var(--fs-sm)', marginTop:2}}>{ar?'إجمالي المنصة · بالألف شيكل':'Platform-wide · ₪ thousands'}</p></div><Segmented items={[{id:'6m',label:ar?'٦ أشهر':'6M'},{id:'1y',label:ar?'سنة':'1Y'}]} value="6m" onChange={()=>{}} /></div><LineChart data={REVENUE_SERIES.map(d=>({...d, v:d.v*7}))} lang={lang} /></div>
        <div className="card card-pad"><h3 className="h3" style={{marginBottom:16}}>{ar?'المساحات حسب المدينة':'Spaces by city'}</h3>
          <div className="stack" style={{gap:14, marginTop:6}}>
            {[['غزة','Gaza City',42],['خان يونس','Khan Younis',28],['رفح','Rafah',19],['جباليا','Jabalia',22],['دير البلح','Deir al-Balah',17]].map((c,i)=> (
              <div key={i} className="stack" style={{gap:6}}><div className="between"><span style={{fontSize:'var(--fs-sm)'}}>{ar?c[0]:c[1]}</span><span className="tnum muted" style={{fontSize:'var(--fs-sm)'}}>{c[2]}</span></div><div className="bar-track"><div className={`bar-fill ${i===0?'amber':''}`} style={{width:`${(c[2]/42)*100}%`}}></div></div></div>
            ))}
          </div>
        </div>
      </div>
      <div className="card card-pad"><h3 className="h3" style={{marginBottom:6}}>{ar?'إشغال المنصة الأسبوعي':'Platform weekly occupancy'}</h3><BarChart data={OCCUPANCY_SERIES} lang={lang} accentLast /></div>
    </div>
  );
}

function AdminWorkspaces({ lang, toast }) {
  const ar = lang==='ar';
  const [filter, setFilter] = useStateAd('all');
  const [q, setQ] = useStateAd('');
  const [sort, setSort] = useStateAd({ key:'revenue', dir:'desc' });
  const [review, setReview] = useStateAd(null);
  const statusLabel = { all:ar?'الكل':'All', active:ar?'نشطة':'Active', pending:ar?'قيد المراجعة':'Pending', suspended:ar?'موقوفة':'Suspended' };
  let rows = ADMIN_SPACES.filter(s=> filter==='all'||s.status===filter).filter(s=> !q || s.name.includes(q) || s.owner.includes(q) || s.city.includes(q));
  rows = sortRows(rows, sort, { members:s=>s.members, revenue:s=>s.revenue, commission:s=>s.commission });
  const chips = [];
  if (filter!=='all') chips.push({ key:'st', label:`${ar?'الحالة':'Status'}: ${statusLabel[filter]}`, onRemove:()=>setFilter('all') });
  if (q) chips.push({ key:'q', label:`${ar?'بحث':'Search'}: "${q}"`, onRemove:()=>setQ('') });
  return (
    <div className="dt" style={{gap:18}}>
      <div className="dt-toolbar">
        <div className="seg">
          {['all','active','pending','suspended'].map(k=>
            <button key={k} className={filter===k?'active':''} onClick={()=>setFilter(k)}>{statusLabel[k]}</button>)}
        </div>
        <div className="spacer"></div>
        <div className="dt-search input-icon"><Icon name="search" /><input className="input" value={q} onChange={e=>setQ(e.target.value)} placeholder={ar?'ابحث باسم المساحة أو المالك…':'Search space or owner…'} /></div>
        <Button variant="secondary" size="sm" icon="download">{ar?'تصدير':'Export'}</Button>
      </div>

      {chips.length>0 && <div className="between wrap" style={{gap:10}}><FilterChips chips={chips} onClear={()=>{setFilter('all');setQ('');}} lang={lang} /><span className="dt-count">{ar?'النتائج:':'Results:'} <strong>{rows.length}</strong> {ar?`من ${ADMIN_SPACES.length}`:`of ${ADMIN_SPACES.length}`}</span></div>}

      <div className="table-wrap">
        <div className="tbl-scroll">
        <table className="tbl">
          <thead><tr>
            <Th id="name" sort={sort} setSort={setSort}>{ar?'المساحة':'Space'}</Th>
            <Th id="owner" sort={sort} setSort={setSort}>{ar?'المالك':'Owner'}</Th>
            <th>{ar?'المدينة':'City'}</th>
            <Th id="members" sort={sort} setSort={setSort} num>{ar?'الأعضاء':'Members'}</Th>
            <Th id="revenue" sort={sort} setSort={setSort} num>{ar?'الإيراد الشهري':'Monthly rev.'}</Th>
            <Th id="commission" sort={sort} setSort={setSort} num>{ar?'العمولة':'Commission'}</Th>
            <Th id="status" sort={sort} setSort={setSort}>{ar?'الحالة':'Status'}</Th>
            <th></th>
          </tr></thead>
          <tbody>
            {rows.map(s=> (
              <tr key={s.id}>
                <td><div className="row" style={{gap:10}}><span className="nav-tile-ico" style={{background:'var(--primary-soft)', color:'var(--primary)'}}><Icon name="building" size={16} /></span><span style={{fontWeight:600}}>{s.name}</span></div></td>
                <td className="muted">{s.owner}</td><td>{s.city}</td>
                <td className="cell-num">{s.members}</td>
                <td className="cell-num" style={{fontWeight:600}}>{NIS}{s.revenue}</td>
                <td className="cell-num">{s.commission}%</td>
                <td><StatusBadge status={s.status} lang={lang} /></td>
                <td><div className="row-actions">{s.status==='pending'
                  ? <Button variant="primary" size="sm" onClick={()=>setReview(s)}>{ar?'مراجعة':'Review'}</Button>
                  : <button className="icon-btn"><Icon name="eye" /></button>}
                  <button className="icon-btn"><Icon name="more" /></button></div></td>
              </tr>
            ))}
            {rows.length===0 && <tr><td colSpan="8"><div className="empty-state"><EmptyArt /><div style={{fontWeight:600, color:'var(--text-2)'}}>{ar?'لا توجد مساحات مطابقة':'No matching spaces'}</div></div></td></tr>}
          </tbody>
        </table>
        </div>
        <div className="table-foot"><span>{ar?`${rows.length} مساحة`:`${rows.length} spaces`}</span><Pager page={1} pages={1} setPage={()=>{}} lang={lang} /></div>
      </div>

      {review && <Modal title={ar?'مراجعة طلب المساحة':'Review workspace application'} icon="building" onClose={()=>setReview(null)}
        footer={<><Button variant="danger" onClick={()=>{setReview(null);toast({tone:'info',title:ar?'تم رفض الطلب':'Application rejected'});}}>{ar?'رفض':'Reject'}</Button><Button variant="primary" icon="check" onClick={()=>{setReview(null);toast({tone:'ok',title:ar?'تمت الموافقة على المساحة':'Workspace approved'});}}>{ar?'الموافقة':'Approve'}</Button></>}>
        <div className="stack" style={{gap:14}}>
          <div className="row" style={{gap:14}}><span className="nav-tile-ico" style={{width:48, height:48, background:'var(--primary-soft)', color:'var(--primary)'}}><Icon name="building" /></span><div><div className="h3">{review.name}</div><div className="muted">{review.owner} · {review.city}</div></div></div>
          <div className="divider"></div>
          {[[ar?'السعة':'Capacity',`${review.members} ${ar?'مقعد':'seats'}`],[ar?'العمولة المقترحة':'Proposed commission',`${review.commission}%`]].map((r,i)=> <div key={i} className="between"><span className="muted">{r[0]}</span><span className="tnum" style={{fontWeight:500}}>{r[1]}</span></div>)}
          <div className="divider"></div>
          <div className="muted" style={{fontSize:'var(--fs-sm)'}}>{ar?'المستندات المرفقة':'Attached documents'}</div>
          <div className="row" style={{gap:10}}>
            <div className="card" style={{flex:1, padding:14, display:'flex', alignItems:'center', gap:10}}><Icon name="doc" size={20} style={{color:'var(--text-3)'}} /><div><div style={{fontSize:'var(--fs-sm)', fontWeight:500}}>{ar?'الرخصة':'License'}</div><div className="muted-3" style={{fontSize:'var(--fs-xs)'}}>PDF · 1.2MB</div></div></div>
            <div className="card" style={{flex:1, padding:14, display:'flex', alignItems:'center', gap:10}}><Icon name="user" size={20} style={{color:'var(--text-3)'}} /><div><div style={{fontSize:'var(--fs-sm)', fontWeight:500}}>{ar?'هوية المالك':'Owner ID'}</div><div className="muted-3" style={{fontSize:'var(--fs-xs)'}}>JPG · 840KB</div></div></div>
          </div>
        </div>
      </Modal>}
    </div>
  );
}

function AdminUsers({ lang }) {
  const ar = lang==='ar';
  const [role, setRole] = useStateAd('all');
  const [q, setQ] = useStateAd('');
  const all = [
    ...MEMBERS.map(m=>({name:ar?m.name:m.nameEn, email:m.email, role:'member', roleL:ar?'عامل مستقل':'Freelancer', status:m.status, avatar:m.avatar})),
    {name:ar?'سمير الأغا':'Samir Al-Agha', email:'samir@manara.ps', role:'owner', roleL:ar?'صاحب مساحة':'Owner', status:'active', avatar:'س'},
    {name:ar?'هبة قديح':'Heba Qudaih', email:'heba@kyhub.ps', role:'owner', roleL:ar?'صاحب مساحة':'Owner', status:'active', avatar:'ه'},
  ];
  const roleL = { all:ar?'الكل':'All', member:ar?'العاملون':'Freelancers', owner:ar?'أصحاب المساحات':'Owners' };
  const users = all.filter(u=> role==='all'||u.role===role).filter(u=> !q || u.name.includes(q) || u.email.includes(q));
  return (
    <div className="dt" style={{gap:18}}>
      <div className="dt-toolbar">
        <div className="seg">
          {['all','member','owner'].map(k=> <button key={k} className={role===k?'active':''} onClick={()=>setRole(k)}>{roleL[k]}</button>)}
        </div>
        <div className="spacer"></div>
        <div className="dt-search input-icon"><Icon name="search" /><input className="input" value={q} onChange={e=>setQ(e.target.value)} placeholder={ar?'ابحث عن مستخدم…':'Search users…'} /></div>
        <span className="dt-count">{ar?'العدد:':'Total:'} <strong>{users.length}</strong></span>
      </div>
      <div className="table-wrap">
        <div className="tbl-scroll">
        <table className="tbl">
          <thead><tr><th>{ar?'المستخدم':'User'}</th><th>{ar?'الدور':'Role'}</th><th>{ar?'الحالة':'Status'}</th><th>{ar?'مفعّل':'Enabled'}</th><th></th></tr></thead>
          <tbody>
            {users.map((u,i)=> (
              <tr key={i}>
                <td><div className="row" style={{gap:10}}><Avatar initial={u.avatar} round /><div><div style={{fontWeight:600}}>{u.name}</div><div className="muted-3 ltr" style={{fontSize:'var(--fs-xs)'}}>{u.email}</div></div></div></td>
                <td><span className="pill-role"><Icon name={u.role==='owner'?'building':'user'} size={13} />{u.roleL}</span></td>
                <td><StatusBadge status={u.status} lang={lang} /></td>
                <td><label className="switch"><input type="checkbox" defaultChecked={u.status==='active'} /><span className="track"></span><span className="thumb"></span></label></td>
                <td><div className="row-actions"><button className="icon-btn"><Icon name="eye" /></button><button className="icon-btn"><Icon name="more" /></button></div></td>
              </tr>
            ))}
          </tbody>
        </table>
        </div>
        <div className="table-foot"><span>{ar?`${users.length} مستخدم`:`${users.length} users`}</span><Pager page={1} pages={1} setPage={()=>{}} lang={lang} /></div>
      </div>
    </div>
  );
}

function AdminFinance({ lang, toast }) {
  const ar = lang==='ar';
  const transfers = ADMIN_SPACES.filter(s=>s.status==='active').map(s=>({name:s.name, owner:s.owner, gross:s.revenue, comm:Math.round(s.revenue*s.commission/100), rate:s.commission}));
  const totalComm = transfers.reduce((a,b)=>a+b.comm,0);
  return (
    <div className="stack" style={{gap:18}}>
      <div className="grid-stats" style={{gridTemplateColumns:'repeat(3,1fr)'}}>
        <StatTile icon="wallet" amber label={ar?'عمولات المنصة (مايو)':'Platform commission (May)'} value={`${NIS}${totalComm}`} delta="9%" deltaDir="up" foot={ar?'من الإيراد الإجمالي':'of gross revenue'} />
        <StatTile icon="trend" label={ar?'الإيراد الإجمالي':'Gross revenue'} value={`${NIS}5,744`} foot={ar?'٥ مساحات نشطة':'5 active spaces'} />
        <StatTile icon="card" label={ar?'تحويلات معلّقة':'Pending transfers'} value={`${NIS}1,260`} foot={ar?'تحويلان':'2 transfers'} />
      </div>
      <div className="card card-pad">
        <div className="between" style={{marginBottom:8}}><h3 className="h3">{ar?'سجل العمولات والتحويلات':'Commissions & transfers'}</h3><Button variant="secondary" size="sm" icon="download">{ar?'تقرير مالي':'Financial report'}</Button></div>
      </div>
      <div className="table-wrap">
        <table className="tbl">
          <thead><tr><th>{ar?'المساحة':'Space'}</th><th>{ar?'الإيراد الإجمالي':'Gross'}</th><th>{ar?'نسبة العمولة':'Rate'}</th><th>{ar?'عمولة المنصة':'Commission'}</th><th>{ar?'صافي للمالك':'Net to owner'}</th><th></th></tr></thead>
          <tbody>
            {transfers.map((t,i)=> (
              <tr key={i}>
                <td><div><div style={{fontWeight:500}}>{t.name}</div><div className="muted-3" style={{fontSize:'var(--fs-xs)'}}>{t.owner}</div></div></td>
                <td className="cell-num">{NIS}{t.gross}</td>
                <td className="cell-num">{t.rate}%</td>
                <td className="cell-num" style={{fontWeight:600, color:'var(--amber-600)'}}>{NIS}{t.comm}</td>
                <td className="cell-num" style={{fontWeight:600}}>{NIS}{t.gross-t.comm}</td>
                <td><Button variant="ghost" size="sm" icon="send" onClick={()=>toast({tone:'ok',title:ar?'تم تنفيذ التحويل':'Transfer sent'})}>{ar?'تحويل':'Transfer'}</Button></td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

/* Generic placeholder for screens not deeply designed (messages, announcements, settings) */
function ComingSoon({ lang, title, icon }) {
  const ar = lang==='ar';
  return <div className="empty-state" style={{minHeight:400}}><div className="st-ico amber" style={{width:56, height:56, borderRadius:'var(--r-tile)'}}><Icon name={icon||'layers'} size={26} /></div><div><div className="h3">{title}</div><div style={{fontSize:'var(--fs-sm)', marginTop:6, maxWidth:340}}>{ar?'هذه الشاشة جزء من نظام التصميم نفسه — تُبنى من المكوّنات المعرّفة في صفحة الأسس.':'This screen reuses the same component library defined in the Foundations page.'}</div></div></div>;
}

Object.assign(window, { AdminAnalytics, AdminWorkspaces, AdminUsers, AdminFinance, ComingSoon });
