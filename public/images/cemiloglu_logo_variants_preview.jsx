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
            gradId="gl"
            cColor="#111827"
            fontWeight={300}
            letterSpacing="0.08em"
          />
        </div>
      </ThemeBlock>

      {/* ── DARK ── */}
      <ThemeBlock title="Dark Theme" outerBg="#000000" outerText="#ffffff">
        <div style={{ background: "linear-gradient(135deg,#1f2937,#111827)", boxShadow: "0 20px 60px rgba(0,0,0,0.6)", borderRadius: 16, padding: "32px 48px", display: "inline-flex" }}>
          <Logo
            gradId="gd"
            cColor="#ffffff"
            fontWeight={500}
            letterSpacing="0.01em"
          />
        </div>
      </ThemeBlock>

      {/* ── BRAND ── */}
      <ThemeBlock title="Brand Theme (Blue / Red)" outerBg="linear-gradient(to right,#1d4ed8,#4f46e5,#dc2626)" outerText="#ffffff">
        <div style={{ background: "rgba(255,255,255,0.10)", backdropFilter: "blur(20px)", border: "1px solid rgba(255,255,255,0.20)", borderRadius: 16, padding: "32px 48px", display: "inline-flex" }}>
          <Logo
            gradId="gb"
            gradFrom="#3b82f6" gradTo="#ef4444"
            textFrom="#60a5fa" textTo="#f87171"
            glowFrom="rgba(59,130,246,0.45)" glowTo="rgba(239,68,68,0.45)"
            fontWeight={700}
            letterSpacing="-0.02em"
          />
        </div>
      </ThemeBlock>

      {/* ── OCEAN ── */}
      <ThemeBlock title="Ocean Theme (Teal / Indigo)" outerBg="linear-gradient(to right,#0d9488,#4338ca)" outerText="#ffffff">
        <div style={{ background: "rgba(255,255,255,0.10)", backdropFilter: "blur(20px)", border: "1px solid rgba(255,255,255,0.18)", borderRadius: 16, padding: "32px 48px", display: "inline-flex" }}>
          <Logo
            gradId="go"
            gradFrom="#2dd4bf" gradTo="#818cf8"
            textFrom="#5eead4" textTo="#a5b4fc"
            glowFrom="rgba(45,212,191,0.45)" glowTo="rgba(129,140,248,0.45)"
            fontWeight={400}
            letterSpacing="0.03em"
          />
        </div>
      </ThemeBlock>

      {/* ── WARM ── */}
      <ThemeBlock title="Warm Theme (Amber / Orange)" outerBg="linear-gradient(to right,#f59e0b,#ea580c)" outerText="#1c0f00">
        <div style={{ background: "rgba(255,255,255,0.18)", backdropFilter: "blur(20px)", border: "1px solid rgba(255,255,255,0.25)", borderRadius: 16, padding: "32px 48px", display: "inline-flex" }}>
          <Logo
            gradId="gw"
            gradFrom="#fbbf24" gradTo="#f97316"
            textFrom="#fcd34d" textTo="#fb923c"
            glowFrom="rgba(251,191,36,0.45)" glowTo="rgba(249,115,22,0.45)"
            fontWeight={600}
            letterSpacing="-0.01em"
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
            gradId="gc"
            gradFrom={customFrom} gradTo={customTo}
            textFrom={customFrom} textTo={customTo}
            glowFrom={customFrom + "77"} glowTo={customTo + "77"}
            fontWeight={600}
            letterSpacing="-0.01em"
          />
        </div>
      </ThemeBlock>

    </div>
  );
}

// ─── Logo ─────────────────────────────────────────────────────────────────────
// cColor  → düz tema için icon + metin rengi
// gradFrom/gradTo → gradient tema için C arc stroke rengi
// textFrom/textTo → gradient tema için metin rengi (ayrı ton)
// glowFrom/glowTo → icon arka planı glow rengi
function Logo({
  gradId,
  cColor,
  gradFrom, gradTo,
  textFrom, textTo,
  glowFrom, glowTo,
  fontWeight = 500,
  letterSpacing = "0",
}) {
  const hasGrad = !!(gradFrom && gradTo);
  const cStroke = hasGrad ? `url(#${gradId})` : cColor;

  return (
    <div style={{ display: "flex", alignItems: "center", gap: 20 }}>

      {/* ── Icon — sadece C şekli ── */}
      <div style={{
        display: "flex", alignItems: "center", justifyContent: "center",
        width: 64, height: 64, borderRadius: 16,
        background: hasGrad ? "rgba(255,255,255,0.10)" : "rgba(0,0,0,0.04)",
        position: "relative",
      }}>
        {/* Glow: sadece gradient temalar */}
        {hasGrad && (
          <div style={{
            position: "absolute", inset: 0, borderRadius: 16,
            background: `linear-gradient(135deg,${glowFrom},${glowTo})`,
            filter: "blur(20px)",
          }} />
        )}

        <svg
          viewBox="0 0 24 24"
          width={36}
          height={36}
          style={{ position: "relative", zIndex: 1, overflow: "visible" }}
        >
          <defs>
            {/* gradientUnits="userSpaceOnUse" + viewBox koordinatları → stroke'ta da çalışır */}
            {hasGrad && (
              <linearGradient
                id={gradId}
                x1="0" y1="12" x2="24" y2="12"
                gradientUnits="userSpaceOnUse"
              >
                <stop offset="0%"   stopColor={gradFrom} />
                <stop offset="100%" stopColor={gradTo}   />
              </linearGradient>
            )}
          </defs>

          {/*
            C şekli: M17 7 a7 7 0 1 0 0 10
            - Merkez ≈ (12, 12), yarıçap ≈ 7
            - Sağa açık C, yukarıdan (17,7) sola dolanarak aşağıya (17,17)
          */}
          <path
            d="M17 7a7 7 0 1 0 0 10"
            fill="none"
            stroke={cStroke}
            strokeWidth="2.5"
            strokeLinecap="round"
          />
        </svg>
      </div>

      {/* ── Metin ── */}
      <span style={{
        fontFamily: "Inter, Poppins, system-ui, sans-serif",
        fontSize: 30,
        fontWeight,
        letterSpacing,
        lineHeight: 1,
        ...(hasGrad
          ? {
              background: `linear-gradient(to right,${textFrom},${textTo})`,
              WebkitBackgroundClip: "text",
              WebkitTextFillColor: "transparent",
              backgroundClip: "text",
            }
          : { color: cColor }),
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
      <div style={{ position: "relative", width: 22, height: 22, borderRadius: 6, overflow: "hidden", border: "2px solid rgba(255,255,255,0.40)" }}>
        <div style={{ width: "100%", height: "100%", background: value }} />
        <input type="color" value={value} onChange={e => onChange(e.target.value)}
          style={{ position: "absolute", inset: 0, opacity: 0, cursor: "pointer", width: "100%", height: "100%" }} />
      </div>
    </label>
  );
}
