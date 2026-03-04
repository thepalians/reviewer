import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { prisma } from "@/lib/db";

export async function GET(
  _req: NextRequest,
  { params }: { params: Promise<{ userId: string }> }
) {
  const session = await auth();
  if (!session || session.user.userType !== "admin") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const { userId } = await params;
  const targetUserId = parseInt(userId);

  if (isNaN(targetUserId)) {
    return NextResponse.json({ error: "Invalid user ID" }, { status: 400 });
  }

  try {
    // Mark all user messages as read
    await prisma.chatMessage.updateMany({
      where: { userId: targetUserId, sender: "user", isRead: false },
      data: { isRead: true },
    });

    const messages = await prisma.chatMessage.findMany({
      where: { userId: targetUserId },
      orderBy: { createdAt: "asc" },
    });

    return NextResponse.json({
      success: true,
      data: messages.map((m) => ({ ...m, createdAt: m.createdAt.toISOString() })),
    });
  } catch (error) {
    console.error("Admin Chat userId GET error:", error);
    return NextResponse.json({ error: "Internal server error" }, { status: 500 });
  }
}

export async function POST(
  request: NextRequest,
  { params }: { params: Promise<{ userId: string }> }
) {
  const session = await auth();
  if (!session || session.user.userType !== "admin") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const { userId } = await params;
  const targetUserId = parseInt(userId);

  if (isNaN(targetUserId)) {
    return NextResponse.json({ error: "Invalid user ID" }, { status: 400 });
  }

  try {
    const body = await request.json();
    const { message } = body;

    if (!message || !message.trim()) {
      return NextResponse.json({ error: "Message is required" }, { status: 400 });
    }

    const chatMessage = await prisma.chatMessage.create({
      data: { userId: targetUserId, sender: "admin", message: message.trim() },
    });

    return NextResponse.json({
      success: true,
      data: { ...chatMessage, createdAt: chatMessage.createdAt.toISOString() },
    });
  } catch (error) {
    console.error("Admin Chat userId POST error:", error);
    return NextResponse.json({ error: "Internal server error" }, { status: 500 });
  }
}
