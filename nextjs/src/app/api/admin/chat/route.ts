import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { query } from "@/lib/db";
import type { RowDataPacket } from "mysql2";

interface ChatUserRow extends RowDataPacket {
  user_id: number;
}

interface UserRow extends RowDataPacket {
  id: number;
  name: string;
  email: string;
}

interface LastMessageRow extends RowDataPacket {
  id: number;
  user_id: number;
  message: string;
  sender_type: string;
  created_at: string;
}

interface UnreadCountRow extends RowDataPacket {
  count: number;
}

export async function GET(_req: NextRequest) {
  const session = await auth();
  if (!session || session.user.userType !== "admin") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  try {
    // Get all unique user IDs that have sent messages, ordered by most recent message
    const userRows = await query<ChatUserRow>(
      `SELECT DISTINCT user_id
       FROM chat_messages
       ORDER BY (
         SELECT MAX(created_at) FROM chat_messages cm2 WHERE cm2.user_id = chat_messages.user_id
       ) DESC`
    );

    const conversations = await Promise.all(
      userRows.map(async ({ user_id }) => {
        const [userRows2, lastMessageRows, unreadRows] = await Promise.all([
          query<UserRow>(
            "SELECT id, name, email FROM users WHERE id = ? LIMIT 1",
            [user_id]
          ),
          query<LastMessageRow>(
            "SELECT id, user_id, message, sender_type, created_at FROM chat_messages WHERE user_id = ? ORDER BY created_at DESC LIMIT 1",
            [user_id]
          ),
          query<UnreadCountRow>(
            "SELECT COUNT(*) AS count FROM chat_messages WHERE user_id = ? AND sender_type = 'user'",
            [user_id]
          ),
        ]);

        const user = userRows2[0] ?? null;
        const lastMessage = lastMessageRows[0] ?? null;
        const unreadCount = unreadRows[0]?.count ?? 0;

        return {
          userId: user_id,
          user: user ? { id: user.id, name: user.name, email: user.email } : null,
          lastMessage: lastMessage
            ? {
                id: lastMessage.id,
                userId: lastMessage.user_id,
                message: lastMessage.message,
                senderType: lastMessage.sender_type,
                createdAt: lastMessage.created_at,
              }
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
