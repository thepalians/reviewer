import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { prisma } from "@/lib/db";

const PERIODS = ["week", "month", "all"] as const;
type Period = (typeof PERIODS)[number];

function getStartDate(period: Period): Date | null {
  const now = new Date();
  if (period === "week") {
    const d = new Date(now);
    d.setDate(d.getDate() - 7);
    return d;
  }
  if (period === "month") {
    const d = new Date(now);
    d.setMonth(d.getMonth() - 1);
    return d;
  }
  return null;
}

export async function GET(req: NextRequest) {
  const session = await auth();

  if (!session || session.user.userType !== "user") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const currentUserId = parseInt(session.user.id);
  const { searchParams } = new URL(req.url);
  const periodParam = searchParams.get("period") ?? "all";
  const period: Period = PERIODS.includes(periodParam as Period)
    ? (periodParam as Period)
    : "all";

  const startDate = getStartDate(period);

  try {
    const aggregated = await prisma.userPoint.groupBy({
      by: ["userId"],
      where: startDate ? { createdAt: { gte: startDate } } : undefined,
      _sum: { points: true },
      orderBy: { _sum: { points: "desc" } },
      take: 50,
    });

    if (aggregated.length === 0) {
      return NextResponse.json({ success: true, data: { rankings: [], currentUserRank: null } });
    }

    const userIds = aggregated.map((a) => a.userId);
    const users = await prisma.user.findMany({
      where: { id: { in: userIds } },
      select: { id: true, name: true },
    });

    const userMap = new Map(users.map((u) => [u.id, u.name]));

    const rankings = aggregated.map((a, idx) => ({
      rank: idx + 1,
      userId: a.userId,
      name: userMap.get(a.userId) ?? "Unknown",
      points: a._sum.points ?? 0,
    }));

    const currentUserRank = rankings.find((r) => r.userId === currentUserId) ?? null;

    return NextResponse.json({ success: true, data: { rankings, currentUserRank } });
  } catch (error) {
    console.error("Leaderboard API error:", error);
    return NextResponse.json(
      { error: "Failed to fetch leaderboard data" },
      { status: 500 }
    );
  }
}
