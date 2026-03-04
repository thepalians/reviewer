import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { prisma } from "@/lib/db";

export async function GET(_req: NextRequest) {
  const session = await auth();
  if (!session || session.user.userType !== "admin") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  try {
    const announcements = await prisma.announcement.findMany({
      include: { _count: { select: { views: true } } },
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
        updatedAt: a.updatedAt.toISOString(),
        viewCount: a._count.views,
      })),
    });
  } catch (error) {
    console.error("Admin Announcements GET error:", error);
    return NextResponse.json({ error: "Internal server error" }, { status: 500 });
  }
}

export async function POST(request: NextRequest) {
  const session = await auth();
  if (!session || session.user.userType !== "admin") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  try {
    const body = await request.json();
    const { title, content, targetAudience, isActive, startDate, endDate } = body;

    if (!title || !content) {
      return NextResponse.json({ error: "title and content are required" }, { status: 400 });
    }

    const announcement = await prisma.announcement.create({
      data: {
        title,
        content,
        targetAudience: targetAudience ?? "all",
        isActive: isActive ?? true,
        startDate: startDate ? new Date(startDate) : null,
        endDate: endDate ? new Date(endDate) : null,
      },
    });

    return NextResponse.json({
      success: true,
      data: {
        ...announcement,
        startDate: announcement.startDate ? announcement.startDate.toISOString() : null,
        endDate: announcement.endDate ? announcement.endDate.toISOString() : null,
        createdAt: announcement.createdAt.toISOString(),
        updatedAt: announcement.updatedAt.toISOString(),
      },
    });
  } catch (error) {
    console.error("Admin Announcements POST error:", error);
    return NextResponse.json({ error: "Internal server error" }, { status: 500 });
  }
}
