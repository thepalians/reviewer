import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { prisma } from "@/lib/db";

export async function PUT(
  _request: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const session = await auth();
  if (!session?.user?.id) {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const { id } = await params;
  const userId = parseInt(session.user.id);

  // Notification IDs are prefixed: "chat-123", "announcement-456", etc.
  const [type, rawId] = id.split("-");
  const numericId = parseInt(rawId);

  if (type === "chat" && !isNaN(numericId)) {
    await prisma.chatMessage.updateMany({
      where: { id: numericId, userId, sender: "admin" },
      data: { isRead: true },
    });
  } else if (type === "announcement" && !isNaN(numericId)) {
    await prisma.announcementView.upsert({
      where: { announcementId_userId: { announcementId: numericId, userId } },
      update: {},
      create: { announcementId: numericId, userId },
    });
  }

  return NextResponse.json({ success: true });
}
