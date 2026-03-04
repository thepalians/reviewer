"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import CampaignTimer from "@/components/CampaignTimer";
import { formatCurrency } from "@/lib/utils";

interface SocialPlatform {
  id: number;
  name: string;
  slug: string;
  icon: string | null;
}

interface Campaign {
  id: number;
  title: string;
  description: string | null;
  url: string | null;
  rewardAmount: number;
  requiredTime: number | null;
  platform: SocialPlatform;
}

interface CampaignTaskClientProps {
  campaign: Campaign;
  alreadyCompleted: boolean;
}

export default function CampaignTaskClient({
  campaign,
  alreadyCompleted,
}: CampaignTaskClientProps) {
  const router = useRouter();
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState("");
  const [success, setSuccess] = useState(false);

  const handleComplete = async () => {
    setError("");
    setIsSubmitting(true);
    try {
      const res = await fetch(`/api/user/social-hub/${campaign.id}/complete`, {
        method: "POST",
      });
      const data = await res.json();
      if (!res.ok) {
        setError(data.error ?? "Failed to complete task");
      } else {
        setSuccess(true);
        setTimeout(() => router.push("/user/social-hub"), 2000);
      }
    } catch {
      setError("Something went wrong. Please try again.");
    } finally {
      setIsSubmitting(false);
    }
  };

  if (alreadyCompleted || success) {
    return (
      <div className="bg-green-50 border border-green-200 rounded-xl p-6 text-center">
        <p className="text-3xl mb-2">✅</p>
        <p className="font-semibold text-green-700">Task already completed!</p>
        <p className="text-sm text-green-600 mt-1">
          You earned {formatCurrency(campaign.rewardAmount)} for this campaign.
        </p>
      </div>
    );
  }

  return (
    <div className="space-y-4">
      {/* Campaign details */}
      <div className="bg-white rounded-xl border border-gray-100 shadow-sm p-5 space-y-3">
        <div className="flex items-center justify-between">
          <span className="text-sm text-gray-500">Reward</span>
          <span className="font-bold text-green-600">{formatCurrency(campaign.rewardAmount)}</span>
        </div>
        {campaign.requiredTime != null && (
          <div className="flex items-center justify-between">
            <span className="text-sm text-gray-500">Required time</span>
            <span className="font-medium text-gray-800">{campaign.requiredTime}s</span>
          </div>
        )}
        {campaign.url && (
          <div>
            <a
              href={campaign.url}
              target="_blank"
              rel="noopener noreferrer"
              className="inline-flex items-center gap-1 text-sm text-[#667eea] hover:underline"
            >
              🔗 Open link
            </a>
          </div>
        )}
      </div>

      {/* Timer */}
      <div className="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
        {campaign.requiredTime != null && campaign.requiredTime > 0 ? (
          <CampaignTimer
            requiredTime={campaign.requiredTime}
            onComplete={handleComplete}
            isSubmitting={isSubmitting}
          />
        ) : (
          <button
            onClick={handleComplete}
            disabled={isSubmitting}
            className="w-full py-3 rounded-lg bg-gradient-to-r from-[#667eea] to-[#764ba2] text-white font-medium hover:opacity-90 disabled:opacity-50 transition-opacity"
          >
            {isSubmitting ? "Submitting..." : "🎉 Complete & Claim Reward"}
          </button>
        )}
      </div>

      {error && (
        <div className="p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-600">
          ❌ {error}
        </div>
      )}
    </div>
  );
}
