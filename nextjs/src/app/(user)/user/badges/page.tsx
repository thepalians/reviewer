import { auth } from "@/lib/auth";
import { redirect } from "next/navigation";
import { query } from "@/lib/db";
import type { Metadata } from "next";
import BadgeCard from "@/components/BadgeCard";
import { BADGE_DEFINITIONS } from "@/lib/badges";
import type { RowDataPacket } from "mysql2";

export const metadata: Metadata = { title: "Badges" };

interface UserBadgeRow extends RowDataPacket {
  id: number;
  user_id: number;
  badge_id: string;
  created_at: Date;
}

export default async function BadgesPage() {
  const session = await auth();
  if (!session || session.user.userType !== "user") redirect("/login");

  const userId = parseInt(session.user.id);

  const userBadges = await query<UserBadgeRow>(
    "SELECT * FROM user_badges WHERE user_id = ? ORDER BY created_at DESC",
    [userId]
  );

  const earnedMap = new Map(
    userBadges.map((b) => [b.badge_id, new Date(b.created_at).toISOString()])
  );

  const categories = ["task", "social", "streak", "special"] as const;

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="bg-gradient-to-r from-[#667eea] to-[#764ba2] rounded-2xl p-6 text-white">
        <h1 className="text-2xl font-bold">🏅 Badges</h1>
        <p className="text-white/80 mt-1">
          {earnedMap.size} / {BADGE_DEFINITIONS.length} badges earned
        </p>
      </div>

      {/* Stats */}
      <div className="bg-white rounded-xl border border-gray-100 p-4 shadow-sm flex items-center gap-4">
        <div className="flex-1 text-center">
          <p className="text-2xl font-bold text-gray-900">{earnedMap.size}</p>
          <p className="text-xs text-gray-500">Earned</p>
        </div>
        <div className="w-px h-10 bg-gray-100" />
        <div className="flex-1 text-center">
          <p className="text-2xl font-bold text-gray-900">
            {BADGE_DEFINITIONS.length - earnedMap.size}
          </p>
          <p className="text-xs text-gray-500">Remaining</p>
        </div>
        <div className="w-px h-10 bg-gray-100" />
        <div className="flex-1 text-center">
          <p className="text-2xl font-bold text-gray-900">
            {Math.round((earnedMap.size / BADGE_DEFINITIONS.length) * 100)}%
          </p>
          <p className="text-xs text-gray-500">Complete</p>
        </div>
      </div>

      {/* Badges by category */}
      {categories.map((cat) => {
        const catBadges = BADGE_DEFINITIONS.filter((b) => b.category === cat);
        const catLabels: Record<typeof cat, string> = {
          task: "📋 Task Badges",
          social: "📱 Social Badges",
          streak: "🔥 Streak Badges",
          special: "⭐ Special Badges",
        };

        return (
          <div key={cat}>
            <h2 className="text-lg font-semibold text-gray-900 mb-3">
              {catLabels[cat]}
            </h2>
            <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
              {catBadges.map((badge) => (
                <BadgeCard
                  key={badge.id}
                  badge={badge}
                  earned={earnedMap.has(badge.id)}
                  awardedAt={earnedMap.get(badge.id)}
                />
              ))}
            </div>
          </div>
        );
      })}
    </div>
  );
}
