import React, { useState } from "react";

// ─── Tema konfigürasyonları ───────────────────────────────────────────────────
const THEMES = {
  // ── Orijinal 3 tema (birebir orjin_cemiloglu_logo_variants_preview.jsx'ten) ──
  light: {
    label: "Light",
    pageStyle: { background: "#f1f5f9" },                          // sayfa: açık gri
    cardStyle: { background: "#ffffff", boxShadow: "0 20px 60px rgba(0,0,0,0.10)" },
    textColor: "#0f172a",                                          // currentColor → koyu
    labelColor: "rgba(15,23,42,0.55)",
    iconBg: "#f5f5f5",                                             // bg-gray-100 (solid)
    cubeColor: "#0f172a",                                          // küp rengi
    cStroke: "#111111",                                            // C şekli: #111
    gradFrom: null,
    gradTo: null,
    glowFrom: null,
    glowTo: null,
    isDark: false,
  },
  dark: {
    label: "Dark",
    pageStyle: { background: "#000000" },                          // bg-black
    cardStyle: { background: "linear-gradient(135deg,#1f2937,#111827)", boxShadow: "0 20px 60px rgba(0,0,0,0.6)" },
    textColor: "#ffffff",                                          // currentColor → beyaz
    labelColor: "rgba(255,255,255,0.50)",
    iconBg: "rgba(255,255,255,0.05)",                              // bg-white/5
    cubeColor: "#ffffff",                                          // küp rengi
    cStroke: "#ffffff",                                            // C şekli: #fff
    gradFrom: null,
    gradTo: null,
    glowFrom: null,
    glowTo: null,
    isDark: true,
  },
  brand: {
    label: "Brand",
    pageStyle: { background: "linear-gradient(to right,#1d4ed8,#4f46e5,#dc2626)" }, // from-blue-700 via-indigo-600 to-red-600
    cardStyle: { background: "rgba(255,255,255,0.10)", backdropFilter: "blur(20px)", border: "1px solid rgba(255,255,255,0.20)" },
    textColor: "#ffffff",
    labelColor: "rgba(255,255,255,0.55)",
    iconBg: "rgba(255,255,255,0.10)",                              // bg-white/10
    cubeColor: null,                                               // gradient kullanılacak
    cStroke: "#ffffff",                                            // brand'de beyaz
    gradFrom: "#3b82f6",                                           // blue-500
    gradTo: "#ef4444",                                             // red-500
    glowFrom: "rgba(59,130,246,0.40)",                             // from-blue-500/40
    glowTo: "rgba(239,68,68,0.40)",                                // to-red-500/40
    textGradFrom: "#60a5fa",                                       // from-blue-400
    textGradTo: "#f87171",                                         // to-red-400
    isDark: true,
  },
  // ── Ek temalar ───────────────────────────────────────────────────────────────
  ocean: {
    label: "Ocean",
    pageStyle: { background: "linear-gradient(to right,#0d9488,#4338ca)" },
    cardStyle: { background: "rgba(255,255,255,0.10)", backdropFilter: "blur(20px)", border: "1px solid rgba(255,255,255,0.18)" },
    textColor: "#ffffff",
    labelColor: "rgba(255,255,255,0.55)",
    iconBg: "rgba(255,255,255,0.10)",
    cubeColor: null,
    cStroke: "#ffffff",
    gradFrom: "#2dd4bf",
    gradTo: "#818cf8",
    glowFrom: "rgba(45,212,191,0.40)",
    glowTo: "rgba(129,140,248,0.40)",
    textGradFrom: "#5eead4",
    textGradTo: "#a5b4fc",
    isDark: true,
  },
  warm: {
    label: "Warm",
    pageStyle: { background: "linear-gradient(to right,#f59e0b,#ea580c)" },
    cardStyle: { background: "rgba(255,255,255,0.18)", backdropFilter: "blur(20px)", border: "1px solid rgba(255,255,255,0.25)" },
    textColor: "#1c0f00",
    labelColor: "rgba(28,15,0,0.55)",
    iconBg: "rgba(255,255,255,0.20)",
    cubeColor: null,
    cStroke: "#1c0f00",
    gradFrom: "#fbbf24",
    gradTo: "#f97316",
    glowFrom: "rgba(251,191,36,0.40)",
    glowTo: "rgba(249,115,22,0.40)",
    textGradFrom: "#fcd34d",
    textGradTo: "#fb923c",
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
        pageStyle: { background: `linear-gradient(to right,${customFrom},${customTo})` },
        cardStyle: { background: "rgba(255,255,255,0.12)", backdropFilter: "blur(20px)", border: "1px solid rgba(255,255,255,0.20)" },
        textColor: "#ffffff",
        labelColor: "rgba(255,255,255,0.55)",
        iconBg: "rgba(255,255,255,0.10)",
        cubeColor: null,
        cStroke: "#ffffff",
        gradFrom: customFrom,
        gradTo: customTo,
        glowFrom: `${customFrom}66`,
        glowTo: `${customTo}66`,
        textGradFrom: customFrom,
        textGradTo: customTo,
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
  // Küp rengi: gradient temalarda url(#id), düz temalarda cubeColor
  const cubeColor = hasGrad ? `url(#${gradId})` : cfg.cubeColor;
  // Metin gradient renkleri (brand: blue-400→red-400, diğerleri: kendi gradyanları)
  const textFrom = cfg.textGradFrom || cfg.gradFrom;
  const textTo   = cfg.textGradTo   || cfg.gradTo;

  return (
    <div style={{ display: "flex", alignItems: "center", gap: 20 }}>

      {/* ── Icon container ── */}
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
        {/* Glow — orijinaldeki bg-gradient-to-br blur-xl mantığıyla */}
        {hasGrad && cfg.glowFrom && (
          <div
            style={{
              position: "absolute",
              inset: 0,
              background: `linear-gradient(135deg, ${cfg.glowFrom}, ${cfg.glowTo})`,
              filter: "blur(24px)",              // blur-xl = 24px
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
            fill={cubeColor}
            opacity="0.95"
          />

          {/* Küp — yan kenarlar */}
          <path
            d="M3 7v10l9 5 9-5V7"
            fill="none"
            stroke={cubeColor}
            strokeWidth="1.6"
          />

          {/* C şekli — orijinal: light → #111, diğerleri → #fff */}
          <path
            d="M16 9a4 4 0 1 0 0 6"
            fill="none"
            stroke={cfg.cStroke}
            strokeWidth="2.2"
            strokeLinecap="round"
          />
        </svg>
      </div>

      {/* ── Metin ── */}
      <div style={{ display: "flex", flexDirection: "column" }}>
        <span
          style={{
            fontSize: 30,
            fontWeight: 600,
            letterSpacing: "-0.02em",
            lineHeight: 1,
            fontFamily: "Inter, Poppins, system-ui, sans-serif",
            // Gradient temalar: bg-gradient-to-r bg-clip-text text-transparent mantığı
            ...(hasGrad
              ? {
                  background: `linear-gradient(to right, ${textFrom}, ${textTo})`,
                  WebkitBackgroundClip: "text",
                  WebkitTextFillColor: "transparent",
                  backgroundClip: "text",
                }
              : { color: cfg.textColor }),
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
