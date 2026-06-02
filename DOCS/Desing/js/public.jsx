/* TAQAT — public site: header/footer chrome, Home, Explore, Workspace detail */
const { useState:useStateP } = React;

/* ---- Tile logotype motif (hero) ---- */
function TileLogo({ size=54 }) {
  const tiles = ['T','A','Q','A','T'];
  return (
    <div className="tiles" style={{gap:size*0.14, fontSize:size}}>
      {tiles.map((c,i)=> i===2
        ? <span key={i} className="tile-glyph is-bulb" aria-label="Q"><Icon name="bulb" /></span>
        : <span key={i} className="tile-glyph">{c}</span>)}
    </div>
  );
}

function PublicHeader({ t, lang, setLang, theme, setTheme, go, route }) {
  const links = [
    { id:'explore', label:t.nav_explore },
    { id:'home', label:t.nav_how, anchor:'how' },
    { id:'home', label:t.nav_features, anchor:'feat' },
    { id:'home', label:t.nav_about, anchor:'about' },
  ];
  return (
    <header className="pub-header">
      <div className="container pub-header-in">
        <a className="logo" onClick={()=>go('home')} style={{cursor:'pointer'}}>
          <img src="assets/logo.png" alt="TAQAT" />
        </a>
        <nav className="pub-nav">
          {links.map((l,i)=> <a key={i} onClick={()=>go(l.id)} className="pub-nav-link">{l.label}</a>)}
        </nav>
        <div className="row" style={{gap:8}}>
          <button className="icon-btn" onClick={()=>setTheme(theme==='dark'?'light':'dark')} aria-label="theme">
            <Icon name={theme==='dark'?'sun':'moon'} />
          </button>
          <button className="lang-toggle" onClick={()=>setLang(lang==='ar'?'en':'ar')}>
            <Icon name="globe" size={16} />{t.other}
          </button>
          <Button variant="ghost" size="sm" onClick={()=>go('login')}>{t.login}</Button>
          <Button variant="primary" size="sm" onClick={()=>go('reg-free')}>{t.getStarted}</Button>
        </div>
      </div>
    </header>
  );
}

function PublicFooter({ t, lang, go }) {
  const cols = lang==='ar'
    ? [['المنصة',['استكشف المساحات','كيف تعمل','الأسعار','عن المنصة']],['للأعضاء',['سجّل كعامل مستقل','تطبيق الجوال','الأسئلة الشائعة']],['لأصحاب المساحات',['أضف مساحتك','لوحة التحكم','الدعم']]]
    : [['Platform',['Explore spaces','How it works','Pricing','About']],['For members',['Register as freelancer','Mobile app','FAQ']],['For owners',['List your space','Dashboard','Support']]];
  return (
    <footer className="pub-footer">
      <div className="container pub-footer-in">
        <div className="pub-footer-brand">
          <a className="logo" onClick={()=>go('home')} style={{cursor:'pointer'}}><img src="assets/logo.png" alt="TAQAT" /></a>
          <p className="muted" style={{maxWidth:260, marginTop:14}}>{t.footer_tag}</p>
        </div>
        {cols.map(([h,items],i)=> (
          <div key={i} className="pub-footer-col">
            <h4 className="pf-h">{h}</h4>
            {items.map((it,j)=> <a key={j} className="pf-link">{it}</a>)}
          </div>
        ))}
      </div>
      <div className="container pub-footer-bottom">
        <span className="muted-3">© 2026 TAQAT.space</span>
        <span className="muted-3 ltr">taqat.space</span>
      </div>
    </footer>
  );
}

/* =========================================================== HOME */
function Home({ t, lang, go }) {
  const ar = lang==='ar';
  const stats = [
    { n:'+128', l:t.stat_spaces },
    { n:'3,400', l:t.stat_free },
    { n:'72%', l:t.stat_occ },
    { n:'24/7', l:ar?'وصول دائم':'always open' },
  ];
  const featured = WORKSPACES.slice(0,3);
  const whys = [t.why1, t.why2, t.why3, t.why4];
  return (
    <div className="pub">
      {/* HERO */}
      <section className="ed-hero">
        <div className="container ed-hero-in">
          <div className="ed-hero-copy">
            <span className="ed-pill"><span className="dot-amber"></span>{t.hero_kicker}</span>
            <h1 className="ed-title">
              {t.hero_t1} <span className="hl">{t.hero_t2}</span>{t.hero_t3}
            </h1>
            <p className="ed-lead">{t.hero_sub}</p>
            <div className="row wrap" style={{gap:12, marginTop:4}}>
              <Button variant="primary" size="lg" icon="user" onClick={()=>go('reg-free')}>{t.hero_cta1}</Button>
              <Button variant="secondary" size="lg" icon="building" onClick={()=>go('reg-space')}>{t.hero_cta2}</Button>
            </div>
            <div className="ed-stats">
              {stats.map((s,i)=> (
                <div key={i} className="ed-stat">
                  <div className="ed-stat-n tnum">{s.n}</div>
                  <div className="ed-stat-l">{s.l}</div>
                </div>
              ))}
            </div>
          </div>

          <div className="ed-collage">
            <div className="ed-img-main"><ImgPlaceholder label={ar?'مساحة عمل':'workspace'} color="#cfe0ee" h={420} radius="var(--r-2xl)" /></div>
            <div className="ed-img-sm"><ImgPlaceholder label={ar?'منطقة الجلوس':'lounge'} color="#e6ddd0" h={196} radius="var(--r-2xl)" /></div>
            {/* floating live-seats card */}
            <div className="ed-float-card card">
              <div className="between" style={{marginBottom:10}}>
                <div className="row" style={{gap:8}}><span className="st-ico amber" style={{width:28,height:28}}><Icon name="grid" size={15} /></span><span style={{fontWeight:600, fontSize:'var(--fs-sm)'}}>{ar?'مقاعد متاحة الآن':'Seats free now'}</span></div>
                <span className="badge badge-success" style={{height:20}}><span className="dot"></span>١٢</span>
              </div>
              <HeroSeatMini />
            </div>
            {/* floating satisfaction badge */}
            <div className="ed-float-badge">
              <div className="ed-badge-n tnum">{t.satisfaction}</div>
              <div className="ed-badge-l">{t.why_stat}</div>
            </div>
          </div>
        </div>
      </section>

      {/* MARQUEE */}
      <Marquee lang={lang} />

      {/* WHY / ABOUT */}
      <section className="container section" id="about">
        <div className="why-grid">
          <div className="why-collage">
            <ImgPlaceholder label={ar?'داخل المساحة':'inside the space'} color="#d3dde7" h={300} radius="var(--r-2xl)" className="why-img-1" />
            <ImgPlaceholder label={ar?'فريق يعمل':'team at work'} color="#e7ddd2" h={190} radius="var(--r-2xl)" className="why-img-2" />
            <div className="why-blob">
              <Icon name="bulb" size={22} />
              <div className="ed-badge-n tnum" style={{marginTop:6}}>+3.4k</div>
              <div className="ed-badge-l">{ar?'عضو نشط':'active members'}</div>
            </div>
          </div>
          <div className="why-copy">
            <span className="eyebrow">{t.why_eyebrow}</span>
            <h2 className="ed-h2">{t.why_title1} <span className="hl">{t.why_title2}</span></h2>
            <p className="muted" style={{fontSize:'var(--fs-lg)', lineHeight:1.8, marginTop:14}}>{t.why_sub}</p>
            <div className="check-list">
              {whys.map((w,i)=> <div key={i} className="check-row"><span className="check-mark"><Icon name="check" size={15} /></span>{w}</div>)}
            </div>
          </div>
        </div>
      </section>

      {/* CAPABILITIES — numbered services */}
      <section className="caps-band" id="feat">
        <div className="container">
          <div className="caps-head">
            <span className="eyebrow">{t.caps_eyebrow}</span>
            <h2 className="ed-h2">{t.caps_title1} <span className="hl">{t.caps_title2}</span></h2>
          </div>
          <div className="caps-grid">
            {CAPABILITIES.map((c,i)=> (
              <div key={i} className="cap-card">
                <div className="cap-top"><span className="cap-num tnum">{c.n}</span><span className="cap-ico"><Icon name={c.ico} size={22} /></span></div>
                <h3 className="h3" style={{marginTop:18}}>{ar?c.ar:c.en}</h3>
                <p className="muted" style={{marginTop:8, lineHeight:1.7}}>{ar?c.arD:c.enD}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* FEATURED SPACES — bento */}
      <section className="container section">
        <div className="page-head" style={{alignItems:'flex-end'}}>
          <div>
            <span className="eyebrow">{ar?'مساحات مختارة':'Featured'}</span>
            <h2 className="ed-h2" style={{marginTop:8}}>{t.featured}</h2>
          </div>
          <Button variant="ghost" iconEnd="arrowR" onClick={()=>go('explore')}>{t.viewAll}</Button>
        </div>
        <div className="ws-grid">
          {featured.map(w=> <WorkspaceCard key={w.id} w={w} t={t} lang={lang} go={go} />)}
        </div>
      </section>

      {/* TESTIMONIALS */}
      <section className="tstm-band">
        <div className="container">
          <div style={{textAlign:'center', maxWidth:560, margin:'0 auto 44px'}}>
            <span className="eyebrow">{t.tstm_eyebrow}</span>
            <h2 className="ed-h2" style={{marginTop:8}}>{t.tstm_title1} <span className="hl">{t.tstm_title2}</span></h2>
          </div>
          <div className="tstm-grid">
            {TESTIMONIALS.map((m,i)=> (
              <div key={i} className="tstm-card">
                <div className="tstm-quote"><Icon name="megaphone" size={20} /></div>
                <p className="tstm-text">{ar?m.ar:m.en}</p>
                <div className="row" style={{gap:11, marginTop:'auto', paddingTop:20}}>
                  <Avatar initial={m.avatar} round />
                  <div><div style={{fontWeight:600, fontSize:'var(--fs-sm)'}}>{ar?m.name:m.nameEn}</div><div className="muted-3" style={{fontSize:'var(--fs-xs)'}}>{ar?m.role:m.roleEn}</div></div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* CTA band */}
      <section className="container">
        <div className="cta-band">
          <div className="cta-bulb"><Icon name="bulb" size={30} /></div>
          <h2 className="h1" style={{maxWidth:600, color:'#fff'}}>{t.cta_band_t}</h2>
          <p style={{maxWidth:480, opacity:.85}}>{t.cta_band_d}</p>
          <div className="row wrap" style={{gap:12, justifyContent:'center', marginTop:8}}>
            <Button variant="accent" size="lg" onClick={()=>go('reg-free')}>{t.hero_cta1}</Button>
            <Button variant="secondary" size="lg" onClick={()=>go('reg-space')}>{t.hero_cta2}</Button>
          </div>
        </div>
      </section>
    </div>
  );
}

function Marquee({ lang }) {
  const ar = lang==='ar';
  const items = [...MARQUEE, ...MARQUEE];
  return (
    <div className="marquee" aria-hidden="true">
      <div className="marquee-track">
        {items.map((m,i)=> (
          <span key={i} className="marquee-item">{ar?m[0]:m[1]}<span className="marquee-star">✦</span></span>
        ))}
      </div>
    </div>
  );
}

function HeroSeatMini() {
  const states = ['a','a','o','a','s','a','o','a','a','r','a','a','o','a','a','a'];
  const map = { a:'', o:'is-occupied', s:'is-selected', r:'is-reserved', d:'is-disabled' };
  return (
    <div className="seatmap" style={{gridTemplateColumns:'repeat(8,1fr)', gap:5}}>
      {states.map((s,i)=> <div key={i} className={`seat ${map[s]}`} style={{fontSize:10, borderWidth:1.5, cursor:'default'}}>
        {s==='o'?<Icon name="user" size={11} />:s==='d'?'':<span className="tnum">{i+1}</span>}
      </div>)}
    </div>
  );
}

/* ---- Workspace card ---- */
function WorkspaceCard({ w, t, lang, go }) {
  return (
    <div className="card card-hover ws-card" onClick={()=>go('detail',{id:w.id})}>
      <div className="ws-card-img">
        <ImgPlaceholder label={lang==='ar'?'صورة المساحة':'workspace photo'} color={w.img} h={158} radius="0" />
        {w.tag && <span className="ws-tag">{lang==='ar'?w.tag:w.tagEn}</span>}
      </div>
      <div className="ws-card-body">
        <div className="between">
          <h3 className="h3" style={{fontSize:'var(--fs-lg)'}}>{lang==='ar'?w.name:w.nameEn}</h3>
          <Rating value={w.rating} lang={lang} />
        </div>
        <div className="row muted" style={{gap:6, fontSize:'var(--fs-sm)'}}>
          <Icon name="pin" size={15} />{lang==='ar'?`${w.city} · ${w.area}`:`${w.cityEn} · ${w.area}`}
        </div>
        <div className="ws-amen">
          {w.amen.slice(0,3).map(a=> <Amenity key={a} code={a} lang={lang} />)}
          {w.amen.length>3 && <span className="muted-3" style={{fontSize:'var(--fs-xs)'}}>+{w.amen.length-3}</span>}
        </div>
        <div className="divider" style={{margin:'2px 0'}}></div>
        <div className="between">
          <div><span className="ws-price tnum">{NIS}{w.price}</span><span className="muted-3" style={{fontSize:'var(--fs-xs)'}}>{t.perDay}</span></div>
          <span className="badge badge-info"><span className="dot"></span>{w.occ}% {t.occupancy}</span>
        </div>
      </div>
    </div>
  );
}

/* =========================================================== EXPLORE */
function Explore({ t, lang, go }) {
  const [city, setCity] = useStateP('all');
  const [sort, setSort] = useStateP('rating');
  const [active, setActive] = useStateP(WORKSPACES[0].id);
  const cities = lang==='ar'
    ? {all:'كل المدن', 'غزة':'غزة','خان يونس':'خان يونس','رفح':'رفح','جباليا':'جباليا','دير البلح':'دير البلح','النصيرات':'النصيرات'}
    : {all:'All cities','غزة':'Gaza City','خان يونس':'Khan Younis','رفح':'Rafah','جباليا':'Jabalia','دير البلح':'Deir al-Balah','النصيرات':'Nuseirat'};
  let list = WORKSPACES.filter(w=> city==='all'||w.city===city);
  list = [...list].sort((a,b)=> sort==='rating'?b.rating-a.rating: sort==='price'?a.price-b.price: b.occ-a.occ);
  return (
    <div className="pub">
      <div className="explore-head">
        <div className="container">
          <h1 className="h1">{lang==='ar'?'استكشف المساحات':'Explore spaces'}</h1>
          <p className="muted" style={{marginTop:4}}>{lang==='ar'?`${WORKSPACES.length} مساحة عمل عبر قطاع غزة`:`${WORKSPACES.length} coworking spaces across Gaza`}</p>
          {/* filter bar */}
          <div className="filter-bar">
            <div className="input-icon grow" style={{maxWidth:300}}>
              <Icon name="search" /><input className="input" placeholder={lang==='ar'?'ابحث باسم المساحة أو المنطقة…':'Search by name or area…'} />
            </div>
            <Select value={city} onChange={e=>setCity(e.target.value)}>
              {Object.entries(cities).map(([k,v])=> <option key={k} value={k}>{v}</option>)}
            </Select>
            <Select defaultValue="any">
              <option value="any">{lang==='ar'?'كل الأسعار':'Any price'}</option>
              <option>{NIS}0–20</option><option>{NIS}20–30</option><option>{NIS}30+</option>
            </Select>
            <button className="btn btn-secondary btn-sm"><Icon name="filter" size={16} />{lang==='ar'?'المرافق':'Amenities'}</button>
            <div className="grow"></div>
            <div className="row" style={{gap:8}}>
              <span className="muted-3" style={{fontSize:'var(--fs-sm)'}}>{lang==='ar'?'ترتيب:':'Sort:'}</span>
              <Select value={sort} onChange={e=>setSort(e.target.value)} style={{width:150}}>
                <option value="rating">{lang==='ar'?'الأعلى تقييماً':'Top rated'}</option>
                <option value="price">{lang==='ar'?'الأقل سعراً':'Lowest price'}</option>
                <option value="occ">{lang==='ar'?'الأكثر إشغالاً':'Most active'}</option>
              </Select>
            </div>
          </div>
        </div>
      </div>

      <div className="container explore-split">
        <div className="explore-list">
          {list.map(w=> (
            <div key={w.id} className={`card card-hover explore-row ${active===w.id?'is-active':''}`}
              onMouseEnter={()=>setActive(w.id)} onClick={()=>go('detail',{id:w.id})}>
              <ImgPlaceholder label={lang==='ar'?'صورة':'photo'} color={w.img} h={104} radius="var(--r-md)" className="explore-row-img" />
              <div className="grow stack" style={{gap:6}}>
                <div className="between">
                  <h3 className="h3" style={{fontSize:'var(--fs-lg)'}}>{lang==='ar'?w.name:w.nameEn}</h3>
                  <Rating value={w.rating} reviews={w.reviews} lang={lang} />
                </div>
                <div className="row muted" style={{gap:6, fontSize:'var(--fs-sm)'}}><Icon name="pin" size={15} />{lang==='ar'?`${w.city} · ${w.area}`:`${w.cityEn} · ${w.area}`}</div>
                <div className="ws-amen">{w.amen.map(a=> <Amenity key={a} code={a} lang={lang} />)}</div>
                <div className="between" style={{marginTop:2}}>
                  <div><span className="ws-price tnum">{NIS}{w.price}</span><span className="muted-3" style={{fontSize:'var(--fs-xs)'}}>{t.perDay}</span></div>
                  <span className="badge badge-info">{w.occ}% {t.occupancy}</span>
                </div>
              </div>
            </div>
          ))}
        </div>
        <div className="explore-map">
          <div className="map-canvas">
            <ImgPlaceholder label={lang==='ar'?'خريطة تفاعلية':'interactive map'} color="#dde6ec" h={520} radius="var(--r-lg)" />
            {WORKSPACES.map((w,i)=> (
              <button key={w.id} className={`map-pin ${active===w.id?'is-active':''}`} onClick={()=>setActive(w.id)}
                style={{top:`${22+(i*12)%62}%`, insetInlineStart:`${18+(i*15)%60}%`}}>
                <span className="tnum">{NIS}{w.price}</span>
              </button>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}

/* =========================================================== DETAIL */
function WorkspaceDetail({ t, lang, go, params }) {
  const w = WORKSPACES.find(x=>x.id===params?.id) || WORKSPACES[0];
  const [tab, setTab] = useStateP('overview');
  const tabs = lang==='ar'
    ? [{id:'overview',label:'نظرة عامة'},{id:'seats',label:'المقاعد والأسعار'},{id:'reviews',label:'التقييمات'}]
    : [{id:'overview',label:'Overview'},{id:'seats',label:'Seats & pricing'},{id:'reviews',label:'Reviews'}];
  return (
    <div className="pub">
      <div className="container" style={{paddingTop:24}}>
        <div className="crumbs" style={{marginBottom:16}}>
          <a onClick={()=>go('home')}>{lang==='ar'?'الرئيسية':'Home'}</a><Icon name="chevR" className="chev" size={14} />
          <a onClick={()=>go('explore')}>{lang==='ar'?'استكشف':'Explore'}</a><Icon name="chevR" className="chev" size={14} />
          <span style={{color:'var(--text)'}}>{lang==='ar'?w.name:w.nameEn}</span>
        </div>
        {/* gallery */}
        <div className="gallery">
          <ImgPlaceholder label={lang==='ar'?'صورة رئيسية':'main photo'} color={w.img} h={320} radius="var(--r-lg)" className="gallery-main" />
          <div className="gallery-side">
            <ImgPlaceholder label={lang==='ar'?'صورة':'photo'} color="#e4ddd4" h={152} radius="var(--r-lg)" />
            <ImgPlaceholder label={lang==='ar'?'صورة':'photo'} color="#dde7e1" h={152} radius="var(--r-lg)" />
          </div>
        </div>

        <div className="detail-split">
          <div className="detail-main">
            <div className="between" style={{alignItems:'flex-start'}}>
              <div>
                <h1 className="h1">{lang==='ar'?w.name:w.nameEn}</h1>
                <div className="row muted" style={{gap:8, marginTop:8}}>
                  <span className="row" style={{gap:5}}><Icon name="pin" size={16} />{lang==='ar'?`${w.city} · ${w.area}`:`${w.cityEn} · ${w.area}`}</span>
                  <span>·</span><Rating value={w.rating} reviews={w.reviews} lang={lang} />
                </div>
              </div>
              {w.tag && <span className="badge badge-warning"><Icon name="star" size={13} />{lang==='ar'?w.tag:w.tagEn}</span>}
            </div>

            <div className="tabs" style={{marginTop:24}}>
              {tabs.map(tb=> <button key={tb.id} className={`tab ${tab===tb.id?'active':''}`} onClick={()=>setTab(tb.id)}>{tb.label}</button>)}
            </div>

            {tab==='overview' && <div className="stack" style={{gap:24, marginTop:24}}>
              <p style={{lineHeight:1.8}}>{lang==='ar'
                ? 'مساحة عمل حديثة في قلب المدينة، مصمّمة للعاملين المستقلين والفرق الصغيرة. تتميّز بإنترنت سريع ومستقر، إضاءة طبيعية، قاعات اجتماعات، وأجواء هادئة تساعد على التركيز والإنتاجية طوال اليوم.'
                : 'A modern coworking space in the heart of the city, designed for freelancers and small teams. Fast, stable internet, natural light, meeting rooms, and a calm atmosphere built for focus and productivity all day long.'}</p>
              <div>
                <h3 className="h3" style={{marginBottom:14}}>{lang==='ar'?'المرافق':'Amenities'}</h3>
                <div className="amen-grid">
                  {Object.keys(AMENITY_LABELS).map(a=> (
                    <div key={a} className={`amen-item ${w.amen.includes(a)?'':'is-off'}`}>
                      <Icon name={AMENITY_LABELS[a].ico} size={18} />{lang==='ar'?AMENITY_LABELS[a].ar:AMENITY_LABELS[a].en}
                      {w.amen.includes(a) ? <Icon name="check" size={15} className="amen-yes" /> : <Icon name="x" size={14} className="amen-no" />}
                    </div>
                  ))}
                </div>
              </div>
            </div>}

            {tab==='seats' && <div className="stack" style={{gap:16, marginTop:24}}>
              <h3 className="h3">{lang==='ar'?'أنواع المقاعد والأسعار':'Seat types & pricing'}</h3>
              <div className="plan-grid">{PLANS.map(p=> <PlanCard key={p.id} p={p} lang={lang} t={t} />)}</div>
            </div>}

            {tab==='reviews' && <div className="stack" style={{gap:14, marginTop:24}}>
              {[['أحمد الفرا','أ',5,'مكان ممتاز للتركيز، الإنترنت سريع جداً والقهوة مجانية. أنصح به بشدة.'],['ليان أبو شاويش','ل',4,'أجواء هادئة ومريحة، فقط القاعات تحتاج حجز مسبق في أوقات الذروة.'],['سارة النجار','س',5,'أفضل مساحة عمل جرّبتها في غزة، الطاقم متعاون والموقع مركزي.']].map((r,i)=> (
                <div key={i} className="card card-pad review">
                  <div className="row" style={{gap:10}}>
                    <Avatar initial={r[1]} round /><div className="grow"><div style={{fontWeight:600}}>{r[0]}</div><div className="rating">{Array.from({length:5}).map((_,j)=> <Icon key={j} name="star" size={13} className={j<r[2]?'star-fill':''} style={j<r[2]?{}:{color:'var(--border-strong)'}} />)}</div></div>
                  </div>
                  <p className="muted" style={{marginTop:10}}>{r[3]}</p>
                </div>
              ))}
            </div>}
          </div>

          {/* sticky book */}
          <aside className="detail-aside">
            <div className="card book-card">
              <div className="row" style={{gap:8, alignItems:'baseline'}}>
                <span className="ws-price tnum" style={{fontSize:'2rem'}}>{NIS}{w.price}</span>
                <span className="muted">{t.perDay}</span>
              </div>
              <div className="muted-3" style={{fontSize:'var(--fs-sm)'}}>{lang==='ar'?'أو اشتراك شهري من':'or monthly from'} <span className="tnum" style={{fontWeight:600,color:'var(--text)'}}>{NIS}35</span></div>
              <div className="divider" style={{margin:'16px 0'}}></div>
              <Field label={lang==='ar'?'نوع المقعد':'Seat type'}><Select><option>{lang==='ar'?'مقعد مفتوح':'Open seat'}</option><option>{lang==='ar'?'مكتب ثابت':'Fixed desk'}</option><option>{lang==='ar'?'مكتب خاص':'Private office'}</option></Select></Field>
              <div style={{height:12}}></div>
              <Field label={lang==='ar'?'التاريخ':'Date'}><Input icon="calendar" defaultValue="2026-06-02" /></Field>
              <div style={{height:16}}></div>
              <Button variant="primary" size="lg" block icon="calendar" onClick={()=>go('reg-free')}>{t.book}</Button>
              <p className="muted-3" style={{fontSize:'var(--fs-xs)', textAlign:'center', marginTop:10}}>{lang==='ar'?'لن يتم خصم أي مبلغ قبل تأكيد المالك':'You won’t be charged until the owner confirms'}</p>
            </div>
          </aside>
        </div>
      </div>
    </div>
  );
}

function PlanCard({ p, lang, t, onChoose }) {
  return (
    <div className={`card plan-card ${p.popular?'is-popular':''}`}>
      {p.popular && <span className="plan-badge">{lang==='ar'?'الأكثر اختياراً':'Most popular'}</span>}
      <div className="plan-name">{lang==='ar'?p.name:p.nameEn}</div>
      <div className="row" style={{gap:6, alignItems:'baseline'}}>
        <span className="ws-price tnum" style={{fontSize:'1.9rem'}}>{NIS}{p.price}</span>
        <span className="muted-3" style={{fontSize:'var(--fs-sm)'}}>/ {p.unit}</span>
      </div>
      <div className="divider" style={{margin:'14px 0'}}></div>
      <div className="stack" style={{gap:10}}>
        {p.feats.map((f,i)=> <div key={i} className="row" style={{gap:9, fontSize:'var(--fs-sm)'}}><Icon name="check" size={16} style={{color:'var(--success)'}} />{f}</div>)}
      </div>
      <div style={{height:16}}></div>
      <Button variant={p.popular?'primary':'secondary'} block onClick={onChoose}>{lang==='ar'?'اختر الخطة':'Choose plan'}</Button>
    </div>
  );
}

Object.assign(window, { TileLogo, PublicHeader, PublicFooter, Home, Marquee, Explore, WorkspaceDetail, WorkspaceCard, PlanCard });
