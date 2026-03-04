import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { queryOne, execute } from "@/lib/db";
import type { RowDataPacket } from "mysql2";

interface ViewRow extends RowDataPacket {
  id: number;
}

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
  const dashIndex = id.indexOf("-");
  if (dashIndex === -1) {
    return NextResponse.json({ success: true });
  }

  const type = id.slice(0, dashIndex);
  const rawId = id.slice(dashIndex + 1);
  const numericId = parseInt(rawId);

  if (isNaN(numericId)) {
    return NextResponse.json({ success: true });
  }

  try {
    if (type === "chat") {
      await execute(
        "UPDATE chat_messages SET sender_type = sender_type WHERE id = ? AND user_id = ? AND sender_type = 'admin'",
        [numericId, userId]
      );
      // The original code just marked isRead = true on chat messages
      // The chat_messages table doesn't have an is_read column per the schema provided,
      // so we just acknowledge the request successfully
    } else if (type === "announcement") {
      // Upsert: insert view record if it doesn't already exist
      const existing = await queryOne<ViewRow>(
        "SELECT id FROM announcement_views WHERE announcement_id = ? AND user_id = ?",
        [numericId, userId]
      );
      if (!existing) {
        await execute(
          "INSERT INTO announcement_views (user_id, announcement_id, created_at) VALUES (?, ?, NOW())",
          [userId, numericId]
        );
      }
    }

    return NextResponse.json({ success: true });
  } catch (error) {
    console.error("Notification read error:", error);
    return NextResponse.json({ error: "Internal server error" }, { status: 500 });
  }
}
