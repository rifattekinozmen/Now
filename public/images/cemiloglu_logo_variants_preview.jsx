import React, { useState } from "react";

// ─── Sayfa ────────────────────────────────────────────────────────────────────
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

      {/* ── LIGHT ── */}
      <ThemeBlock title="Light Theme" outerBg="#ffffff" outerText="#111827">
        <div style={{ background: "#ffffff", boxShadow: "0 20px 60px rgba(0,0,0,0.10)", borderRadius: 16, padding: "32px 48px", display: "inline-flex" }}>
          <Logo
            gradId="grad-light"
            cubeColor="#111827"
            cStroke="#111111"
            hasGrad={false}
          />
        </div>
      </ThemeBlock>

      {/* ── DARK ── */}
      <ThemeBlock title="Dark Theme" outerBg="#000000" outerText="#ffffff">
        <div style={{ background: "linear-gradient(135deg,#1f2937,#111827)", boxShadow: "0 20px 60px rgba(0,0,0,0.6)", borderRadius: 16, padding: "32px 48px", display: "inline-flex" }}>
          <Logo
            gradId="grad-dark"
            cubeColor="#ffffff"
            cStroke="#ffffff"
            hasGrad={false}
          />
        </div>
      </ThemeBlock>

      {/* ── BRAND ── */}
      <ThemeBlock title="Brand Theme (Blue / Red)" outerBg="linear-gradient(to right,#1d4ed8,#4f46e5,#dc2626)" outerText="#ffffff">
        <div style={{ background: "rgba(255,255,255,0.10)", backdropFilter: "blur(20px)", border: "1px solid rgba(255,255,255,0.20)", borderRadius: 16, padding: "32px 48px", display: "inline-flex" }}>
          <Logo
            gradId="grad-brand"
            hasGrad={true}
            gradFrom="#3b82f6"
            gradTo="#ef4444"
            glowFrom="rgba(59,130,246,0.40)"
            glowTo="rgba(239,68,68,0.40)"
            textGradFrom="#60a5fa"
            textGradTo="#f87171"
            cStroke="#ffffff"
          />
        </div>
      </ThemeBlock>

      {/* ── OCEAN ── */}
      <ThemeBlock title="Ocean Theme (Teal / Indigo)" outerBg="linear-gradient(to right,#0d9488,#4338ca)" outerText="#ffffff">
        <div style={{ background: "rgba(255,255,255,0.10)", backdropFilter: "blur(20px)", border: "1px solid rgba(255,255,255,0.18)", borderRadius: 16, padding: "32px 48px", display: "inline-flex" }}>
          <Logo
            gradId="grad-ocean"
            hasGrad={true}
            gradFrom="#2dd4bf"
            gradTo="#818cf8"
            glowFrom="rgba(45,212,191,0.40)"
            glowTo="rgba(129,140,248,0.40)"
            textGradFrom="#5eead4"
            textGradTo="#a5b4fc"
            cStroke="#ffffff"
          />
        </div>
      </ThemeBlock>

      {/* ── WARM ── */}
      <ThemeBlock title="Warm Theme (Amber / Orange)" outerBg="linear-gradient(to right,#f59e0b,#ea580c)" outerText="#1c0f00">
        <div style={{ background: "rgba(255,255,255,0.18)", backdropFilter: "blur(20px)", border: "1px solid rgba(255,255,255,0.25)", borderRadius: 16, padding: "32px 48px", display: "inline-flex" }}>
          <Logo
            gradId="grad-warm"
            hasGrad={true}
            gradFrom="#fbbf24"
            gradTo="#f97316"
            glowFrom="rgba(251,191,36,0.40)"
            glowTo="rgba(249,115,22,0.40)"
            textGradFrom="#fcd34d"
            textGradTo="#fb923c"
            cStroke="#1c0f00"
          />
        </div>
      </ThemeBlock>

      {/* ── CUSTOM ── */}
      <ThemeBlock
        title={
          <span style={{ display: "flex", alignItems: "center", gap: 12 }}>
            Custom Theme
            <ColorPickerRow label="Primary"   value={customFrom} onChange={setCustomFrom} />
            <ColorPickerRow label="Secondary" value={customTo}   onChange={setCustomTo}   />
          </span>
        }
        outerBg={`linear-gradient(to right,${customFrom},${customTo})`}
        outerText="#ffffff"
      >
        <div style={{ background: "rgba(255,255,255,0.12)", backdropFilter: "blur(20px)", border: "1px solid rgba(255,255,255,0.20)", borderRadius: 16, padding: "32px 48px", display: "inline-flex" }}>
          <Logo
            gradId="grad-custom"
            hasGrad={true}
            gradFrom={customFrom}
            gradTo={customTo}
            glowFrom={customFrom + "66"}
            glowTo={customTo + "66"}
            textGradFrom={customFrom}
            textGradTo={customTo}
            cStroke="#ffffff"
          />
        </div>
      </ThemeBlock>

    </div>
  );
}

// ─── Logo ─────────────────────────────────────────────────────────────────────
function Logo({
  gradId,
  hasGrad = false,
  cubeColor = null,
  cStroke = "#ffffff",
  gradFrom, gradTo,
  glowFrom, glowTo,
  textGradFrom, textGradTo,
}) {
  const fill   = hasGrad ? `url(#${gradId})` : cubeColor;
  const stroke = hasGrad ? `url(#${gradId})` : cubeColor;

  return (
    <div style={{ display: "flex", alignItems: "center", gap: 20 }}>

      {/* Icon */}
      <div style={{
        display: "flex", alignItems: "center", justifyContent: "center",
        width: 64, height: 64, borderRadius: 16,
        background: hasGrad ? "rgba(255,255,255,0.10)" : "rgba(0,0,0,0.04)",
        position: "relative", overflow: "hidden",
      }}>
        {/* Glow — orijinal: absolute inset-0 bg-gradient-to-br blur-xl */}
        {hasGrad && (
          <div style={{
            position: "absolute", inset: 0,
            background: `linear-gradient(135deg,${glowFrom},${glowTo})`,
            filter: "blur(24px)",
          }} />
        )}

        <svg viewBox="0 0 24 24" width={36} height={36} style={{ position: "relative", zIndex: 1 }}>
          <defs>
            {hasGrad && (
              <linearGradient id={gradId} x1="0" y1="0" x2="1" y2="1">
                <stop offset="0%" stopColor={gradFrom} />
                <stop offset="100%" stopColor={gradTo} />
              </linearGradient>
            )}
          </defs>

          {/* Cube top */}
          <path d="M12 2 3 7l9 5 9-5-9-5Z" fill={fill} opacity="0.95" />

          {/* Cube lines */}
          <path d="M3 7v10l9 5 9-5V7" fill="none" stroke={stroke} strokeWidth="1.6" />

          {/* C shape */}
          <path d="M16 9a4 4 0 1 0 0 6" fill="none" stroke={cStroke} strokeWidth="2.2" strokeLinecap="round" />
        </svg>
      </div>

      {/* Text */}
      <span style={{
        fontSize: 30,
        fontWeight: 600,
        letterSpacing: "-0.02em",
        lineHeight: 1,
        fontFamily: "Inter, Poppins, system-ui, sans-serif",
        ...(hasGrad
          ? { background: `linear-gradient(to right,${textGradFrom},${textGradTo})`, WebkitBackgroundClip: "text", WebkitTextFillColor: "transparent", backgroundClip: "text" }
          : { color: cubeColor }),
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
      <p style={{ marginBottom: 20, fontWeight: 600, fontSize: 14, letterSpacing: "0.04em", opacity: 0.80, fontFamily: "Inter,system-ui,sans-serif" }}>
        {title}
      </p>
      <div style={{ display: "flex", alignItems: "center", justifyContent: "center", padding: "32px 16px" }}>
        {children}
      </div>
    </div>
  );
}

// ─── Color Picker ─────────────────────────────────────────────────────────────
function ColorPickerRow({ label, value, onChange }) {
  return (
    <label style={{ display: "inline-flex", alignItems: "center", gap: 6, cursor: "pointer" }}>
      <span style={{ fontSize: 11, fontWeight: 600, color: "rgba(255,255,255,0.70)", fontFamily: "Inter,system-ui,sans-serif", letterSpacing: "0.06em" }}>
        {label}
      </span>
      <div style={{ position: "relative", width: 22, height: 22, borderRadius: 6, overflow: "hidden", border: "2px solid rgba(255,255,255,0.40)", boxShadow: "0 2px 4px rgba(0,0,0,0.20)" }}>
        <div style={{ width: "100%", height: "100%", background: value }} />
        <input type="color" value={value} onChange={e => onChange(e.target.value)}
          style={{ position: "absolute", inset: 0, opacity: 0, cursor: "pointer", width: "100%", height: "100%" }} />
      </div>
    </label>
  );
}
