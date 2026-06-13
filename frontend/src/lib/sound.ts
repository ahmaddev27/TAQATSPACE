/**
 * A tiny, dependency-free notification chime synthesized with the Web Audio API
 * — no audio asset to ship or fetch. Best-effort: it silently no-ops when the
 * API is unavailable or still blocked by the browser autoplay policy (before the
 * user has interacted with the page).
 */

let audioContext: AudioContext | null = null;

function getContext(): AudioContext | null {
  if (typeof window === "undefined") return null;
  const Ctor =
    window.AudioContext ||
    (window as unknown as { webkitAudioContext?: typeof AudioContext })
      .webkitAudioContext;
  if (!Ctor) return null;
  audioContext ??= new Ctor();
  return audioContext;
}

/** Play a soft two-note chime (A5 → D6) for an incoming notification. */
export function playNotificationSound(): void {
  try {
    const ctx = getContext();
    if (!ctx) return;
    // Autoplay policy may leave the context suspended until a user gesture.
    if (ctx.state === "suspended") void ctx.resume();

    const now = ctx.currentTime;
    [880, 1174.66].forEach((frequency, i) => {
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.type = "sine";
      osc.frequency.value = frequency;

      const start = now + i * 0.12;
      gain.gain.setValueAtTime(0.0001, start);
      gain.gain.exponentialRampToValueAtTime(0.14, start + 0.02);
      gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.18);

      osc.connect(gain).connect(ctx.destination);
      osc.start(start);
      osc.stop(start + 0.2);
    });
  } catch {
    // Sound is a nicety — never surface a failure.
  }
}
