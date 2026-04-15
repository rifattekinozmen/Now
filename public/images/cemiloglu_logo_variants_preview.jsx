import React, { useState } from "react";

export default function CemilogluLogos() {
  const [customFrom, setCustomFrom] = useState("#6366f1");
  const [customTo,   setCustomTo]   = useState("#ec4899");

  return (
    <div style={{
      padding: "40px",
      display: "flex",
      flexDirection: "column",
      gap: "48px",
      background: "linear-gradient(135deg,#f1f5f9,#e2e8f0,#cbd5e1)",
      minHeight: "100vh",
    }}>

      {/* LIGHT */}
      <ThemeBlock title="Light Theme" outerBg="#ffffff" outerText="#111827">
        <Card bg="#ffffff" shadow="0 20px 60px rgba(0,0,0,0.10)">
          <Logo cubeColor="#111827" cStroke="#111111" textColor="#111827" />
        </Card>
      </ThemeBlock>

      {/* DARK */}
      <ThemeBlock title="Dark Theme" outerBg="#000000" outerText="#ffffff">
        <Card bg="linear-gradient(135deg,#1f2937,#111827)" shadow="0 20px 60px rgba(0,0,0,0.6)">
          <Logo cubeColor="#ffffff" cStroke="#ffffff" textColor="#ffffff" />
        </Card>
      </ThemeBlock>

      {/* BRAND */}
      <ThemeBlock
        title="Brand Theme (Blue / Red)"
        outerBg="linear-gradient(to right,#1d4ed8,#4f46e5,#dc2626)"
        outerText="#ffffff"
      >
        <Card glass>
          <Logo
            cubeColor="#3b82f6"
            cubeStroke="#ef4444"
            cStroke="#ffffff"
            textFrom="#60a5fa"
            textTo="#f87171"
            glowFrom="rgba(59,130,246,0.45)"
            glowTo="rgba(239,68,68,0.45)"
          />
        </Card>
      </ThemeBlock>

      {/* OCEAN */}
      <ThemeBlock
        title="Ocean Theme (Teal / Indigo)"
        outerBg="linear-gradient(to right,#0d9488,#4338ca)"
        outerText="#ffffff"
      >
        <Card glass>
          <Logo
            cubeColor="#2dd4bf"
            cubeStroke="#818cf8"
            cStroke="#ffffff"
            textFrom="#5eead4"
            textTo="#a5b4fc"
            glowFrom="rgba(45,212,191,0.45)"
            glowTo="rgba(129,140,248,0.45)"
          />
        </Card>
      </ThemeBlock>

      {/* WARM */}
      <ThemeBlock
        title="Warm Theme (Amber / Orange)"
        outerBg="linear-gradient(to right,#f59e0b,#ea580c)"
        outerText="#1c0f00"
      >
        <Card glass glassOpacity={0.18}>
          <Logo
            cubeColor="#fbbf24"
            cubeStroke="#f97316"
            cStroke="#1c0f00"
            textFrom="#fcd34d"
            textTo="#fb923c"
            glowFrom="rgba(251,191,36,0.45)"
            glowTo="rgba(249,115,22,0.45)"
          />
        </Card>
      </ThemeBlock>

      {/* CUSTOM */}
      <ThemeBlock
        title={
          <span style={{ display: "flex", alignItems: "center", gap: 12 }}>
            Custom Theme
            <Picker label="Primary"   value={customFrom} onChange={setCustomFrom} />
            <Picker label="Secondary" value={customTo}   onChange={setCustomTo}   />
          </span>
        }
        outerBg={`linear-gradient(to right,${customFrom},${customTo})`}
        outerText="#ffffff"
      >
        <Card glass>
          <Logo
            cubeColor={customFrom}
            cubeStroke={customTo}
            cStroke="#ffffff"
            textFrom={customFrom}
            textTo={customTo}
            glowFrom={customFrom + "77"}
            glowTo={customTo + "77"}
          />
        </Card>
      </ThemeBlock>

    </div>
  );
}

// ─── Logo ─────────────────────────────────────────────────────────────────────
// cubeColor  → küp üst yüzey dolgu rengi
// cubeStroke → küp kenar çizgisi rengi (= gradTo, ikinci marka rengi)
// cStroke    → C şekli rengi
// textFrom/textTo → gradient metin (tanımlıysa)
// textColor  → düz metin (gradient yoksa)
function Logo({
  cubeColor,
  cubeStroke,       // tanımlıysa gradient tema → 2. renk
  cStroke,
  textColor,
  textFrom, textTo,
  glowFrom, glowTo,
}) {
  const isGrad = !!(textFrom && textTo);
  const edgeColor = cubeStroke ?? cubeColor; // kenar = 2. renk veya aynı renk
  const iconBg    = isGrad ? "rgba(255,255,255,0.10)" : "rgba(0,0,0,0.04)";

  return (
    <div style={{ display: "flex", alignItems: "center", gap: 20 }}>

      {/* İkon */}
      <div style={{
        display: "flex", alignItems: "center", justifyContent: "center",
        width: 64, height: 64, borderRadius: 16,
        background: iconBg, position: "relative", overflow: "hidden",
      }}>
        {/* Glow */}
        {isGrad && (
          <div style={{
            position: "absolute", inset: 0,
            background: `linear-gradient(135deg,${glowFrom},${glowTo})`,
            filter: "blur(24px)",
          }} />
        )}

        <svg viewBox="0 0 24 24" width={36} height={36} style={{ position: "relative", zIndex: 1 }}>
          {/* Küp üst yüzey — cubeColor (1. renk / mavi) */}
          <path
            d="M12 2 3 7l9 5 9-5-9-5Z"
            fill={cubeColor}
            opacity="0.95"
          />

          {/* Küp kenar çizgileri — edgeColor (2. renk / kırmızı) */}
          <path
            d="M3 7v10l9 5 9-5V7"
            fill="none"
            stroke={edgeColor}
            strokeWidth="1.6"
          />

          {/* C şekli */}
          <path
            d="M16 9a4 4 0 1 0 0 6"
            fill="none"
            stroke={cStroke}
            strokeWidth="2.2"
            strokeLinecap="round"
          />
        </svg>
      </div>

      {/* Metin — Inter/Poppins, font-semibold, tracking-tight */}
      <span style={{
        fontFamily: "Inter, Poppins, system-ui, sans-serif",
        fontSize: "1.875rem",
        fontWeight: 600,
        letterSpacing: "-0.025em",
        lineHeight: 1,
        ...(isGrad
          ? {
              background: `linear-gradient(to right,${textFrom},${textTo})`,
              WebkitBackgroundClip: "text",
              WebkitTextFillColor: "transparent",
              backgroundClip: "text",
            }
          : { color: textColor }),
      }}>
        Cemiloğlu
      </span>

    </div>
  );
}

// ─── ThemeBlock ───────────────────────────────────────────────────────────────
function ThemeBlock({ title, outerBg, outerText, children }) {
  return (
    <div style={{ padding: 24, borderRadius: 24, background: outerBg, color: outerText }}>
      <p style={{ marginBottom: 20, fontWeight: 600, fontSize: 14, letterSpacing: "0.05em", opacity: 0.80, fontFamily: "Inter,system-ui,sans-serif" }}>
        {title}
      </p>
      <div style={{ display: "flex", alignItems: "center", justifyContent: "center", padding: "32px 16px" }}>
        {children}
      </div>
    </div>
  );
}

// ─── Card ─────────────────────────────────────────────────────────────────────
function Card({ bg, shadow, glass, glassOpacity = 0.10, children }) {
  const style = glass
    ? { background: `rgba(255,255,255,${glassOpacity})`, backdropFilter: "blur(20px)", border: "1px solid rgba(255,255,255,0.20)" }
    : { background: bg, boxShadow: shadow };

  return (
    <div style={{ ...style, borderRadius: 16, padding: "32px 48px", display: "inline-flex" }}>
      {children}
    </div>
  );
}

// ─── Color Picker ─────────────────────────────────────────────────────────────
function Picker({ label, value, onChange }) {
  return (
    <label style={{ display: "inline-flex", alignItems: "center", gap: 6, cursor: "pointer" }}>
      <span style={{ fontSize: 11, fontWeight: 600, color: "rgba(255,255,255,0.70)", fontFamily: "Inter,system-ui,sans-serif", letterSpacing: "0.06em" }}>
        {label}
      </span>
      <div style={{ position: "relative", width: 22, height: 22, borderRadius: 6, overflow: "hidden", border: "2px solid rgba(255,255,255,0.40)" }}>
        <div style={{ width: "100%", height: "100%", background: value }} />
        <input type="color" value={value} onChange={e => onChange(e.target.value)}
          style={{ position: "absolute", inset: 0, opacity: 0, cursor: "pointer", width: "100%", height: "100%" }} />
      </div>
    </label>
  );
}
