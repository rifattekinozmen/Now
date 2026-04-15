import React from "react";

// PREMIUM 3 THEME SYSTEM: Light / Dark / Brand (Blue-Red)
export default function CemilogluLogos() {
  return (
    <div className="p-10 space-y-12 bg-gradient-to-br from-gray-100 via-gray-200 to-gray-300 min-h-screen">

      {/* LIGHT THEME */}
      <ThemeBlock title="Light Theme" bg="bg-white" text="text-gray-900" card="bg-white shadow-xl">
        <Logo theme="light" />
      </ThemeBlock>

      {/* DARK THEME */}
      <ThemeBlock title="Dark Theme" bg="bg-black" text="text-white" card="bg-gradient-to-br from-gray-900 to-gray-800 shadow-2xl">
        <Logo theme="dark" />
      </ThemeBlock>

      {/* BRAND THEME */}
      <ThemeBlock title="Brand Theme (Blue / Red)" bg="bg-gradient-to-r from-blue-700 via-indigo-600 to-red-600" text="text-white" card="bg-white/10 backdrop-blur-xl border border-white/20">
        <Logo theme="brand" />
      </ThemeBlock>

    </div>
  );
}

// PREMIUM LOGO
function Logo({ theme }) {
  const isBrand = theme === "brand";

  return (
    <div className="flex items-center gap-5">

      {/* ICON */}
      <div className={`flex items-center justify-center size-16 rounded-2xl relative overflow-hidden ${
        theme === "dark"
          ? "bg-white/5"
          : theme === "brand"
          ? "bg-white/10"
          : "bg-gray-100"
      }`}>

        {/* Glow effect */}
        {isBrand && (
          <div className="absolute inset-0 bg-gradient-to-br from-blue-500/40 to-red-500/40 blur-xl" />
        )}

        <svg viewBox="0 0 24 24" className="size-9 relative z-10">
          <defs>
            <linearGradient id="grad1" x1="0" y1="0" x2="1" y2="1">
              <stop offset="0%" stopColor="#3b82f6" />
              <stop offset="100%" stopColor="#ef4444" />
            </linearGradient>
          </defs>

          {/* Cube */}
          <path
            d="M12 2 3 7l9 5 9-5-9-5Z"
            fill={isBrand ? "url(#grad1)" : "currentColor"}
            opacity="0.95"
          />

          {/* Cube lines */}
          <path
            d="M3 7v10l9 5 9-5V7"
            fill="none"
            stroke={isBrand ? "url(#grad1)" : "currentColor"}
            strokeWidth="1.6"
          />

          {/* C shape */}
          <path
            d="M16 9a4 4 0 1 0 0 6"
            fill="none"
            stroke={theme === "light" ? "#111" : "#fff"}
            strokeWidth="2.2"
            strokeLinecap="round"
          />
        </svg>
      </div>

      {/* TEXT */}
      <div className="flex flex-col">
        <span
          className={`text-3xl font-semibold tracking-tight leading-none ${
            isBrand
              ? "bg-gradient-to-r from-blue-400 to-red-400 bg-clip-text text-transparent"
              : ""
          }`}
          style={{ fontFamily: "Inter, Poppins, system-ui, sans-serif" }}
        >
          Cemiloğlu
        </span>

        {/* subtitle / tech feel */}
        <span className={`text-xs tracking-[0.2em] uppercase opacity-60 ${theme === "light" ? "text-gray-500" : "text-gray-400"}`}>
          Systems • Technology
        </span>
      </div>

    </div>
  );
}

// THEME BLOCK
function ThemeBlock({ title, bg, text, card, children }) {
  return (
    <div className={`p-6 rounded-3xl ${bg} ${text}`}>
      <h2 className="mb-5 font-semibold text-lg tracking-wide opacity-80">{title}</h2>
      <div className={`flex items-center justify-center p-8 rounded-2xl ${card}`}>
        {children}
      </div>
    </div>
  );
}