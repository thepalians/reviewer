"use client";

import { useState } from "react";
import LeaderboardTable from "@/components/LeaderboardTable";

interface LeaderboardEntry {
  rank: number;
  userId: number;
  name: string;
  points: number;
}

type Period = "week" | "month" | "all";

interface LeaderboardPageClientProps {
  initialRankings: LeaderboardEntry[];
  currentUserId: number;
}

const PERIOD_LABELS: Record<Period, string> = {
  week: "This Week",
  month: "This Month",
  all: "All Time",
};

export default function LeaderboardPageClient({
  initialRankings,
  currentUserId,
}: LeaderboardPageClientProps) {
  const [period, setPeriod] = useState<Period>("all");
  const [rankings, setRankings] = useState<LeaderboardEntry[]>(initialRankings);
  const [loading, setLoading] = useState(false);

  const handlePeriodChange = async (newPeriod: Period) => {
    if (newPeriod === period) return;
    setPeriod(newPeriod);
    setLoading(true);
    try {
      const res = await fetch(`/api/user/leaderboard?period=${newPeriod}`);
      const json = await res.json();
      setRankings(json.data?.rankings ?? []);
    } catch {
      // keep current rankings on error
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="space-y-4">
      {/* Period tabs */}
      <div className="flex rounded-lg bg-gray-100 p-1">
        {(Object.keys(PERIOD_LABELS) as Period[]).map((p) => (
          <button
            key={p}
            onClick={() => handlePeriodChange(p)}
            className={`flex-1 py-2 rounded-md text-sm font-medium transition-all duration-200 ${
              period === p
                ? "bg-white shadow text-gray-900"
                : "text-gray-500 hover:text-gray-700"
            }`}
          >
            {PERIOD_LABELS[p]}
          </button>
        ))}
      </div>

      {loading ? (
        <div className="flex items-center justify-center py-12 text-gray-500">
          <div className="animate-spin text-2xl mr-3">⟳</div>
          Loading...
        </div>
      ) : (
        <LeaderboardTable rankings={rankings} currentUserId={currentUserId} />
      )}
    </div>
  );
}
