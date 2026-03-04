"use client";

import { useState } from "react";
import CampaignCard from "@/components/CampaignCard";
import PlatformFilter from "@/components/PlatformFilter";

interface SocialPlatform {
  id: number;
  name: string;
  slug: string;
  icon: string | null;
  isActive: boolean;
}

interface Campaign {
  id: number;
  title: string;
  description: string | null;
  rewardAmount: number;
  requiredTime: number | null;
  platform: SocialPlatform;
}

interface SocialHubClientProps {
  campaigns: Campaign[];
  platforms: SocialPlatform[];
}

export default function SocialHubClient({ campaigns, platforms }: SocialHubClientProps) {
  const [selectedPlatform, setSelectedPlatform] = useState("all");

  const filtered =
    selectedPlatform === "all"
      ? campaigns
      : campaigns.filter((c) => c.platform.slug === selectedPlatform);

  return (
    <div className="space-y-4">
      <PlatformFilter
        platforms={platforms}
        selected={selectedPlatform}
        onChange={setSelectedPlatform}
      />

      {filtered.length === 0 ? (
        <div className="text-center py-12 text-gray-500">
          <p className="text-4xl mb-3">📭</p>
          <p className="font-medium">No campaigns available</p>
          <p className="text-sm mt-1">Check back later for new social tasks.</p>
        </div>
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          {filtered.map((campaign) => (
            <CampaignCard key={campaign.id} {...campaign} />
          ))}
        </div>
      )}
    </div>
  );
}
