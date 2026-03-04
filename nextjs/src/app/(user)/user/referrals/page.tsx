"use client";

import { useState, useEffect, useCallback } from "react";
import Button from "@/components/ui/Button";
import { formatDate } from "@/lib/utils";

interface ReferredUser {
  id: number;
  name: string;
  email: string;
  createdAt: string;
  status: string;
}

interface Referral {
  id: number;
  rewardPaid: boolean;
  rewardAmount: number | null;
  createdAt: string;
  referred: ReferredUser;
}

interface ReferralData {
  referralCode: string | null;
  totalReferred: number;
  totalRewards: number;
  referrals: Referral[];
}

export default function UserReferralsPage() {
  const [data, setData] = useState<ReferralData | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [generating, setGenerating] = useState(false);
  const [copied, setCopied] = useState(false);

  const fetchReferrals = useCallback(async () => {
    setIsLoading(true);
    const res = await fetch("/api/user/referrals");
    const json = await res.json();
    if (json.success) setData(json.data);
    setIsLoading(false);
  }, []);

  useEffect(() => {
    fetchReferrals();
  }, [fetchReferrals]);

  const generateCode = async () => {
    setGenerating(true);
    const res = await fetch("/api/user/referrals/generate-code", { method: "POST" });
    const json = await res.json();
    if (json.success) await fetchReferrals();
    setGenerating(false);
  };

  const copyLink = () => {
    if (!data?.referralCode) return;
    const link = `${window.location.origin}/register?ref=${data.referralCode}`;
    navigator.clipboard.writeText(link);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  const shareWhatsApp = () => {
    if (!data?.referralCode) return;
    const link = `${window.location.origin}/register?ref=${data.referralCode}`;
    window.open(
      `https://wa.me/?text=${encodeURIComponent(`Join ReviewFlow and earn rewards! Use my referral link: ${link}`)}`,
      "_blank"
    );
  };

  const shareTelegram = () => {
    if (!data?.referralCode) return;
    const link = `${window.location.origin}/register?ref=${data.referralCode}`;
    window.open(
      `https://t.me/share/url?url=${encodeURIComponent(link)}&text=${encodeURIComponent("Join ReviewFlow and earn rewards!")}`,
      "_blank"
    );
  };

  if (isLoading) {
    return (
      <div className="flex justify-center py-12">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-[#667eea]" />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="bg-gradient-to-r from-[#667eea] to-[#764ba2] rounded-2xl p-6 text-white">
        <h1 className="text-2xl font-bold">🔗 My Referrals</h1>
        <p className="text-white/80 mt-1">Invite friends and earn rewards</p>
      </div>

      {/* Referral Code Card */}
      <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h2 className="text-lg font-semibold text-gray-900 mb-4">Your Referral Code</h2>
        {data?.referralCode ? (
          <div className="space-y-4">
            <div className="flex items-center gap-3 bg-gray-50 rounded-xl p-4">
              <span className="text-2xl font-bold tracking-widest text-[#667eea] flex-1">
                {data.referralCode}
              </span>
              <Button variant="secondary" size="sm" onClick={copyLink}>
                {copied ? "✅ Copied!" : "📋 Copy Link"}
              </Button>
            </div>
            <div className="flex gap-3">
              <Button
                variant="secondary"
                size="sm"
                onClick={shareWhatsApp}
                className="flex-1"
              >
                📱 Share on WhatsApp
              </Button>
              <Button
                variant="secondary"
                size="sm"
                onClick={shareTelegram}
                className="flex-1"
              >
                ✈️ Share on Telegram
              </Button>
            </div>
          </div>
        ) : (
          <div className="text-center py-4">
            <p className="text-gray-500 mb-4">You don&apos;t have a referral code yet.</p>
            <Button
              variant="primary"
              isLoading={generating}
              onClick={generateCode}
            >
              Generate Referral Code
            </Button>
          </div>
        )}
      </div>

      {/* Stats */}
      <div className="grid grid-cols-2 gap-4">
        <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 text-center">
          <p className="text-3xl font-bold text-[#667eea]">{data?.totalReferred ?? 0}</p>
          <p className="text-sm text-gray-500 mt-1">Total Referred</p>
        </div>
        <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 text-center">
          <p className="text-3xl font-bold text-green-600">₹{(data?.totalRewards ?? 0).toFixed(2)}</p>
          <p className="text-sm text-gray-500 mt-1">Rewards Earned</p>
        </div>
      </div>

      {/* Referrals List */}
      <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div className="p-6 border-b border-gray-50">
          <h2 className="text-lg font-semibold text-gray-900">Referred Users</h2>
        </div>
        {!data?.referrals.length ? (
          <div className="p-8 text-center text-gray-500">
            <p className="text-4xl mb-2">👥</p>
            <p>No referrals yet. Share your code to get started!</p>
          </div>
        ) : (
          <div className="divide-y divide-gray-50">
            {data.referrals.map((r) => (
              <div key={r.id} className="flex items-center justify-between px-6 py-4">
                <div>
                  <p className="font-medium text-gray-900">{r.referred.name}</p>
                  <p className="text-sm text-gray-500">{r.referred.email}</p>
                  <p className="text-xs text-gray-400">Joined {formatDate(r.createdAt)}</p>
                </div>
                <div className="text-right">
                  {r.rewardPaid ? (
                    <span className="inline-flex items-center gap-1 text-xs bg-green-100 text-green-700 px-2 py-1 rounded-full">
                      ✅ Reward: ₹{r.rewardAmount?.toFixed(2)}
                    </span>
                  ) : (
                    <span className="inline-flex items-center gap-1 text-xs bg-yellow-100 text-yellow-700 px-2 py-1 rounded-full">
                      ⏳ Pending
                    </span>
                  )}
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
