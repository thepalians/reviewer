import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { prisma } from "@/lib/db";

export async function GET(_req: NextRequest) {
  const session = await auth();
  if (!session || session.user.userType !== "user") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const userId = parseInt(session.user.id);

  try {
    const now = new Date();

    const announcements = await prisma.announcement.findMany({
      where: {
        isActive: true,
        OR: [
          { targetAudience: "all" },
          { targetAudience: "users" },
        ],
        AND: [
          { OR: [{ startDate: null }, { startDate: { lte: now } }] },
          { OR: [{ endDate: null }, { endDate: { gte: now } }] },
        ],
      },
      include: {
        views: { where: { userId }, select: { id: true } },
      },
      orderBy: { createdAt: "desc" },
    });

    return NextResponse.json({
      success: true,
      data: announcements.map((a) => ({
        id: a.id,
        title: a.title,
        content: a.content,
        targetAudience: a.targetAudience,
        isActive: a.isActive,
        startDate: a.startDate ? a.startDate.toISOString() : null,
        endDate: a.endDate ? a.endDate.toISOString() : null,
        createdAt: a.createdAt.toISOString(),
        isRead: a.views.length > 0,
      })),
    });
  } catch (error) {
    console.error("Announcements GET error:", error);
    return NextResponse.json({ error: "Internal server error" }, { status: 500 });
  }
}
