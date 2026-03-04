import { NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { prisma } from "@/lib/db";

export async function GET() {
  const session = await auth();

  if (!session || session.user.userType !== "user") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const userId = parseInt(session.user.id);

  try {
    const userBadges = await prisma.userBadge.findMany({
      where: { userId },
      orderBy: { awardedAt: "desc" },
    });

    return NextResponse.json({
      success: true,
      data: {
        earnedBadgeIds: userBadges.map((b) => b.badgeId),
        badges: userBadges.map((b) => ({
          id: b.id,
          badgeId: b.badgeId,
          awardedAt: b.awardedAt.toISOString(),
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
