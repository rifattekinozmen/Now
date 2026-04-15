import React from "react";

// 3 THEME SYSTEM: Light / Dark / Brand (Blue-Red)
export default function CemilogluLogos() {
  return (
    <div className="p-8 space-y-10">

      {/* LIGHT THEME */}
      <ThemeBlock title="Light Theme" bg="bg-white" text="text-black" card="bg-gray-100">
        <Logo />
      </ThemeBlock>

      {/* DARK THEME */}
      <ThemeBlock title="Dark Theme" bg="bg-black" text="text-white" card="bg-gray-800">
        <Logo />
      </ThemeBlock>

      {/* BRAND THEME (BLUE-RED) */}
      <ThemeBlock title="Brand Theme (Blue / Red)" bg="bg-gradient-to-r from-blue-600 to-red-500" text="text-white" card="bg-white/10">
        <Logo colorful />
      </ThemeBlock>

    </div>
  );
}

// FULL LOGO (ICON + TEXT)
function Logo({ colorful = false }) {
  return (
    <div className="flex items-center gap-4">

      {/* ICON */}
      <div className="flex items-center justify-center size-14 rounded-xl bg-current/10">
        <svg viewBox="0 0 24 24" className="size-8">
          {/* Cube */}
          <path
            d="M12 2 3 7l9 5 9-5-9-5Z"
            fill={colorful ? "#2563eb" : "currentColor"}
          />
          <path
            d="M3 7v10l9 5 9-5V7"
            fill="none"
            stroke={colorful ? "#ef4444" : "currentColor"}
            strokeWidth="1.8"
          />
          {/* C shape */}
          <path
            d="M16 9a4 4 0 1 0 0 6"
            fill="none"
            stroke={colorful ? "#ffffff" : "currentColor"}
            strokeWidth="2"
          />
        </svg>
      </div>

      {/* TEXT */}
      <span className="text-2xl font-semibold tracking-tight">
        Cemiloğlu
      </span>

    </div>
  );
}

// THEME BLOCK
function ThemeBlock({ title, bg, text, card, children }) {
  return (
    <div className={`p-6 rounded-2xl ${bg} ${text}`}>
      <h2 className="mb-4 font-semibold text-lg">{title}</h2>
      <div className={`flex items-center justify-center p-6 rounded-xl ${card}`}>
        {children}
      </div>
    </div>
  );
}