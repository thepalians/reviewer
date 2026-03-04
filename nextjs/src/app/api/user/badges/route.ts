import { NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { query } from "@/lib/db";
import type { RowDataPacket } from "mysql2";

interface UserBadgeRow extends RowDataPacket {
  id: number;
  user_id: number;
  badge_id: number;
  created_at: Date;
}

export async function GET() {
  const session = await auth();

  if (!session || session.user.userType !== "user") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const userId = parseInt(session.user.id);

  try {
    const userBadges = await query<UserBadgeRow>(
      "SELECT * FROM user_badges WHERE user_id = ? ORDER BY created_at DESC",
      [userId]
    );

    return NextResponse.json({
      success: true,
      data: {
        earnedBadgeIds: userBadges.map((b) => b.badge_id),
        badges: userBadges.map((b) => ({
          id: b.id,
          badgeId: b.badge_id,
          awardedAt: b.created_at instanceof Date ? b.created_at.toISOString() : String(b.created_at),
        })),
      },
    });
  } catch (error) {
    console.error("Badges API error:", error);
    return NextResponse.json(
      { error: "Failed to fetch badges data" },
      { status: 500 }
    );
  }
}
