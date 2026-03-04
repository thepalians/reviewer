"use client";

import { cn } from "@/lib/utils";
import TierBadge from "@/components/TierBadge";

interface LeaderboardEntry {
  rank: number;
  userId: number;
  name: string;
  points: number;
}

interface LeaderboardTableProps {
  rankings: LeaderboardEntry[];
  currentUserId: number;
}

const PODIUM_STYLES: Record<number, string> = {
  1: "bg-gradient-to-r from-yellow-400 to-yellow-300 text-white",
  2: "bg-gradient-to-r from-slate-400 to-slate-300 text-white",
  3: "bg-gradient-to-r from-amber-600 to-amber-500 text-white",
};

const RANK_ICONS: Record<number, string> = {
  1: "🥇",
  2: "🥈",
  3: "🥉",
};

export default function LeaderboardTable({
  rankings,
  currentUserId,
}: LeaderboardTableProps) {
  if (rankings.length === 0) {
    return (
      <div className="bg-white rounded-xl border border-gray-100 p-8 text-center text-gray-500">
        <p className="text-4xl mb-2">🏆</p>
        <p>No rankings yet. Start earning points!</p>
      </div>
    );
  }

  const top3 = rankings.slice(0, 3);
  const rest = rankings.slice(3);

  return (
    <div className="space-y-4">
      {/* Podium */}
      {top3.length > 0 && (
        <div className="grid grid-cols-3 gap-3">
          {top3.map((entry) => (
            <div
              key={entry.userId}
              className={cn(
                "rounded-xl p-4 text-center shadow-md",
                PODIUM_STYLES[entry.rank] ?? "bg-white border border-gray-100"
              )}
            >
              <p className="text-3xl">{RANK_ICONS[entry.rank] ?? entry.rank}</p>
              <p className="mt-1 font-bold text-sm truncate">
                {entry.name}
              </p>
              <p className="text-xs opacity-80">{entry.points} pts</p>
              <div className="mt-2 flex justify-center">
                <TierBadge points={entry.points} size="sm" />
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Rest of list */}
      {rest.length > 0 && (
        <div className="bg-white rounded-xl border border-gray-100 overflow-hidden">
          <table className="w-full text-sm">
            <thead>
              <tr className="bg-gray-50 border-b border-gray-100">
                <th className="px-4 py-3 text-left font-semibold text-gray-600 w-12">
                  #
                </th>
                <th className="px-4 py-3 text-left font-semibold text-gray-600">
                  User
                </th>
                <th className="px-4 py-3 text-right font-semibold text-gray-600">
                  Points
                </th>
                <th className="px-4 py-3 text-right font-semibold text-gray-600 hidden sm:table-cell">
                  Tier
                </th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-50">
              {rest.map((entry) => (
                <tr
                  key={entry.userId}
                  className={cn(
                    "transition-colors",
                    entry.userId === currentUserId
                      ? "bg-[#667eea]/5 font-semibold"
                      : "hover:bg-gray-50"
                  )}
                >
                  <td className="px-4 py-3 text-gray-500">{entry.rank}</td>
                  <td className="px-4 py-3 text-gray-900">
                    {entry.name}
                    {entry.userId === currentUserId && (
                      <span className="ml-2 text-xs text-[#667eea]">(You)</span>
                    )}
                  </td>
                  <td className="px-4 py-3 text-right text-gray-700">
                    {entry.points}
                  </td>
                  <td className="px-4 py-3 text-right hidden sm:table-cell">
                    <TierBadge points={entry.points} size="sm" />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
