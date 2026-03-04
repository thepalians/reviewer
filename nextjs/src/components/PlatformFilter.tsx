"use client";

import { cn } from "@/lib/utils";

interface Platform {
  id: number;
  name: string;
  slug: string;
  icon: string | null;
}

interface PlatformFilterProps {
  platforms: Platform[];
  selected: string;
  onChange: (slug: string) => void;
}

export default function PlatformFilter({ platforms, selected, onChange }: PlatformFilterProps) {
  return (
    <div className="flex flex-wrap gap-2">
      <button
        onClick={() => onChange("all")}
        className={cn(
          "px-4 py-1.5 rounded-full text-sm font-medium border transition-colors",
          selected === "all"
            ? "bg-gradient-to-r from-[#667eea] to-[#764ba2] text-white border-transparent"
            : "border-gray-200 text-gray-600 hover:border-[#667eea] hover:text-[#667eea]"
        )}
      >
        All
      </button>
      {platforms.map((p) => (
        <button
          key={p.slug}
          onClick={() => onChange(p.slug)}
          className={cn(
            "px-4 py-1.5 rounded-full text-sm font-medium border transition-colors flex items-center gap-1",
            selected === p.slug
              ? "bg-gradient-to-r from-[#667eea] to-[#764ba2] text-white border-transparent"
              : "border-gray-200 text-gray-600 hover:border-[#667eea] hover:text-[#667eea]"
          )}
        >
          {p.icon && <span>{p.icon}</span>}
          {p.name}
        </button>
      ))}
    </div>
  );
}
