import { Icon } from "@/components/ui/Icon";

const STATES = ["a", "a", "o", "a", "s", "a", "o", "a", "a", "r", "a", "a", "o", "a", "a", "a"] as const;
const CLASS: Record<string, string> = {
  a: "",
  o: "is-occupied",
  s: "is-selected",
  r: "is-reserved",
  d: "is-disabled",
};

/** Decorative mini seat map for the hero floating card. */
export function HeroSeatMini() {
  return (
    <div className="seatmap" style={{ gridTemplateColumns: "repeat(8,1fr)", gap: 5 }}>
      {STATES.map((s, i) => (
        <div
          key={i}
          className={`seat ${CLASS[s]}`.trim()}
          style={{ fontSize: 10, borderWidth: 1.5, cursor: "default" }}
        >
          {s === "o" ? <Icon name="user" size={11} /> : <span className="tnum">{i + 1}</span>}
        </div>
      ))}
    </div>
  );
}
