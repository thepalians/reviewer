"use client";

import Link from "next/link";
import Badge from "@/components/ui/Badge";
import { formatCurrency } from "@/lib/utils";

interface SocialPlatform {
  id: number;
  name: string;
  slug: string;
  icon: string | null;
}

interface CampaignCardProps {
  id: number;
  title: string;
  description: string | null;
  rewardAmount: number;
  requiredTime: number | null;
  platform: SocialPlatform;
}

export default function CampaignCard({
  id,
  title,
  description,
  rewardAmount,
  requiredTime,
  platform,
}: CampaignCardProps) {
  return (
    <div className="bg-white rounded-xl border border-gray-100 shadow-sm p-4 hover:shadow-md transition-shadow duration-200">
      <div className="flex items-start justify-between gap-3">
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2 mb-1">
            {platform.icon && (
              <span className="text-lg">{platform.icon}</span>
            )}
            <Badge label={platform.name} className="capitalize" />
          </div>
          <p className="font-medium text-gray-900 truncate">{title}</p>
          {description && (
            <p className="text-sm text-gray-500 mt-0.5 line-clamp-2">{description}</p>
          )}
        </div>
        <div className="text-right shrink-0">
          <p className="font-bold text-green-600">{formatCurrency(rewardAmount)}</p>
          {requiredTime != null && (
            <p className="text-xs text-gray-400 mt-0.5">⏱ {requiredTime}s</p>
          )}
        </div>
      </div>
      <div className="mt-3">
        <Link
          href={`/user/social-hub/${id}`}
          className="inline-flex items-center justify-center w-full py-2 rounded-lg bg-gradient-to-r from-[#667eea] to-[#764ba2] text-white text-sm font-medium hover:opacity-90 transition-opacity"
        >
          Start Task →
        </Link>
      </div>
    </div>
  );
}
