import React, { useState } from "react";

// ─── Tema konfigürasyonları ───────────────────────────────────────────────────
const THEMES = {
  light: {
    label: "Light",
    pageBg: "#f8fafc",
    pageStyle: { background: "#f8fafc" },
    cardStyle: { background: "#ffffff", boxShadow: "0 20px 60px rgba(0,0,0,0.10)" },
    textColor: "#0f172a",
    labelColor: "rgba(15,23,42,0.55)",
    iconBg: "rgba(0,0,0,0.05)",
    cStroke: "#111111",
    gradFrom: null,
    gradTo: null,
    isDark: false,
  },
  dark: {
    label: "Dark",
    pageStyle: { background: "#09090b" },
    cardStyle: { background: "linear-gradient(135deg,#18181b,#27272a)", boxShadow: "0 20px 60px rgba(0,0,0,0.6)" },
    textColor: "#f4f4f5",
    labelColor: "rgba(244,244,245,0.50)",
    iconBg: "rgba(255,255,255,0.06)",
    cStroke: "#ffffff",
    gradFrom: null,
    gradTo: null,
    isDark: true,
  },
  brand: {
    label: "Brand",
    pageStyle: { background: "linear-gradient(135deg,#1d4ed8,#4f46e5,#dc2626)" },
    cardStyle: { background: "rgba(255,255,255,0.10)", backdropFilter: "blur(20px)", border: "1px solid rgba(255,255,255,0.18)" },
    textColor: "#ffffff",
    labelColor: "rgba(255,255,255,0.55)",
    iconBg: "rgba(255,255,255,0.12)",
    cStroke: "#ffffff",
    gradFrom: "#3b82f6",
    gradTo: "#ef4444",
    isDark: true,
  },
  ocean: {
    label: "Ocean",
    pageStyle: { background: "linear-gradient(135deg,#0d9488,#4338ca)" },
    cardStyle: { background: "rgba(255,255,255,0.10)", backdropFilter: "blur(20px)", border: "1px solid rgba(255,255,255,0.18)" },
    textColor: "#ffffff",
    labelColor: "rgba(255,255,255,0.55)",
    iconBg: "rgba(255,255,255,0.12)",
    cStroke: "#ffffff",
    gradFrom: "#2dd4bf",
    gradTo: "#818cf8",
    isDark: true,
  },
  warm: {
    label: "Warm",
    pageStyle: { background: "linear-gradient(135deg,#f59e0b,#ea580c)" },
    cardStyle: { background: "rgba(255,255,255,0.18)", backdropFilter: "blur(20px)", border: "1px solid rgba(255,255,255,0.25)" },
    textColor: "#1c0f00",
    labelColor: "rgba(28,15,0,0.55)",
    iconBg: "rgba(255,255,255,0.20)",
    cStroke: "#1c0f00",
    gradFrom: "#fbbf24",
    gradTo: "#f97316",
    isDark: false,
  },
};

// ─── Ana bileşen ──────────────────────────────────────────────────────────────
export default function CemilogluLogos() {
  const [active, setActive] = useState("light");
  const [customFrom, setCustomFrom] = useState("#6366f1");
  const [customTo, setCustomTo] = useState("#ec4899");

  const isCustom = active === "custom";

  const cfg = isCustom
    ? {
        label: "Custom",
        pageStyle: { background: `linear-gradient(135deg,${customFrom},${customTo})` },
        cardStyle: { background: "rgba(255,255,255,0.12)", backdropFilter: "blur(20px)", border: "1px solid rgba(255,255,255,0.20)" },
        textColor: "#ffffff",
        labelColor: "rgba(255,255,255,0.55)",
        iconBg: "rgba(255,255,255,0.12)",
        cStroke: "#ffffff",
        gradFrom: customFrom,
        gradTo: customTo,
        isDark: true,
      }
    : THEMES[active];

  const gradId = `grad-${active}`;

  return (
    <div
      style={{ ...cfg.pageStyle, transition: "background 0.45s ease", minHeight: "100vh", padding: "48px 40px" }}
    >
      {/* ── Başlık ── */}
      <div style={{ marginBottom: 36, textAlign: "center" }}>
        <p style={{ fontSize: 12, fontWeight: 600, letterSpacing: "0.12em", textTransform: "uppercase", color: cfg.labelColor, marginBottom: 14, fontFamily: "Inter,system-ui,sans-serif" }}>
          Logo Theme Preview
        </p>

        {/* Tema seçici */}
        <div style={{ display: "inline-flex", gap: 6, padding: "6px", borderRadius: 14, background: "rgba(128,128,128,0.15)", backdropFilter: "blur(10px)" }}>
          {Object.entries(THEMES).map(([key, t]) => (
            <button
              key={key}
              onClick={() => setActive(key)}
              style={{
                padding: "7px 16px",
                borderRadius: 10,
                border: "none",
                cursor: "pointer",
                fontSize: 13,
                fontWeight: 600,
                fontFamily: "Inter,system-ui,sans-serif",
                transition: "all 0.2s ease",
                background: active === key ? (cfg.isDark ? "rgba(255,255,255,0.90)" : "rgba(0,0,0,0.85)") : "transparent",
                color: active === key ? (cfg.isDark ? "#09090b" : "#ffffff") : cfg.textColor,
                boxShadow: active === key ? "0 2px 8px rgba(0,0,0,0.20)" : "none",
                opacity: active === key ? 1 : 0.65,
              }}
            >
              {t.label}
            </button>
          ))}
          {/* Custom buton */}
          <button
            onClick={() => setActive("custom")}
            style={{
              padding: "7px 16px",
              borderRadius: 10,
              border: "none",
              cursor: "pointer",
              fontSize: 13,
              fontWeight: 600,
              fontFamily: "Inter,system-ui,sans-serif",
              transition: "all 0.2s ease",
              background: isCustom ? (cfg.isDark ? "rgba(255,255,255,0.90)" : "rgba(0,0,0,0.85)") : "transparent",
              color: isCustom ? (cfg.isDark ? "#09090b" : "#ffffff") : cfg.textColor,
              boxShadow: isCustom ? "0 2px 8px rgba(0,0,0,0.20)" : "none",
              opacity: isCustom ? 1 : 0.65,
            }}
          >
            Custom
          </button>
        </div>

        {/* Custom renk seçiciler */}
        {isCustom && (
          <div style={{ display: "flex", justifyContent: "center", gap: 20, marginTop: 18, alignItems: "center" }}>
            <ColorPickerRow label="Primary" value={customFrom} onChange={setCustomFrom} labelColor={cfg.labelColor} />
            <ColorPickerRow label="Secondary" value={customTo} onChange={setCustomTo} labelColor={cfg.labelColor} />
          </div>
        )}
      </div>

      {/* ── Logo Kartı ── */}
      <div style={{ display: "flex", alignItems: "center", justifyContent: "center" }}>
        <div
          style={{
            ...cfg.cardStyle,
            padding: "56px 80px",
            borderRadius: 24,
            transition: "all 0.4s ease",
          }}
        >
          <Logo cfg={cfg} gradId={gradId} />
        </div>
      </div>

      {/* ── Tema adı etiketi ── */}
      <p style={{ textAlign: "center", marginTop: 28, fontSize: 13, color: cfg.labelColor, fontFamily: "Inter,system-ui,sans-serif", fontWeight: 500, letterSpacing: "0.06em" }}>
        {cfg.label} Theme
      </p>
    </div>
  );
}

// ─── Logo bileşeni ────────────────────────────────────────────────────────────
function Logo({ cfg, gradId }) {
  const hasGrad = !!(cfg.gradFrom && cfg.gradTo);

  return (
    <div style={{ display: "flex", alignItems: "center", gap: 20 }}>
      {/* Icon */}
      <div
        style={{
          display: "flex",
          alignItems: "center",
          justifyContent: "center",
          width: 64,
          height: 64,
          borderRadius: 16,
          background: cfg.iconBg,
          position: "relative",
          overflow: "hidden",
          transition: "background 0.4s ease",
        }}
      >
        {/* Glow — sadece gradyanlı temalar */}
        {hasGrad && (
          <div
            style={{
              position: "absolute",
              inset: 0,
              background: `radial-gradient(circle at 50% 50%, ${cfg.gradFrom}66, ${cfg.gradTo}66)`,
              filter: "blur(14px)",
            }}
          />
        )}

        <svg viewBox="0 0 24 24" width={36} height={36} style={{ position: "relative", zIndex: 1 }}>
          <defs>
            {hasGrad && (
              <linearGradient id={gradId} x1="0" y1="0" x2="1" y2="1">
                <stop offset="0%" stopColor={cfg.gradFrom} />
                <stop offset="100%" stopColor={cfg.gradTo} />
              </linearGradient>
            )}
          </defs>

          {/* Küp — üst yüzey */}
          <path
            d="M12 2 3 7l9 5 9-5-9-5Z"
            fill={hasGrad ? `url(#${gradId})` : cfg.textColor}
            opacity="0.95"
          />

          {/* Küp — kenar çizgileri */}
          <path
            d="M3 7v10l9 5 9-5V7"
            fill="none"
            stroke={hasGrad ? `url(#${gradId})` : cfg.textColor}
            strokeWidth="1.6"
          />

          {/* C şekli */}
          <path
            d="M16 9a4 4 0 1 0 0 6"
            fill="none"
            stroke={cfg.cStroke}
            strokeWidth="2.2"
            strokeLinecap="round"
          />
        </svg>
      </div>

      {/* Metin */}
      <div style={{ display: "flex", flexDirection: "column" }}>
        <span
          style={{
            fontSize: 32,
            fontWeight: 600,
            letterSpacing: "-0.02em",
            lineHeight: 1,
            fontFamily: "Inter, Poppins, system-ui, sans-serif",
            ...(hasGrad
              ? {
                  background: `linear-gradient(90deg, ${cfg.gradFrom}, ${cfg.gradTo})`,
                  WebkitBackgroundClip: "text",
                  WebkitTextFillColor: "transparent",
                  backgroundClip: "text",
                }
              : { color: cfg.textColor }),
            transition: "color 0.4s ease",
          }}
        >
          Cemiloğlu
        </span>
      </div>
    </div>
  );
}

// ─── Renk seçici satırı ───────────────────────────────────────────────────────
function ColorPickerRow({ label, value, onChange, labelColor }) {
  return (
    <label style={{ display: "flex", alignItems: "center", gap: 8, cursor: "pointer" }}>
      <span style={{ fontSize: 12, fontWeight: 600, color: labelColor, fontFamily: "Inter,system-ui,sans-serif", letterSpacing: "0.06em" }}>
        {label}
      </span>
      <div style={{ position: "relative", width: 32, height: 32, borderRadius: 8, overflow: "hidden", border: "2px solid rgba(255,255,255,0.30)", boxShadow: "0 2px 6px rgba(0,0,0,0.20)" }}>
        <div style={{ width: "100%", height: "100%", background: value }} />
        <input
          type="color"
          value={value}
          onChange={(e) => onChange(e.target.value)}
          style={{ position: "absolute", inset: 0, opacity: 0, cursor: "pointer", width: "100%", height: "100%" }}
        />
      </div>
      <span style={{ fontSize: 11, color: labelColor, fontFamily: "monospace" }}>{value}</span>
    </label>
  );
}
