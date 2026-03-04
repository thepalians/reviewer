import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { prisma } from "@/lib/db";

export async function GET(_req: NextRequest) {
  const session = await auth();
  if (!session || session.user.userType !== "admin") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  try {
    // Get all unique user IDs that have sent messages
    const userIds = await prisma.chatMessage.findMany({
      distinct: ["userId"],
      select: { userId: true },
      orderBy: { createdAt: "desc" },
    });

    const conversations = await Promise.all(
      userIds.map(async ({ userId }) => {
        const [user, lastMessage, unreadCount] = await Promise.all([
          prisma.user.findUnique({
            where: { id: userId },
            select: { id: true, name: true, email: true },
          }),
          prisma.chatMessage.findFirst({
            where: { userId },
            orderBy: { createdAt: "desc" },
          }),
          prisma.chatMessage.count({
            where: { userId, sender: "user", isRead: false },
          }),
        ]);

        return {
          userId,
          user,
          lastMessage: lastMessage
            ? { ...lastMessage, createdAt: lastMessage.createdAt.toISOString() }
            : null,
          unreadCount,
        };
      })
    );

    return NextResponse.json({ success: true, data: conversations });
  } catch (error) {
    console.error("Admin Chat GET error:", error);
    return NextResponse.json({ error: "Internal server error" }, { status: 500 });
  }
}
