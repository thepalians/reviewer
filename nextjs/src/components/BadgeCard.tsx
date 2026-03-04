import { cn } from "@/lib/utils";
import type { BadgeDefinition } from "@/lib/badges";
import { formatDateTime } from "@/lib/utils";

interface BadgeCardProps {
  badge: BadgeDefinition;
  earned: boolean;
  awardedAt?: string;
}

const CATEGORY_COLORS: Record<BadgeDefinition["category"], string> = {
  task: "bg-blue-50 border-blue-200",
  social: "bg-green-50 border-green-200",
  streak: "bg-orange-50 border-orange-200",
  special: "bg-purple-50 border-purple-200",
};

const CATEGORY_LABELS: Record<BadgeDefinition["category"], string> = {
  task: "Task",
  social: "Social",
  streak: "Streak",
  special: "Special",
};

export default function BadgeCard({ badge, earned, awardedAt }: BadgeCardProps) {
  return (
    <div
      className={cn(
        "relative rounded-xl border p-4 flex flex-col items-center text-center transition-all duration-200",
        earned
          ? cn(
              CATEGORY_COLORS[badge.category],
              "shadow-md",
              "ring-2 ring-offset-1",
              badge.category === "task"
                ? "ring-blue-300"
                : badge.category === "social"
                ? "ring-green-300"
                : badge.category === "streak"
                ? "ring-orange-300"
                : "ring-purple-400"
            )
          : "bg-gray-50 border-gray-200 opacity-60 grayscale"
      )}
    >
      {/* Earned glow */}
      {earned && (
        <div className="absolute inset-0 rounded-xl pointer-events-none ring-2 ring-white/40 blur-sm" />
      )}

      <span className="text-4xl mb-2">{badge.emoji}</span>
      <h3 className="font-semibold text-gray-900 text-sm leading-tight">
        {badge.name}
      </h3>
      <p className="text-xs text-gray-500 mt-1">{badge.description}</p>
      <p className="text-xs text-gray-400 mt-2 italic">{badge.requirement}</p>

      <span
        className={cn(
          "mt-3 text-xs px-2 py-0.5 rounded-full font-medium",
          earned
            ? "bg-white/70 text-gray-700"
            : "bg-gray-100 text-gray-500"
        )}
      >
        {CATEGORY_LABELS[badge.category]}
      </span>

      {earned && awardedAt && (
        <p className="mt-2 text-xs text-gray-400">
          Earned {formatDateTime(awardedAt)}
        </p>
      )}

      {!earned && (
        <p className="mt-2 text-xs text-gray-400 font-medium">🔒 Locked</p>
      )}
    </div>
  );
}
