import { NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { query } from "@/lib/db";
import type { RowDataPacket } from "mysql2";

interface ChatMessageRow extends RowDataPacket {
  id: number;
  message: string;
  created_at: Date;
}

interface AnnouncementRow extends RowDataPacket {
  id: number;
  title: string;
  content: string;
  created_at: Date;
}

interface WalletTransactionRow extends RowDataPacket {
  id: number;
  type: string;
  amount: number;
  description: string | null;
  created_at: Date;
}

interface AnnouncementViewRow extends RowDataPacket {
  announcement_id: number;
}

export async function GET() {
  const session = await auth();
  if (!session?.user?.id) {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const userId = parseInt(session.user.id);

  try {
    const viewRows = await query<AnnouncementViewRow>(
      "SELECT announcement_id FROM announcement_views WHERE user_id = ?",
      [userId]
    );
    const viewedAnnouncementIds = new Set(viewRows.map((r) => r.announcement_id));

    const [unreadMessages, recentAnnouncements, recentTransactions] = await Promise.all([
      query<ChatMessageRow>(
        `SELECT id, message, created_at FROM chat_messages
         WHERE user_id = ? AND sender_type = 'admin'
         ORDER BY created_at DESC LIMIT 20`,
        [userId]
      ),
      query<AnnouncementRow>(
        `SELECT id, title, content, created_at FROM announcements
         WHERE target_audience IN ('all', 'user')
           AND (start_date IS NULL OR start_date <= NOW())
           AND (end_date IS NULL OR end_date >= NOW())
         ORDER BY created_at DESC LIMIT 10`,
        []
      ),
      query<WalletTransactionRow>(
        `SELECT id, type, amount, description, created_at FROM wallet_transactions
         WHERE user_id = ?
         ORDER BY created_at DESC LIMIT 10`,
        [userId]
      ),
    ]);

    const notifications = [
      ...unreadMessages.map((m) => ({
        id: `chat-${m.id}`,
        type: "chat" as const,
        title: "New message from support",
        message: m.message,
        isRead: false,
        createdAt: m.created_at instanceof Date ? m.created_at.toISOString() : String(m.created_at),
      })),
      ...recentAnnouncements.map((a) => ({
        id: `announcement-${a.id}`,
        type: "system" as const,
        title: a.title,
        message: a.content,
        isRead: viewedAnnouncementIds.has(a.id),
        createdAt: a.created_at instanceof Date ? a.created_at.toISOString() : String(a.created_at),
      })),
      ...recentTransactions.map((t) => ({
        id: `wallet-${t.id}`,
        type: "wallet" as const,
        title: `Wallet ${t.type}`,
        message: t.description ?? `${t.type}: ₹${t.amount}`,
        isRead: true,
        createdAt: t.created_at instanceof Date ? t.created_at.toISOString() : String(t.created_at),
      })),
    ].sort(
      (a, b) => new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime()
    );

    return NextResponse.json({ notifications });
  } catch (error) {
    console.error("Notifications GET error:", error);
    return NextResponse.json({ error: "Internal server error" }, { status: 500 });
  }
}
