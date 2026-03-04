import { auth } from "@/lib/auth";
import { redirect } from "next/navigation";
import { query } from "@/lib/db";
import type { Metadata } from "next";
import LeaderboardPageClient from "./LeaderboardPageClient";
import type { RowDataPacket } from "mysql2";

export const metadata: Metadata = { title: "Leaderboard" };

interface RankingRow extends RowDataPacket {
  user_id: number;
  total_points: number;
  name: string;
}

export default async function LeaderboardPage() {
  const session = await auth();
  if (!session || session.user.userType !== "user") redirect("/login");

  const currentUserId = parseInt(session.user.id);

  const rankingRows = await query<RankingRow>(
    `SELECT up.user_id, SUM(up.points) AS total_points, u.name
     FROM user_points up
     JOIN users u ON u.id = up.user_id
     GROUP BY up.user_id, u.name
     ORDER BY total_points DESC
     LIMIT 50`,
    []
  );

  const rankings = rankingRows.map((row, idx) => ({
    rank: idx + 1,
    userId: row.user_id,
    name: row.name ?? "Unknown",
    points: Number(row.total_points ?? 0),
  }));

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="bg-gradient-to-r from-[#667eea] to-[#764ba2] rounded-2xl p-6 text-white">
        <h1 className="text-2xl font-bold">🏆 Leaderboard</h1>
        <p className="text-white/80 mt-1">Top earners ranked by total points</p>
      </div>

      <LeaderboardPageClient
        initialRankings={rankings}
        currentUserId={currentUserId}
      />
    </div>
  );
}
