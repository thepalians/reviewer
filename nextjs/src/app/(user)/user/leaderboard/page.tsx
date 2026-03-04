import { auth } from "@/lib/auth";
import { redirect } from "next/navigation";
import { prisma } from "@/lib/db";
import type { Metadata } from "next";
import LeaderboardPageClient from "./LeaderboardPageClient";

export const metadata: Metadata = { title: "Leaderboard" };

export default async function LeaderboardPage() {
  const session = await auth();
  if (!session || session.user.userType !== "user") redirect("/login");

  const currentUserId = parseInt(session.user.id);

  const aggregated = await prisma.userPoint.groupBy({
    by: ["userId"],
    _sum: { points: true },
    orderBy: { _sum: { points: "desc" } },
    take: 50,
  });

  const userIds = aggregated.map((a) => a.userId);

  const users =
    userIds.length > 0
      ? await prisma.user.findMany({
          where: { id: { in: userIds } },
          select: { id: true, name: true },
        })
      : [];

  const userMap = new Map(users.map((u) => [u.id, u.name]));

  const rankings = aggregated.map((a, idx) => ({
    rank: idx + 1,
    userId: a.userId,
    name: userMap.get(a.userId) ?? "Unknown",
    points: a._sum.points ?? 0,
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
