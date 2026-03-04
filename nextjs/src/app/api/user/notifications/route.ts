import { NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { prisma } from "@/lib/db";

export async function GET() {
  const session = await auth();
  if (!session?.user?.id) {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  // Notifications are derived from chat messages, announcements and wallet transactions
  const userId = parseInt(session.user.id);

  const viewedAnnouncementIds = await prisma.announcementView.findMany({
    where: { userId },
    select: { announcementId: true },
  }).then((rows) => new Set(rows.map((r) => r.announcementId)));

  const [unreadMessages, recentAnnouncements, recentTransactions] = await Promise.all([
    prisma.chatMessage.findMany({
      where: { userId, sender: "admin", isRead: false },
      orderBy: { createdAt: "desc" },
      take: 20,
      select: { id: true, message: true, createdAt: true, isRead: true },
    }),
    prisma.announcement.findMany({
      where: {
        isActive: true,
        OR: [{ targetAudience: "all" }, { targetAudience: "user" }],
      },
      orderBy: { createdAt: "desc" },
      take: 10,
      select: { id: true, title: true, content: true, createdAt: true },
    }),
    prisma.walletTransaction.findMany({
      where: { userId },
      orderBy: { createdAt: "desc" },
      take: 10,
      select: { id: true, type: true, amount: true, description: true, createdAt: true },
    }),
  ]);

  const notifications = [
    ...unreadMessages.map((m) => ({
      id: `chat-${m.id}`,
      type: "chat" as const,
      title: "New message from support",
      message: m.message,
      isRead: m.isRead,
      createdAt: m.createdAt,
    })),
    ...recentAnnouncements.map((a) => ({
      id: `announcement-${a.id}`,
      type: "system" as const,
      title: a.title,
      message: a.content,
      isRead: viewedAnnouncementIds.has(a.id),
      createdAt: a.createdAt,
    })),
    ...recentTransactions.map((t) => ({
      id: `wallet-${t.id}`,
      type: "wallet" as const,
      title: `Wallet ${t.type}`,
      message: t.description ?? `${t.type}: ₹${t.amount}`,
      isRead: true,
      createdAt: t.createdAt,
    })),
  ].sort((a, b) => new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime());

  return NextResponse.json({ notifications });
}
