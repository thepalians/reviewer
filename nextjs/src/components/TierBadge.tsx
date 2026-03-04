"use client";

import { getTier, TIERS } from "@/lib/badges";
import { cn } from "@/lib/utils";

interface TierBadgeProps {
  points: number;
  size?: "sm" | "md" | "lg";
}

export default function TierBadge({ points, size = "md" }: TierBadgeProps) {
  const tier = getTier(points);

  const sizeClasses = {
    sm: "text-xs px-2 py-0.5",
    md: "text-sm px-3 py-1",
    lg: "text-base px-4 py-1.5",
  };

  return (
    <span
      className={cn(
        "inline-flex items-center gap-1 rounded-full font-semibold text-white bg-gradient-to-r",
        tier.gradient,
        sizeClasses[size]
      )}
    >
      {tier.emoji} {tier.name}
    </span>
  );
}

interface TierProgressProps {
  points: number;
}

export function TierProgress({ points }: TierProgressProps) {
  const tier = getTier(points);
  const nextTierIdx = TIERS.findIndex((t) => t.name === tier.name) + 1;
  const nextTier = TIERS[nextTierIdx] ?? null;

  if (!nextTier) {
    return (
      <div className="space-y-2">
        <div className="flex items-center justify-between text-sm">
          <span className="font-medium text-gray-700">
            {tier.emoji} {tier.name} (Max Tier)
          </span>
          <span className="text-gray-500">{points} pts</span>
        </div>
        <div className="w-full bg-gray-200 rounded-full h-3">
          <div
            className={`h-3 rounded-full bg-gradient-to-r ${tier.gradient}`}
            style={{ width: "100%" }}
          />
        </div>
      </div>
    );
  }

  const range = nextTier.min - tier.min;
  const progress = Math.min(((points - tier.min) / range) * 100, 100);

  return (
    <div className="space-y-2">
      <div className="flex items-center justify-between text-sm">
        <span className="font-medium text-gray-700">
          {tier.emoji} {tier.name}
        </span>
        <span className="text-gray-500">
          {points} / {nextTier.min} pts to {nextTier.emoji} {nextTier.name}
        </span>
      </div>
      <div className="w-full bg-gray-200 rounded-full h-3">
        <div
          className={`h-3 rounded-full bg-gradient-to-r ${tier.gradient} transition-all duration-500`}
          style={{ width: `${progress}%` }}
        />
      </div>
    </div>
  );
}
