import type { CSSProperties } from "react";

/** Lucide-style line icons, 1.5px stroke. RTL mirrors directional ones. */
export const ICON_PATHS = {
  home: '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M9.5 21v-6h5v6"/>',
  search: '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.2-3.2"/>',
  map: '<path d="m9 4-6 2v14l6-2 6 2 6-2V4l-6 2-6-2Z"/><path d="M9 4v14M15 6v14"/>',
  pin: '<path d="M12 21s-6-5.3-6-10a6 6 0 1 1 12 0c0 4.7-6 10-6 10Z"/><circle cx="12" cy="11" r="2.2"/>',
  users:
    '<path d="M16 19v-1.5a3.5 3.5 0 0 0-3.5-3.5h-5A3.5 3.5 0 0 0 4 17.5V19"/><circle cx="10" cy="8" r="3.2"/><path d="M20 19v-1.5a3.5 3.5 0 0 0-2.6-3.4"/><path d="M15.5 5.2a3.2 3.2 0 0 1 0 5.6"/>',
  user: '<circle cx="12" cy="8" r="3.5"/><path d="M5 20v-1a4 4 0 0 1 4-4h6a4 4 0 0 1 4 4v1"/>',
  receipt:
    '<path d="M5 3v18l2-1.2L9 21l2-1.2L13 21l2-1.2L17 21l2-1.2V3l-2 1.2L15 3l-2 1.2L11 3 9 4.2 7 3 5 4.2Z"/><path d="M8 8h8M8 12h8M8 16h5"/>',
  grid: '<rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/>',
  wifi: '<path d="M2 8.5a16 16 0 0 1 20 0"/><path d="M5 12a11 11 0 0 1 14 0"/><path d="M8.5 15.5a6 6 0 0 1 7 0"/><circle cx="12" cy="19" r="1"/>',
  chat: '<path d="M4 5h16v11H8l-4 3.5Z"/><path d="M8 9.5h8M8 12.5h5"/>',
  megaphone:
    '<path d="M4 10v4a1 1 0 0 0 1 1h2l9 4V5L7 9H5a1 1 0 0 0-1 1Z"/><path d="M16 8.5a4 4 0 0 1 0 7"/><path d="M7 15v3.5a1.5 1.5 0 0 0 3 0V16"/>',
  inbox:
    '<path d="M4 13 6 5h12l2 8"/><path d="M4 13v6h16v-6"/><path d="M4 13h5l1.5 2.5h3L15 13h5"/>',
  chart: '<path d="M4 4v16h16"/><path d="M8 16v-4M12 16V8M16 16v-6"/>',
  settings:
    '<circle cx="12" cy="12" r="3"/><path d="M12 2.5v3M12 18.5v3M21.5 12h-3M5.5 12h-3M18.4 5.6l-2.1 2.1M7.7 16.3l-2.1 2.1M18.4 18.4l-2.1-2.1M7.7 7.7 5.6 5.6"/>',
  building:
    '<rect x="5" y="3.5" width="14" height="17" rx="1.5"/><path d="M9 7.5h2M13 7.5h2M9 11h2M13 11h2M9 14.5h2M13 14.5h2"/><path d="M10 20.5v-3h4v3"/>',
  shield: '<path d="M12 3 5 6v5.5c0 4 3 7.5 7 9 4-1.5 7-5 7-9V6Z"/><path d="m9 12 2 2 4-4"/>',
  wallet:
    '<rect x="3.5" y="6" width="17" height="13" rx="2"/><path d="M3.5 9.5h17"/><circle cx="16.5" cy="14" r="1.3"/>',
  bell: '<path d="M6 9a6 6 0 0 1 12 0c0 5 2 6 2 6H4s2-1 2-6Z"/><path d="M10 19a2 2 0 0 0 4 0"/>',
  calendar:
    '<rect x="3.5" y="5" width="17" height="16" rx="2"/><path d="M3.5 9.5h17M8 3v4M16 3v4"/>',
  chevR: '<path d="m9 6 6 6-6 6"/>',
  chevL: '<path d="m15 6-6 6 6 6"/>',
  chevD: '<path d="m6 9 6 6 6-6"/>',
  arrowUp: '<path d="M12 19V5M6 11l6-6 6 6"/>',
  arrowDown: '<path d="M12 5v14M6 13l6 6 6-6"/>',
  arrowL: '<path d="M19 12H5M11 6l-6 6 6 6"/>',
  arrowR: '<path d="M5 12h14M13 6l6 6-6 6"/>',
  check: '<path d="m5 12 5 5 9-11"/>',
  checkCircle: '<circle cx="12" cy="12" r="9"/><path d="m8.5 12 2.3 2.3L16 9"/>',
  x: '<path d="M6 6 18 18M18 6 6 18"/>',
  xCircle: '<circle cx="12" cy="12" r="9"/><path d="m9 9 6 6M15 9l-6 6"/>',
  alert: '<path d="M12 3 2.5 20h19Z"/><path d="M12 10v4M12 17.5v.5"/>',
  clock: '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
  plus: '<path d="M12 5v14M5 12h14"/>',
  filter: '<path d="M3 5h18l-7 8v6l-4-2v-4Z"/>',
  star: '<path d="m12 3 2.6 5.4 5.9.8-4.3 4.1 1 5.9L12 16.6 6.8 19.3l1-5.9L3.5 9.2l5.9-.8Z"/>',
  download: '<path d="M12 4v11M8 11l4 4 4-4"/><path d="M5 20h14"/>',
  upload: '<path d="M12 16V5M8 9l4-4 4 4"/><path d="M5 20h14"/>',
  more: '<circle cx="5" cy="12" r="1.4"/><circle cx="12" cy="12" r="1.4"/><circle cx="19" cy="12" r="1.4"/>',
  edit: '<path d="M5 19h14M14 5.5l3.5 3.5L9 17.5l-4 1 1-4Z"/>',
  trash: '<path d="M4 7h16M9 7V5h6v2M6 7l1 13h10l1-13"/>',
  eye: '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="3"/>',
  eyeOff:
    '<path d="M3 3l18 18"/><path d="M10.6 6.1A9 9 0 0 1 12 6c6 0 9.5 6 9.5 6a16 16 0 0 1-3.2 3.9"/><path d="M6.6 6.6A16 16 0 0 0 2.5 12S6 18 12 18a9 9 0 0 0 3.4-.7"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>',
  mail: '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/>',
  lock: '<rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
  phone:
    '<path d="M5 4h4l1.5 4-2 1.5a11 11 0 0 0 5 5l1.5-2 4 1.5v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2Z"/>',
  bulb: '<path d="M9 18h6M10 21h4"/><path d="M12 3a6 6 0 0 0-4 10.5c.8.8 1 1.2 1 2.5h6c0-1.3.2-1.7 1-2.5A6 6 0 0 0 12 3Z"/>',
  coffee:
    '<path d="M4 8h13v5a5 5 0 0 1-5 5H9a5 5 0 0 1-5-5Z"/><path d="M17 9h2.5a2.5 2.5 0 0 1 0 5H17"/><path d="M7 3.5v1.5M11 3.5v1.5"/>',
  printer:
    '<path d="M7 9V4h10v5"/><rect x="5" y="9" width="14" height="7" rx="1.5"/><path d="M7 14h10v6H7Z"/>',
  zap: '<path d="M13 3 5 13h6l-1 8 8-10h-6Z"/>',
  send: '<path d="M21 4 3 11l7 2 2 7Z"/><path d="m10 13 4-4"/>',
  trend: '<path d="M4 16 10 10l3 3 7-7"/><path d="M16 6h4v4"/>',
  logout:
    '<path d="M14 4h4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-4"/><path d="M9 12h11M16 8l4 4-4 4"/>',
  globe:
    '<circle cx="12" cy="12" r="8.5"/><path d="M3.5 12h17M12 3.5c2.5 2.5 2.5 14.5 0 17M12 3.5c-2.5 2.5-2.5 14.5 0 17"/>',
  moon: '<path d="M20 14.5A8 8 0 0 1 9.5 4 8 8 0 1 0 20 14.5Z"/>',
  sun: '<circle cx="12" cy="12" r="4"/><path d="M12 2.5v2M12 19.5v2M21.5 12h-2M4.5 12h-2M18.4 5.6l-1.4 1.4M7 17l-1.4 1.4M18.4 18.4 17 17M7 7 5.6 5.6"/>',
  menu: '<path d="M4 6h16M4 12h16M4 18h16"/>',
  layers: '<path d="m12 3 9 5-9 5-9-5Z"/><path d="m3 12 9 5 9-5M3 16l9 5 9-5"/>',
  doc: '<path d="M6 3h8l4 4v14H6Z"/><path d="M14 3v4h4M9 13h6M9 16h4"/>',
  briefcase:
    '<rect x="3.5" y="7.5" width="17" height="12" rx="2"/><path d="M8.5 7.5V6a2 2 0 0 1 2-2h3a2 2 0 0 1 2 2v1.5M3.5 12.5h17"/>',
  refresh:
    '<path d="M4 11a8 8 0 0 1 13.5-4.5L20 9"/><path d="M20 4v5h-5"/><path d="M20 13a8 8 0 0 1-13.5 4.5L4 15"/><path d="M4 20v-5h5"/>',
  parking:
    '<rect x="4" y="4" width="16" height="16" rx="3"/><path d="M9 17V7h3.5a2.8 2.8 0 0 1 0 5.6H9"/>',
  snow: '<path d="M12 3v18M3.5 7.5l17 9M20.5 7.5l-17 9"/>',
  card: '<rect x="3" y="5.5" width="18" height="13" rx="2"/><path d="M3 9.5h18"/>',
  flag: '<path d="M5 21V4h11l-2 3 2 3H5"/>',
} as const;

export type IconName = keyof typeof ICON_PATHS;

const DIRECTIONAL: IconName[] = ["chevR", "chevL", "arrowL", "arrowR", "logout", "send"];

export interface IconProps {
  name: IconName;
  size?: number;
  className?: string;
  strokeWidth?: number;
  style?: CSSProperties;
}

export function Icon({
  name,
  size = 20,
  className = "",
  strokeWidth = 1.5,
  style,
}: IconProps) {
  const mirror = DIRECTIONAL.includes(name);
  return (
    <svg
      className={`ico ${mirror ? "dir-ico" : ""} ${className}`.trim()}
      width={size}
      height={size}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth={strokeWidth}
      strokeLinecap="round"
      strokeLinejoin="round"
      style={style}
      aria-hidden="true"
      dangerouslySetInnerHTML={{ __html: ICON_PATHS[name] ?? "" }}
    />
  );
}
