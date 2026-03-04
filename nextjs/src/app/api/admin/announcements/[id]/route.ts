import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { prisma } from "@/lib/db";

export async function PUT(
  request: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const session = await auth();
  if (!session || session.user.userType !== "admin") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const { id } = await params;
  const announcementId = parseInt(id);

  if (isNaN(announcementId)) {
    return NextResponse.json({ error: "Invalid announcement ID" }, { status: 400 });
  }

  try {
    const body = await request.json();
    const { title, content, targetAudience, isActive, startDate, endDate } = body;

    const announcement = await prisma.announcement.update({
      where: { id: announcementId },
      data: {
        ...(title !== undefined && { title }),
        ...(content !== undefined && { content }),
        ...(targetAudience !== undefined && { targetAudience }),
        ...(isActive !== undefined && { isActive }),
        ...(startDate !== undefined && { startDate: startDate ? new Date(startDate) : null }),
        ...(endDate !== undefined && { endDate: endDate ? new Date(endDate) : null }),
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
    console.error("Admin Announcement PUT error:", error);
    return NextResponse.json({ error: "Internal server error" }, { status: 500 });
  }
}
