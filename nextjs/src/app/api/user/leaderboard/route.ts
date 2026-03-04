import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { query } from "@/lib/db";
import type { RowDataPacket } from "mysql2";

const PERIODS = ["week", "month", "all"] as const;
type Period = (typeof PERIODS)[number];

interface LeaderboardRow extends RowDataPacket {
  user_id: number;
  name: string;
  total_points: number;
}

function getStartDateStr(period: Period): string | null {
  const now = new Date();
  if (period === "week") {
    const d = new Date(now);
    d.setDate(d.getDate() - 7);
    return d.toISOString().slice(0, 19).replace("T", " ");
  }
  if (period === "month") {
    const d = new Date(now);
    d.setMonth(d.getMonth() - 1);
    return d.toISOString().slice(0, 19).replace("T", " ");
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

  const startDateStr = getStartDateStr(period);

  try {
    let sql: string;
    let params: unknown[];

    if (startDateStr) {
      sql = `SELECT up.user_id, u.name, SUM(up.points) AS total_points
             FROM user_points up
             JOIN users u ON u.id = up.user_id
             WHERE up.created_at >= ?
             GROUP BY up.user_id, u.name
             ORDER BY total_points DESC
             LIMIT 50`;
      params = [startDateStr];
    } else {
      sql = `SELECT up.user_id, u.name, SUM(up.points) AS total_points
             FROM user_points up
             JOIN users u ON u.id = up.user_id
             GROUP BY up.user_id, u.name
             ORDER BY total_points DESC
             LIMIT 50`;
      params = [];
    }

    const aggregated = await query<LeaderboardRow>(sql, params);

    if (aggregated.length === 0) {
      return NextResponse.json({
        success: true,
        data: { rankings: [], currentUserRank: null },
      });
    }

    const rankings = aggregated.map((a, idx) => ({
      rank: idx + 1,
      userId: a.user_id,
      name: a.name ?? "Unknown",
      points: Number(a.total_points ?? 0),
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
