import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { query, execute } from "@/lib/db";
import type { RowDataPacket } from "mysql2";

interface ChatMessageRow extends RowDataPacket {
  id: number;
  user_id: number;
  message: string;
  sender_type: string;
  created_at: string;
}

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
    const messages = await query<ChatMessageRow>(
      "SELECT id, user_id, message, sender_type, created_at FROM chat_messages WHERE user_id = ? ORDER BY created_at ASC",
      [targetUserId]
    );

    return NextResponse.json({
      success: true,
      data: messages.map((m) => ({
        id: m.id,
        userId: m.user_id,
        message: m.message,
        senderType: m.sender_type,
        createdAt: m.created_at,
      })),
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

    const result = await execute(
      "INSERT INTO chat_messages (user_id, message, sender_type, created_at) VALUES (?, ?, 'admin', NOW())",
      [targetUserId, message.trim()]
    );

    const rows = await query<ChatMessageRow>(
      "SELECT id, user_id, message, sender_type, created_at FROM chat_messages WHERE id = ?",
      [result.insertId]
    );

    const chatMessage = rows[0];

    return NextResponse.json({
      success: true,
      data: {
        id: chatMessage.id,
        userId: chatMessage.user_id,
        message: chatMessage.message,
        senderType: chatMessage.sender_type,
        createdAt: chatMessage.created_at,
      },
    });
  } catch (error) {
    console.error("Admin Chat userId POST error:", error);
    return NextResponse.json({ error: "Internal server error" }, { status: 500 });
  }
}
