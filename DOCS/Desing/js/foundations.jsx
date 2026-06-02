/* TAQAT — Foundations / complete component library showcase */
const { useState:useStateF } = React;

function FSection({ id, n, title, sub, children }) {
  return (
    <section className="fnd-section" id={id}>
      <div className="fnd-section-head">
        <span className="fnd-n tnum">{n}</span>
        <div><h2 className="h2">{title}</h2>{sub && <p className="muted" style={{marginTop:4}}>{sub}</p>}</div>
      </div>
      {children}
    </section>
  );
}
function Swatch({ name, hex }) {
  return <div className="swatch"><div className="sw-chip" style={{background:hex}}></div><div className="sw-meta"><span className="sw-name">{name}</span><span className="sw-hex tnum">{hex}</span></div></div>;
}
function Spec({ label, children }) { return <div className="fnd-spec"><div className="fnd-spec-label">{label}</div><div className="fnd-spec-body">{children}</div></div>; }
function Demo({ label, children, col }) {
  return <div className="fnd-demo"><div className="fnd-demo-label">{label}</div><div className={`fnd-demo-body ${col?'col':''}`}>{children}</div></div>;
}

/* ---- live datatable demo ---- */
function FndTable({ lang }) {
  const ar = lang==='ar';
  const [sort, setSort] = useStateF({ key:'name', dir:'asc' });
  const [sel, setSel] = useStateF(()=>new Set());
  const [q, setQ] = useStateF('');
  const [page, setPage] = useStateF(1);
  const nameOf = m => ar?m.name:m.nameEn;
  let rows = MEMBERS.filter(m=> !q || nameOf(m).includes(q) || m.email.includes(q));
  rows = sortRows(rows, sort, { name:nameOf, seat:m=>m.seat, status:m=>m.status });
  const pageSize = 4, pages = Math.max(1, Math.ceil(rows.length/pageSize));
  const cur = Math.min(page, pages);
  const pageRows = rows.slice((cur-1)*pageSize, cur*pageSize);
  const allSel = pageRows.length>0 && pageRows.every(m=>sel.has(m.id));
  const someSel = pageRows.some(m=>sel.has(m.id));
  const tAll = ()=>{ const n=new Set(sel); allSel?pageRows.forEach(m=>n.delete(m.id)):pageRows.forEach(m=>n.add(m.id)); setSel(n); };
  const tOne = id=>{ const n=new Set(sel); n.has(id)?n.delete(id):n.add(id); setSel(n); };
  const chips = q ? [{ key:'q', label:`${ar?'بحث':'Search'}: "${q}"`, onRemove:()=>setQ('') }] : [];
  return (
    <div className="dt">
      <div className="dt-toolbar">
        <div className="dt-search input-icon"><Icon name="search" /><input className="input" value={q} onChange={e=>{setQ(e.target.value);setPage(1);}} placeholder={ar?'ابحث…':'Search…'} /></div>
        <div className="spacer"></div>
        <span className="dt-count">{ar?'النتائج:':'Results:'} <strong>{rows.length}</strong></span>
        <Button variant="secondary" size="sm" icon="download">{ar?'تصدير':'Export'}</Button>
      </div>
      {chips.length>0 && <FilterChips chips={chips} onClear={()=>setQ('')} lang={lang} />}
      {sel.size>0 && <div className="bulk-bar"><span className="bb-count"><span className="tnum">{sel.size}</span> {ar?'محدّد':'selected'}</span><div className="row" style={{gap:8}}><Button variant="ghost" size="sm" icon="chat">{ar?'رسالة':'Message'}</Button><Button variant="danger" size="sm" icon="x">{ar?'إيقاف':'Suspend'}</Button></div></div>}
      <div className="table-wrap">
        <div className="tbl-scroll">
        <table className="tbl">
          <thead><tr>
            <th className="col-check"><Cbx checked={allSel} indeterminate={someSel} onChange={tAll} /></th>
            <Th id="name" sort={sort} setSort={setSort}>{ar?'العضو':'Member'}</Th>
            <Th id="seat" sort={sort} setSort={setSort} num>{ar?'المقعد':'Seat'}</Th>
            <Th id="status" sort={sort} setSort={setSort}>{ar?'الحالة':'Status'}</Th>
            <th></th>
          </tr></thead>
          <tbody>
            {pageRows.map(m=> (
              <tr key={m.id} className={sel.has(m.id)?'is-selected':''}>
                <td className="col-check"><Cbx checked={sel.has(m.id)} onChange={()=>tOne(m.id)} /></td>
                <td><div className="row" style={{gap:10}}><Avatar initial={m.avatar} round /><div><div style={{fontWeight:600}}>{nameOf(m)}</div><div className="muted-3 ltr" style={{fontSize:'var(--fs-xs)'}}>{m.email}</div></div></div></td>
                <td className="cell-num">{m.seat}</td>
                <td><StatusBadge status={m.status} lang={lang} /></td>
                <td><div className="row-actions"><button className="icon-btn"><Icon name="eye" /></button><button className="icon-btn"><Icon name="more" /></button></div></td>
              </tr>
            ))}
            {rows.length===0 && <tr><td colSpan="5"><div className="empty-state" style={{padding:'32px'}}><div style={{fontWeight:600,color:'var(--text-2)'}}>{ar?'لا نتائج':'No results'}</div></div></td></tr>}
          </tbody>
        </table>
        </div>
        {rows.length>0 && <div className="table-foot"><span>{ar?`عرض ${(cur-1)*pageSize+1}–${Math.min(cur*pageSize,rows.length)} من `:`Showing ${(cur-1)*pageSize+1}–${Math.min(cur*pageSize,rows.length)} of `}<strong style={{color:'var(--text-2)'}}>{rows.length}</strong></span><Pager page={cur} pages={pages} setPage={setPage} lang={lang} /></div>}
      </div>
    </div>
  );
}

function Foundations({ lang, go, theme, setTheme, setLang, t, toast }) {
  const ar = lang==='ar';
  const [seatSel, setSeatSel] = useStateF(null);
  const [mOpen, setMOpen] = useStateF(false);
  const [confirm, setConfirm] = useStateF(false);
  const [dOpen, setDOpen] = useStateF(false);
  const [tab, setTab] = useStateF('a');
  const [seg, setSeg] = useStateF('list');
  const blues = [['50','#EAF4FB'],['100','#D2E8F6'],['200','#A7D1EE'],['300','#6FB4E2'],['400','#3F97D4'],['500','#1F82C7'],['600','#176AA8'],['700','#135789'],['800','#114A74'],['900','#0E3A5C']];
  const ambers = [['400','#FBC03A'],['500','#F6A91B'],['600','#DB9009']];
  const neutrals = [['50','#FAFAFA'],['100','#F4F4F5'],['200','#E4E4E7'],['300','#D4D4D8'],['400','#A1A1AA'],['500','#71717A'],['600','#52525B'],['700','#3F3F46'],['800','#27272A'],['900','#18181B']];
  const semantic = [['success','#16A34A'],['warning','#F59E0B'],['danger','#DC2626'],['info','#1F82C7']];
  const seats = makeSeats('A',8,['','occupied','','reserved','','','disabled','']);
  return (
    <div className="fnd">
      <div className="fnd-top">
        <div className="container between" style={{height:64}}>
          <a className="logo" onClick={()=>go('home')} style={{cursor:'pointer'}}><img src="assets/logo.png" alt="TAQAT" /><span className="fnd-tag">{ar?'نظام التصميم':'Design system'}</span></a>
          <div className="row" style={{gap:8}}>
            <button className="lang-toggle" onClick={()=>setLang(ar?'en':'ar')}><Icon name="globe" size={16} />{t.other}</button>
            <button className="icon-btn" onClick={()=>setTheme(theme==='dark'?'light':'dark')}><Icon name={theme==='dark'?'sun':'moon'} /></button>
            <Button variant="secondary" size="sm" iconEnd="arrowR" onClick={()=>go('home')}>{ar?'المنصة':'View product'}</Button>
          </div>
        </div>
      </div>

      <div className="container fnd-body">
        <header className="fnd-hero">
          <span className="eyebrow">TAQAT · {ar?'أسس التصميم':'Design foundations'}</span>
          <h1 className="h-display" style={{marginTop:12}}>{ar?'نظام تصميم طاقت':'The TAQAT design system'}</h1>
          <p className="muted" style={{fontSize:'var(--fs-lg)', maxWidth:620, marginTop:12, lineHeight:1.7}}>{ar
            ? 'هادئ، عالي الثقة، حدود بدل الظلال. مبني على وحدة «المربّع» المستوحاة من الشعار، RTL أصيل مع دعم كامل للإنجليزية.'
            : 'Calm, high-trust, borders over shadows. Built on the logo’s rounded-tile primitive — RTL-native with full English support.'}</p>
          <div className="row wrap" style={{gap:10, marginTop:20}}>
            <span className="kicker"><span className="dot-amber"></span>{ar?'حدود بدل الظلال':'Borders over shadows'}</span>
            <span className="kicker"><Icon name="globe" size={15} />{ar?'RTL أصيل':'RTL-native'}</span>
            <span className="kicker"><Icon name="grid" size={15} />{ar?'خط Cairo':'Cairo type'}</span>
          </div>
        </header>

        {/* 01 BRAND */}
        <FSection id="brand" n="01" title={ar?'العلامة ووحدة المربّع':'Brand & the tile primitive'} sub={ar?'الشعار = مربّعات تحمل حروف T·A·Q·A·T، والمصباح في حرف Q هو لمسة الطاقة.':'The logotype = tiles holding T·A·Q·A·T, with the lightbulb in the Q as the energy accent.'}>
          <div className="fnd-grid2">
            <div className="card card-pad stack" style={{gap:24, alignItems:'center', justifyContent:'center', minHeight:200}}>
              <img src="assets/logo.png" alt="TAQAT" style={{height:52}} />
              <div className="divider" style={{width:'60%'}}></div>
              <TileLogo size={44} />
            </div>
            <div className="card card-pad stack" style={{gap:16}}>
              <Spec label={ar?'وحدة المربّع — نصف قطر ١٤':'Tile primitive — radius 14'}><div className="row" style={{gap:10}}><span className="tile-glyph" style={{fontSize:34}}>T</span><span className="tile-glyph is-bulb" style={{fontSize:34}}><Icon name="bulb" /></span><span className="seat is-selected" style={{width:46,height:46,cursor:'default'}}>04</span><span className="st-ico amber" style={{width:46,height:46,borderRadius:'var(--r-tile)'}}><Icon name="grid" /></span></div></Spec>
              <p className="muted" style={{fontSize:'var(--fs-sm)'}}>{ar?'يتكرر المربّع في: الشعار، بطاقات الإحصاء، خلايا خريطة المقاعد، وحالة التنقّل النشطة.':'The tile recurs across: the logotype, stat cards, seat-map cells, and active nav states.'}</p>
            </div>
          </div>
        </FSection>

        {/* 02 COLOR */}
        <FSection id="color" n="02" title={ar?'نظام الألوان':'Color system'} sub={ar?'محايد القيادة، أزرق طاقت للأفعال الأساسية، كهرماني كلمسة طاقة محدودة.':'Neutral-led, TAQAT blue for primary, amber as a restrained energy accent.'}>
          <div className="stack" style={{gap:20}}>
            <div><div className="fnd-label">{ar?'أزرق طاقت':'TAQAT Blue'}</div><div className="ramp">{blues.map(([n,h])=> <Swatch key={n} name={n} hex={h} />)}</div></div>
            <div className="fnd-grid2">
              <div><div className="fnd-label">{ar?'كهرماني (المصباح)':'Amber (lightbulb)'}</div><div className="ramp ramp-3">{ambers.map(([n,h])=> <Swatch key={n} name={n} hex={h} />)}</div></div>
              <div><div className="fnd-label">{ar?'دلالي':'Semantic'}</div><div className="ramp ramp-4">{semantic.map(([n,h])=> <Swatch key={n} name={n} hex={h} />)}</div></div>
            </div>
            <div><div className="fnd-label">{ar?'محايد':'Neutrals'}</div><div className="ramp">{neutrals.map(([n,h])=> <Swatch key={n} name={n} hex={h} />)}</div></div>
          </div>
        </FSection>

        {/* 03 TYPE */}
        <FSection id="type" n="03" title={ar?'الطباعة':'Typography'} sub={ar?'Cairo للعربية، Inter للأرقام واللاتيني بأرقام جدولية.':'Cairo for Arabic, Inter for Latin & tabular numerals.'}>
          <div className="card card-pad stack" style={{gap:2}}>
            {[['Display','2.5rem',700],['Heading 1','2rem',600],['Heading 2','1.5rem',600],['Heading 3','1.25rem',600],['Body large','1.125rem',400],['Body','1rem',400],['Small','0.875rem',500]].map((r,i)=> (
              <div key={i} className="type-row"><span className="type-sample" style={{fontSize:r[1], fontWeight:r[2]}}>{ar?'طاقتك تجد مكانها':'Your energy finds its place'}</span><span className="type-meta muted-3 tnum">{r[0]} · {r[1]}</span></div>
            ))}
            <div className="divider" style={{margin:'10px 0'}}></div>
            <div className="type-row"><span className="tnum" style={{fontSize:'1.6rem', fontWeight:600}}>₪1,280.50 · INV-2605 · A-04</span><span className="type-meta muted-3">Inter · tabular-nums · LTR</span></div>
          </div>
        </FSection>

        {/* 04 BUTTONS */}
        <FSection id="buttons" n="04" title={ar?'الأزرار والأفعال':'Buttons & actions'} sub={ar?'خمسة أنماط، ثلاثة أحجام، مع أيقونات، وكل الحالات.':'Five variants, three sizes, with icons, and every state.'}>
          <div className="card card-pad stack" style={{gap:18}}>
            <Demo label={ar?'الأنماط':'Variants'}><Button>{ar?'أساسي':'Primary'}</Button><Button variant="secondary">{ar?'ثانوي':'Secondary'}</Button><Button variant="ghost">{ar?'شبح':'Ghost'}</Button><Button variant="danger">{ar?'خطر':'Danger'}</Button><Button variant="accent" icon="bulb">{ar?'كهرماني':'Accent'}</Button></Demo>
            <Demo label={ar?'الأحجام':'Sizes'}><Button size="sm">{ar?'صغير':'Small'}</Button><Button>{ar?'متوسط':'Medium'}</Button><Button size="lg">{ar?'كبير':'Large'}</Button></Demo>
            <Demo label={ar?'مع أيقونات':'With icons'}><Button icon="plus">{ar?'إضافة':'Add'}</Button><Button variant="secondary" iconEnd="arrowR">{ar?'التالي':'Next'}</Button><button className="btn btn-secondary btn-icon"><Icon name="settings" /></button><button className="icon-btn"><Icon name="bell" /></button></Demo>
            <Demo label={ar?'الحالات':'States'}><Button>{ar?'افتراضي':'Default'}</Button><Button disabled>{ar?'معطّل':'Disabled'}</Button><Button className="is-loading" disabled><span className="btn-spinner"></span>{ar?'جارٍ…':'Loading…'}</Button></Demo>
          </div>
        </FSection>

        {/* 05 FORM FIELDS */}
        <FSection id="forms" n="05" title={ar?'حقول النماذج':'Form fields'} sub={ar?'كل أنواع الإدخال مع حالات مطلوب/اختياري/خطأ/صحيح/معطّل.':'Every input type with required / optional / error / valid / disabled states.'}>
          <div className="fnd-grid2">
            <div className="card card-pad stack" style={{gap:16}}>
              <div className="form-section"><span className="fs-title">{ar?'حقول نصية':'Text inputs'}</span><span className="fs-line"></span></div>
              <Field label={ar?'حقل عادي':'Text'} req><Input placeholder={ar?'اكتب هنا…':'Type here…'} /></Field>
              <Field label={ar?'بحث':'Search'} optional lang={lang}><Input icon="search" placeholder={ar?'ابحث…':'Search…'} /></Field>
              <Field label={ar?'قائمة منسدلة':'Select'}><Select><option>{ar?'غزة':'Gaza City'}</option><option>{ar?'خان يونس':'Khan Younis'}</option><option>{ar?'رفح':'Rafah'}</option></Select></Field>
              <Field label={ar?'مبلغ (مع لاحقة)':'Amount (addon)'}><div className="input-group"><span className="ig-addon lead">{NIS}</span><input className="input ltr tnum" defaultValue="35" /><span className="ig-addon">{ar?'/شهر':'/mo'}</span></div></Field>
              <Field label={ar?'منطقة نص':'Textarea'}><textarea className="textarea" placeholder={ar?'اكتب وصفاً…':'Write a description…'}></textarea></Field>
            </div>
            <div className="card card-pad stack" style={{gap:16}}>
              <div className="form-section"><span className="fs-title">{ar?'الحالات':'States'}</span><span className="fs-line"></span></div>
              <Field label={ar?'حقل صحيح':'Valid'}><div className="field is-ok"><Input defaultValue={ar?'أحمد الفرا':'Ahmad Al-Farra'} /></div></Field>
              <Field label={ar?'به خطأ':'Error'} error={ar?'هذا الحقل مطلوب':'This field is required'}><Input defaultValue="" /></Field>
              <Field label={ar?'معطّل':'Disabled'}><Input disabled defaultValue={ar?'غير قابل للتعديل':'Not editable'} /></Field>
              <div className="form-section"><span className="fs-title">{ar?'اختيارات':'Choices'}</span><span className="fs-line"></span></div>
              <div className="row wrap" style={{gap:18, alignItems:'center'}}>
                <label className="check"><input type="checkbox" defaultChecked />{ar?'خيار':'Checkbox'}</label>
                <label className="check"><input type="radio" name="fnd-r" defaultChecked />{ar?'راديو ١':'Radio 1'}</label>
                <label className="check"><input type="radio" name="fnd-r" />{ar?'راديو ٢':'Radio 2'}</label>
                <label className="switch"><input type="checkbox" defaultChecked /><span className="track"></span><span className="thumb"></span></label>
              </div>
              <div className="form-section"><span className="fs-title">{ar?'رفع ملف':'File upload'}</span><span className="fs-line"></span></div>
              <label className="upload" style={{padding:18}}><Icon name="upload" /><span style={{fontWeight:600, color:'var(--text-2)', fontSize:'var(--fs-sm)'}}>{ar?'اسحب أو اضغط للرفع':'Drag or click to upload'}</span></label>
            </div>
          </div>
        </FSection>

        {/* 06 BADGES / AVATARS / TILES */}
        <FSection id="elements" n="06" title={ar?'الشارات والعناصر':'Badges, tiles & data points'} sub={ar?'حالات، أدوار، صور رمزية، شرائح، تقييم وبطاقات إحصاء.':'Statuses, roles, avatars, chips, ratings and stat tiles.'}>
          <div className="fnd-grid2">
            <div className="card card-pad stack" style={{gap:16}}>
              <Demo label={ar?'شارات الحالة':'Status badges'} col>
                <div className="row wrap" style={{gap:8}}><StatusBadge status="paid" lang={lang} /><StatusBadge status="pending" lang={lang} /><StatusBadge status="overdue" lang={lang} /></div>
                <div className="row wrap" style={{gap:8}}><StatusBadge status="active" lang={lang} /><StatusBadge status="approved" lang={lang} /><StatusBadge status="rejected" lang={lang} /><StatusBadge status="suspended" lang={lang} /></div>
              </Demo>
              <Demo label={ar?'شارات وأدوار':'Tonal badges & roles'}><Badge tone="info">{ar?'معلومة':'Info'}</Badge><Badge tone="neutral">{ar?'محايد':'Neutral'}</Badge><span className="pill-role"><Icon name="building" size={13} />{ar?'مالك':'Owner'}</span><span className="pill-role"><Icon name="user" size={13} />{ar?'عضو':'Member'}</span></Demo>
              <Demo label={ar?'شرائح التصفية':'Filter chips'}><span className="chip">{ar?'الحالة: نشط':'Status: Active'}<button><Icon name="x" size={13} /></button></span><span className="chip">{ar?'المدينة: غزة':'City: Gaza'}<button><Icon name="x" size={13} /></button></span></Demo>
              <Demo label={ar?'صور رمزية وتقييم':'Avatars & rating'}><Avatar initial="أ" round /><Avatar initial="ل" round tone="#1F82C7" /><Avatar initial="س" size="lg" round tone="#16A34A" /><Rating value={4.8} reviews={126} lang={lang} /></Demo>
            </div>
            <div className="stack" style={{gap:16}}>
              <StatTile icon="wallet" amber label={ar?'إيراد اليوم':'Today’s revenue'} value={`${NIS}640`} delta="12%" deltaDir="up" foot={ar?'مقابل أمس':'vs yesterday'} spark={[4,6,5,8,7,9,12]} />
              <div className="fnd-grid2" style={{gap:16}}>
                <StatTile icon="users" label={ar?'الأعضاء':'Members'} value="42" delta="3" deltaDir="up" foot={ar?'هذا الشهر':'this month'} />
                <StatTile icon="grid" amber label={ar?'الإشغال':'Occupancy'} value="84%" delta="2%" deltaDir="down" foot={ar?'هذا الأسبوع':'this week'} />
              </div>
            </div>
          </div>
        </FSection>

        {/* 07 FEEDBACK */}
        <FSection id="feedback" n="07" title={ar?'التنبيهات والرسائل':'Alerts, toasts & dialogs'} sub={ar?'بانرات تنبيه، إشعارات منبثقة حيّة، ونوافذ حوارية. جرّبها.':'Inline alert banners, live toasts, and dialogs. Try them.'}>
          <div className="fnd-grid2">
            <div className="card card-pad stack" style={{gap:12}}>
              <div className="fnd-label">{ar?'بانرات التنبيه':'Alert banners'}</div>
              <Alert tone="info" title={ar?'معلومة':'Heads up'}>{ar?'بياناتك محمية وتُستخدم للتحقق فقط.':'Your data is protected and used for verification only.'}</Alert>
              <Alert tone="success" title={ar?'تم بنجاح':'All set'}>{ar?'تم حفظ التغييرات.':'Your changes were saved.'}</Alert>
              <Alert tone="warning" title={ar?'تنبيه':'Reminder'} onClose={()=>{}}>{ar?'فاتورة تستحق خلال ٣ أيام.':'An invoice is due in 3 days.'}</Alert>
              <Alert tone="danger" title={ar?'خطأ':'Action needed'}>{ar?'فشل الدفع — تحقق من البطاقة.':'Payment failed — check your card.'}</Alert>
            </div>
            <div className="stack" style={{gap:16}}>
              <div className="card card-pad stack" style={{gap:12}}>
                <div className="fnd-label">{ar?'إشعارات منبثقة (حيّة)':'Toasts (live)'}</div>
                <div className="row wrap" style={{gap:8}}>
                  <Button variant="secondary" size="sm" icon="checkCircle" onClick={()=>toast({tone:'ok',title:ar?'تم الحفظ بنجاح':'Saved successfully'})}>{ar?'نجاح':'Success'}</Button>
                  <Button variant="secondary" size="sm" icon="bell" onClick={()=>toast({tone:'info',title:ar?'لديك إشعار جديد':'You have a notification'})}>{ar?'معلومة':'Info'}</Button>
                  <Button variant="secondary" size="sm" icon="xCircle" onClick={()=>toast({tone:'err',title:ar?'حدث خطأ ما':'Something went wrong'})}>{ar?'خطأ':'Error'}</Button>
                </div>
              </div>
              <div className="card card-pad stack" style={{gap:12}}>
                <div className="fnd-label">{ar?'النوافذ الحوارية (حيّة)':'Dialogs (live)'}</div>
                <div className="row wrap" style={{gap:8}}>
                  <Button variant="secondary" size="sm" icon="receipt" onClick={()=>setMOpen(true)}>{ar?'فتح نافذة':'Open modal'}</Button>
                  <Button variant="secondary" size="sm" icon="alert" onClick={()=>setConfirm(true)}>{ar?'تأكيد':'Confirm'}</Button>
                  <Button variant="secondary" size="sm" icon="user" onClick={()=>setDOpen(true)}>{ar?'فتح لوحة جانبية':'Open drawer'}</Button>
                </div>
              </div>
            </div>
          </div>
        </FSection>

        {/* 08 DATA DISPLAY */}
        <FSection id="data" n="08" title={ar?'عرض البيانات':'Data display'} sub={ar?'جدول بيانات كامل (فرز، تحديد، تصفية، ترقيم)، تبويبات، شرائح، ومسار تنقّل.':'Full datatable (sort, select, filter, paginate), tabs, segmented control, and breadcrumbs.'}>
          <div className="stack" style={{gap:18}}>
            <div className="row wrap" style={{gap:24, alignItems:'center'}}>
              <div className="stack" style={{gap:8}}><div className="fnd-label">{ar?'مسار التنقّل':'Breadcrumbs'}</div>
                <div className="crumbs"><a>{ar?'الرئيسية':'Home'}</a><Icon name="chevR" className="chev" size={14} /><a>{ar?'المساحات':'Spaces'}</a><Icon name="chevR" className="chev" size={14} /><span style={{color:'var(--text)'}}>{ar?'مساحة المنارة':'Al-Manara'}</span></div>
              </div>
              <div className="stack" style={{gap:8}}><div className="fnd-label">{ar?'تبويبات':'Tabs'}</div>
                <Tabs items={[{id:'a',label:ar?'نظرة عامة':'Overview'},{id:'b',label:ar?'المقاعد':'Seats'},{id:'c',label:ar?'التقييمات':'Reviews'}]} value={tab} onChange={setTab} />
              </div>
              <div className="stack" style={{gap:8}}><div className="fnd-label">{ar?'تحكّم مجزّأ':'Segmented'}</div>
                <Segmented items={[{id:'list',label:ar?'قائمة':'List'},{id:'map',label:ar?'خريطة':'Map'}]} value={seg} onChange={setSeg} />
              </div>
            </div>
            <div><div className="fnd-label">{ar?'جدول بيانات':'Datatable'}</div><FndTable lang={lang} /></div>
          </div>
        </FSection>

        {/* 09 SEAT MAP */}
        <FSection id="seatmap" n="09" title={ar?'خريطة المقاعد — التفاعل البطل':'Seat map — the hero interaction'} sub={ar?'شبكة من المربّعات بخمس حالات. جرّب اختيار مقعد متاح.':'A grid of tiles with five states. Try selecting an available seat.'}>
          <div className="fnd-grid2">
            <div className="card card-pad">
              <SeatMap seats={seats} selected={seatSel} onSelect={setSeatSel} cols={8} />
              <div className="divider" style={{margin:'18px 0 14px'}}></div>
              <SeatLegend lang={lang} />
            </div>
            <div className="card card-pad stack" style={{gap:12, justifyContent:'center'}}>
              <div className="fnd-label">{ar?'الحالة المختارة':'Selected state'}</div>
              {seatSel ? <div className="row" style={{gap:12}}><span className="seat is-selected" style={{width:54,height:54,cursor:'default'}}>{seatSel.split('-')[1]}</span><div><div style={{fontWeight:600}}>{ar?'المقعد':'Seat'} {seatSel}</div><div className="muted" style={{fontSize:'var(--fs-sm)'}}>{ar?'متاح · جاهز للتعيين':'Available · ready to assign'}</div></div></div>
              : <p className="muted" style={{fontSize:'var(--fs-sm)'}}>{ar?'اضغط على مقعد متاح (أبيض) في الخريطة.':'Click an available (white) seat in the map.'}</p>}
            </div>
          </div>
        </FSection>

        {/* 10 CHARTS & STATES */}
        <FSection id="charts" n="10" title={ar?'الرسوم والحالات':'Charts & states'} sub={ar?'رسوم خطية وعمودية ومؤشرات، مع حالات فارغة وتحميل.':'Line, bar and gauge charts, with empty & loading states.'}>
          <div className="fnd-grid3">
            <div className="card card-pad"><div className="fnd-label" style={{marginBottom:14}}>{ar?'رسم خطي':'Line'}</div><LineChart data={REVENUE_SERIES} lang={lang} h={150} /></div>
            <div className="card card-pad"><div className="fnd-label" style={{marginBottom:14}}>{ar?'رسم عمودي':'Bar'}</div><BarChart data={OCCUPANCY_SERIES} lang={lang} h={150} accentLast /></div>
            <div className="card card-pad"><div className="fnd-label" style={{marginBottom:14}}>{ar?'مؤشر':'Gauge'}</div><div style={{display:'grid',placeItems:'center'}}><Gauge value={72} size={132} label={ar?'الإشغال':'occupancy'} /></div></div>
          </div>
          <div className="fnd-grid2" style={{marginTop:16}}>
            <div className="card" style={{overflow:'hidden'}}><div className="empty-state"><EmptyArt /><div><div style={{fontWeight:600, color:'var(--text-2)'}}>{ar?'حالة فارغة':'Empty state'}</div><div style={{fontSize:'var(--fs-sm)'}}>{ar?'لا توجد بيانات بعد':'No data yet'}</div></div></div></div>
            <div className="card card-pad stack" style={{gap:10, justifyContent:'center'}}><div className="fnd-label">{ar?'حالة تحميل':'Loading state'}</div>{[80,95,70].map((w,i)=> <div key={i} className="skel" style={{height:16, width:`${w}%`}}></div>)}</div>
          </div>
        </FSection>

        <footer className="fnd-foot">
          <TileLogo size={30} />
          <p className="muted-3" style={{marginTop:16}}>{ar?'نظام تصميم طاقت · ٢٠٢٦':'TAQAT design system · 2026'}</p>
          <Button variant="primary" iconEnd="arrowR" onClick={()=>go('home')} style={{marginTop:16}}>{ar?'استعرض المنصة':'Explore the product'}</Button>
        </footer>
      </div>

      {/* live dialogs */}
      {mOpen && <Modal title={ar?'إصدار فاتورة جديدة':'Issue new invoice'} icon="receipt" onClose={()=>setMOpen(false)}
        footer={<><Button variant="ghost" onClick={()=>setMOpen(false)}>{ar?'إلغاء':'Cancel'}</Button><Button variant="primary" icon="send" onClick={()=>{setMOpen(false);toast({tone:'ok',title:ar?'تم إصدار الفاتورة':'Invoice issued'});}}>{ar?'إصدار':'Issue'}</Button></>}>
        <div className="stack" style={{gap:16}}>
          <Field label={ar?'العضو':'Member'} req><Select>{MEMBERS.slice(0,4).map(m=> <option key={m.id}>{ar?m.name:m.nameEn}</option>)}</Select></Field>
          <div className="grid2"><Field label={ar?'المبلغ':'Amount'} req><div className="input-group"><span className="ig-addon lead">{NIS}</span><input className="input ltr tnum" defaultValue="35" /></div></Field><Field label={ar?'الاستحقاق':'Due'}><Input className="ltr" defaultValue="2026-06-08" /></Field></div>
        </div>
      </Modal>}

      {confirm && <Modal title={ar?'تأكيد الإيقاف':'Confirm suspension'} icon="alert" onClose={()=>setConfirm(false)}
        footer={<><Button variant="ghost" onClick={()=>setConfirm(false)}>{ar?'تراجع':'Cancel'}</Button><Button variant="danger" icon="x" onClick={()=>{setConfirm(false);toast({tone:'info',title:ar?'تم الإيقاف':'Suspended'});}}>{ar?'تأكيد':'Confirm'}</Button></>}>
        <Alert tone="danger" title={ar?'إجراء لا يمكن التراجع عنه بسهولة':'This is hard to undo'}>{ar?'سيتم إيقاف وصول العضو إلى مقعده فوراً.':'The member will immediately lose access to their seat.'}</Alert>
      </Modal>}

      {dOpen && <Drawer title={ar?'تفاصيل العضو':'Member details'} onClose={()=>setDOpen(false)}
        footer={<><Button variant="ghost" onClick={()=>setDOpen(false)}>{ar?'إغلاق':'Close'}</Button><Button variant="primary">{ar?'حفظ':'Save'}</Button></>}>
        <div className="stack" style={{gap:0}}>
          <div className="drawer-section row" style={{gap:14}}><Avatar initial="أ" size="lg" round /><div><div className="h3">{ar?'أحمد الفرا':'Ahmad Al-Farra'}</div><div className="muted">{ar?'مطوّر واجهات':'Frontend developer'}</div><div style={{marginTop:6}}><StatusBadge status="active" lang={lang} /></div></div></div>
          <div className="drawer-section stack" style={{gap:12}}>
            {[[ar?'البريد':'Email','ahmad.f@mail.ps','ltr'],[ar?'المقعد':'Seat','A-04','tnum'],[ar?'الخطة':'Plan',ar?'شهري':'Monthly']].map(([k,v,c],i)=> <div key={i} className="between"><span className="muted">{k}</span><span className={c||''} style={{fontWeight:500}}>{v}</span></div>)}
          </div>
        </div>
      </Drawer>}
    </div>
  );
}

Object.assign(window, { Foundations, FndTable });
