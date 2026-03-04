import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { prisma } from "@/lib/db";

export async function POST(
  _req: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const session = await auth();
  if (!session || session.user.userType !== "user") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const userId = parseInt(session.user.id);
  const { id } = await params;
  const announcementId = parseInt(id);

  if (isNaN(announcementId)) {
    return NextResponse.json({ error: "Invalid announcement ID" }, { status: 400 });
  }

  try {
    await prisma.announcementView.upsert({
      where: { announcementId_userId: { announcementId, userId } },
      update: {},
      create: { announcementId, userId },
    });

    return NextResponse.json({ success: true });
  } catch (error) {
    console.error("Announcement view error:", error);
    return NextResponse.json({ error: "Internal server error" }, { status: 500 });
  }
}
