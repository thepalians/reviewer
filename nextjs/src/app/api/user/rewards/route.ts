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
    const [points, totalAgg] = await Promise.all([
      prisma.userPoint.findMany({
        where: { userId },
        orderBy: { createdAt: "desc" },
        take: 100,
      }),
      prisma.userPoint.aggregate({
        where: { userId },
        _sum: { points: true },
      }),
    ]);

    const totalPoints = totalAgg._sum.points ?? 0;

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
          createdAt: p.createdAt.toISOString(),
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
