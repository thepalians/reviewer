import { NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { query, queryOne } from "@/lib/db";
import type { RowDataPacket } from "mysql2";

interface UserPointRow extends RowDataPacket {
  id: number;
  user_id: number;
  points: number;
  type: string;
  description: string | null;
  created_at: Date;
}

interface TotalRow extends RowDataPacket {
  total: number | null;
}

export async function GET() {
  const session = await auth();

  if (!session || session.user.userType !== "user") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const userId = parseInt(session.user.id);

  try {
    const [points, totalRow] = await Promise.all([
      query<UserPointRow>(
        "SELECT * FROM user_points WHERE user_id = ? ORDER BY created_at DESC LIMIT 100",
        [userId]
      ),
      queryOne<TotalRow>(
        "SELECT SUM(points) AS total FROM user_points WHERE user_id = ?",
        [userId]
      ),
    ]);

    const totalPoints = Number(totalRow?.total ?? 0);

    const breakdown = points.reduce<Record<string, number>>((acc, p) => {
      acc[p.type] = (acc[p.type] ?? 0) + p.points;
      return acc;
    }, {});

    return NextResponse.json({
      success: true,
      data: {
        totalPoints,
        breakdown,
        history: points.map((p) => ({
          id: p.id,
          points: p.points,
          type: p.type,
          description: p.description,
          createdAt: p.created_at instanceof Date ? p.created_at.toISOString() : String(p.created_at),
        })),
      },
    });
  } catch (error) {
    console.error("Rewards API error:", error);
    return NextResponse.json(
      { error: "Failed to fetch rewards data" },
      { status: 500 }
    );
  }
}
