/* TAQAT — reusable primitives. Depends on window.Icon. */
const { useState, useRef, useEffect } = React;

/* ---- Button ---- */
function Button({ variant='primary', size='md', icon, iconEnd, block, children, className='', ...rest }) {
  const sz = size==='sm'?'btn-sm':size==='lg'?'btn-lg':'';
  return (
    <button className={`btn btn-${variant} ${sz} ${block?'btn-block':''} ${className}`} {...rest}>
      {icon && <Icon name={icon} />}
      {children}
      {iconEnd && <Icon name={iconEnd} />}
    </button>
  );
}

/* ---- Badges ---- */
const STATUS_MAP = {
  active:{ cls:'badge-success', ar:'نشط', en:'Active' },
  expired:{ cls:'badge-neutral', ar:'منتهٍ', en:'Expired' },
  pending:{ cls:'badge-warning', ar:'قيد المراجعة', en:'Pending' },
  approved:{ cls:'badge-success', ar:'مقبول', en:'Approved' },
  rejected:{ cls:'badge-danger', ar:'مرفوض', en:'Rejected' },
  suspended:{ cls:'badge-danger', ar:'موقوف', en:'Suspended' },
  paid:{ cls:'badge-success', ar:'مدفوعة', en:'Paid' },
  overdue:{ cls:'badge-danger', ar:'متأخرة', en:'Overdue' },
};
function StatusBadge({ status, lang='ar', dot=true }) {
  const m = STATUS_MAP[status] || { cls:'badge-neutral', ar:status, en:status };
  return <span className={`badge ${m.cls}`}>{dot && <span className="dot"></span>}{lang==='ar'?m.ar:m.en}</span>;
}
function Badge({ tone='neutral', children, dot }) {
  return <span className={`badge badge-${tone}`}>{dot && <span className="dot"></span>}{children}</span>;
}

/* ---- Fields ---- */
function Field({ label, hint, error, children, htmlFor, req, optional, lang }) {
  return (
    <div className={`field ${error?'is-error':''} ${req?'req':''}`}>
      {label && <label className="label" htmlFor={htmlFor}>{label}{optional && <span className="label-opt">{lang==='en'?'optional':'اختياري'}</span>}</label>}
      {children}
      {(error||hint) && <span className="hint">{error||hint}</span>}
    </div>
  );
}
function Input({ icon, ...rest }) {
  if (icon) return <div className="input-icon"><Icon name={icon} /><input className="input" {...rest} /></div>;
  return <input className="input" {...rest} />;
}
function Select({ children, ...rest }) {
  return <div className="select-wrap"><select className="select" {...rest}>{children}</select><Icon name="chevD" size={16} /></div>;
}

/* ---- Avatar ---- */
function Avatar({ initial, size='md', round, tone }) {
  const sz = size==='lg'?'lg':size==='sm'?'sm':'';
  const style = tone ? { background:`color-mix(in srgb, ${tone} 16%, var(--surface))`, color:tone } : undefined;
  return <span className={`avatar ${sz} ${round?'round':''}`} style={style}>{initial}</span>;
}

/* ---- Stat tile ---- */
function StatTile({ icon, amber, label, value, delta, deltaDir, foot, spark }) {
  return (
    <div className="stat-tile">
      <div className="st-head">
        <span className="st-label">{label}</span>
        <span className={`st-ico ${amber?'amber':''}`}><Icon name={icon} /></span>
      </div>
      <div className="st-num tnum">{value}</div>
      <div className="st-foot">
        {delta && <span className={`delta ${deltaDir}`}><Icon name={deltaDir==='up'?'arrowUp':'arrowDown'} />{delta}</span>}
        {foot && <span className="muted-3">{foot}</span>}
      </div>
      {spark && <Sparkline data={spark} />}
    </div>
  );
}
function Sparkline({ data, w=180, h=34 }) {
  const max = Math.max(...data), min = Math.min(...data);
  const pts = data.map((v,i)=>`${(i/(data.length-1))*w},${h-((v-min)/(max-min||1))*(h-4)-2}`).join(' ');
  return (
    <svg className="chart" viewBox={`0 0 ${w} ${h}`} style={{height:h, marginTop:4}} preserveAspectRatio="none">
      <polyline className="line" style={{strokeWidth:2}} points={pts} />
    </svg>
  );
}

/* ---- Image placeholder (striped) ---- */
function ImgPlaceholder({ label, color='#dbe7f0', h=180, radius='var(--r-lg)', className='' }) {
  return (
    <div className={`imgph ${className}`} style={{height:h, borderRadius:radius, background:color}}>
      <span className="imgph-label">{label}</span>
    </div>
  );
}

/* ---- Amenity chip ---- */
function Amenity({ code, lang='ar' }) {
  const a = AMENITY_LABELS[code]; if (!a) return null;
  return <span className="amenity"><Icon name={a.ico} size={16} />{lang==='ar'?a.ar:a.en}</span>;
}

/* ---- Rating ---- */
function Rating({ value, reviews, lang='ar' }) {
  return (
    <span className="rating">
      <Icon name="star" size={15} className="star-fill" />
      <span className="tnum" style={{fontWeight:600}}>{value.toFixed(1)}</span>
      {reviews!=null && <span className="muted-3" style={{fontSize:'var(--fs-xs)'}}>({reviews} {lang==='ar'?'تقييم':'reviews'})</span>}
    </span>
  );
}

/* ---- Tabs / Segmented ---- */
function Tabs({ items, value, onChange }) {
  return (
    <div className="tabs">
      {items.map(it=> <button key={it.id} className={`tab ${value===it.id?'active':''}`} onClick={()=>onChange(it.id)}>{it.label}</button>)}
    </div>
  );
}
function Segmented({ items, value, onChange }) {
  return (
    <div className="seg">
      {items.map(it=> <button key={it.id} className={value===it.id?'active':''} onClick={()=>onChange(it.id)}>{it.label}</button>)}
    </div>
  );
}

/* ---- Modal ---- */
function Modal({ title, onClose, children, footer, icon }) {
  return (
    <div className="overlay" onClick={onClose}>
      <div className="modal" onClick={e=>e.stopPropagation()} role="dialog" aria-modal="true">
        <div className="modal-head">
          <div className="row" style={{gap:12}}>
            {icon && <span className="st-ico"><Icon name={icon} /></span>}
            <div><h3 className="h3">{title}</h3></div>
          </div>
          <button className="icon-btn" onClick={onClose} aria-label="إغلاق"><Icon name="x" /></button>
        </div>
        <div className="modal-body">{children}</div>
        {footer && <div className="modal-foot">{footer}</div>}
      </div>
    </div>
  );
}
function Drawer({ title, onClose, children, footer }) {
  return (
    <div className="drawer-wrap"><div className="overlay" onClick={onClose} style={{position:'absolute'}}></div>
      <div className="drawer" role="dialog" aria-modal="true">
        <div className="modal-head">
          <h3 className="h3">{title}</h3>
          <button className="icon-btn" onClick={onClose} aria-label="إغلاق"><Icon name="x" /></button>
        </div>
        <div className="modal-body grow" style={{overflowY: 'auto'}}>{children}</div>
        {footer && <div className="modal-foot">{footer}</div>}
      </div>
    </div>
  );
}

/* ---- Alert / inline banner ---- */
function Alert({ tone='info', title, children, icon, onClose, action }) {
  const ic = { info:'bell', success:'checkCircle', warning:'alert', danger:'xCircle' }[tone] || 'bell';
  return (
    <div className={`alert alert-${tone}`} role="status">
      <span className="alert-ico"><Icon name={icon||ic} size={18} /></span>
      <div className="grow">
        {title && <div className="alert-title">{title}</div>}
        {children && <div className="alert-body">{children}</div>}
      </div>
      {action}
      {onClose && <button className="alert-x" onClick={onClose} aria-label="close"><Icon name="x" size={15} /></button>}
    </div>
  );
}

/* ---- Toast host ---- */
function ToastHost({ toasts }) {
  const ic = { ok:'checkCircle', err:'xCircle', info:'bell' };
  return (
    <div className="toast-host">
      {toasts.map(t=> (
        <div key={t.id} className={`toast ${t.tone||'info'}`}>
          <span className="t-ico"><Icon name={ic[t.tone]||'bell'} size={18} /></span>
          <div className="grow"><div style={{fontWeight:600, fontSize:'var(--fs-sm)'}}>{t.title}</div>{t.body && <div className="muted-3" style={{fontSize:'var(--fs-xs)'}}>{t.body}</div>}</div>
        </div>
      ))}
    </div>
  );
}

/* ---- Seat map ---- */
function SeatMap({ seats, selected, onSelect, cols=8 }) {
  return (
    <div className="seatmap" style={{gridTemplateColumns:`repeat(${cols}, 1fr)`}}>
      {seats.map(s=> {
        const isSel = selected===s.id;
        const cls = s.state==='disabled'?'is-disabled':s.state==='occupied'?'is-occupied':s.state==='reserved'?'is-reserved':isSel?'is-selected':'';
        return (
          <button key={s.id} className={`seat ${cls}`} disabled={s.state==='disabled'||s.state==='occupied'}
            onClick={()=> onSelect && onSelect(s.id)} title={s.label}>
            {s.state==='occupied' ? <Icon name="user" size={15} /> : s.state==='disabled' ? <Icon name="x" size={14} /> : <span className="tnum">{s.label}</span>}
          </button>
        );
      })}
    </div>
  );
}
function SeatLegend({ lang='ar' }) {
  const L = lang==='ar'
    ? [['متاح','--seat-available','--seat-available-bd'],['محجوز لك','--seat-selected','--seat-selected-bd'],['مشغول','--seat-occupied','--seat-occupied-bd'],['محجوز مسبقاً','--seat-reserved','--seat-reserved-bd'],['غير متاح','--seat-disabled','--seat-disabled-bd']]
    : [['Available','--seat-available','--seat-available-bd'],['Selected','--seat-selected','--seat-selected-bd'],['Occupied','--seat-occupied','--seat-occupied-bd'],['Reserved','--seat-reserved','--seat-reserved-bd'],['Disabled','--seat-disabled','--seat-disabled-bd']];
  return (
    <div className="seat-legend">
      {L.map(([t,bg,bd])=> <span key={t} className="lg"><span className="sw" style={{background:`var(${bg})`, borderColor:`var(${bd})`}}></span>{t}</span>)}
    </div>
  );
}

/* ---- Charts ---- */
function LineChart({ data, lang='ar', h=220, suffix='k' }) {
  const w=560, pad=34;
  const vals = data.map(d=>d.v), max=Math.max(...vals)*1.15, min=0;
  const x = i => pad + (i/(data.length-1))*(w-pad*2);
  const y = v => h-26 - ((v-min)/(max-min))*(h-50);
  const line = data.map((d,i)=>`${x(i)},${y(d.v)}`).join(' ');
  const area = `${pad},${h-26} ${line} ${w-pad},${h-26}`;
  return (
    <svg className="chart" viewBox={`0 0 ${w} ${h}`} style={{width:'100%', height:h}}>
      <defs><linearGradient id="areaGrad" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stopColor="var(--primary)" stopOpacity="0.18"/><stop offset="100%" stopColor="var(--primary)" stopOpacity="0"/></linearGradient></defs>
      {[0,.25,.5,.75,1].map(t=> <line key={t} className="grid-line" x1={pad} x2={w-pad} y1={26+t*(h-52)} y2={26+t*(h-52)} />)}
      <polygon className="area" points={area} />
      <polyline className="line" points={line} />
      {data.map((d,i)=> <circle key={i} className="dot" cx={x(i)} cy={y(d.v)} r="3.5" />)}
      {data.map((d,i)=> <text key={i} className="chart-axis" x={x(i)} y={h-8} textAnchor="middle">{lang==='ar'?d.m:d.mEn}</text>)}
    </svg>
  );
}
function BarChart({ data, lang='ar', h=220, accentLast }) {
  const w=560, pad=30;
  const max=Math.max(...data.map(d=>d.v))*1.1;
  const bw=(w-pad*2)/data.length*0.56;
  const gap=(w-pad*2)/data.length;
  return (
    <svg className="chart" viewBox={`0 0 ${w} ${h}`} style={{width:'100%', height:h}}>
      {[0,.5,1].map(t=> <line key={t} className="grid-line" x1={pad} x2={w-pad} y1={20+t*(h-44)} y2={20+t*(h-44)} />)}
      {data.map((d,i)=> {
        const bh=((d.v)/max)*(h-44);
        const bx=pad+gap*i+(gap-bw)/2;
        return <g key={i}>
          <rect className={`bar ${accentLast&&i===data.length-1?'amber':''}`} x={bx} y={h-24-bh} width={bw} height={bh} rx="6" />
          <text className="chart-axis" x={bx+bw/2} y={h-8} textAnchor="middle">{lang==='ar'?(d.d||d.m):(d.dEn||d.mEn)}</text>
        </g>;
      })}
    </svg>
  );
}
function Gauge({ value, size=140, label }) {
  const r=size/2-12, c=2*Math.PI*r, off=c*(1-value/100);
  return (
    <div className="gauge" style={{width:size, textAlign:'center'}}>
      <svg width={size} height={size} viewBox={`0 0 ${size} ${size}`}>
        <circle className="gauge-track" cx={size/2} cy={size/2} r={r} strokeWidth="11" />
        <circle className="gauge-fill" cx={size/2} cy={size/2} r={r} strokeWidth="11"
          strokeDasharray={c} strokeDashoffset={off} transform={`rotate(-90 ${size/2} ${size/2})`} />
        <text x="50%" y="48%" textAnchor="middle" className="tnum" style={{fontSize:size*0.22, fontWeight:700, fill:'var(--text)'}}>{value}%</text>
        {label && <text x="50%" y="64%" textAnchor="middle" style={{fontSize:size*0.1, fill:'var(--text-3)'}}>{label}</text>}
      </svg>
    </div>
  );
}

Object.assign(window, {
  Button, Badge, StatusBadge, STATUS_MAP, Field, Input, Select, Avatar, StatTile, Sparkline,
  ImgPlaceholder, Amenity, Rating, Tabs, Segmented, Modal, Drawer, ToastHost, SeatMap, SeatLegend,
  LineChart, BarChart, Gauge, Alert,
});
