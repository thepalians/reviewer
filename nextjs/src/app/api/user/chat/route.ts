import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { query, execute } from "@/lib/db";
import type { RowDataPacket } from "mysql2";

interface ChatMessageRow extends RowDataPacket {
  id: number;
  user_id: number;
  message: string;
  sender_type: string;
  created_at: Date;
}

export async function GET(_req: NextRequest) {
  const session = await auth();
  if (!session || session.user.userType !== "user") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const userId = parseInt(session.user.id);

  try {
    const messages = await query<ChatMessageRow>(
      "SELECT * FROM chat_messages WHERE user_id = ? ORDER BY created_at ASC",
      [userId]
    );

    return NextResponse.json({
      success: true,
      data: messages.map((m) => ({
        id: m.id,
        userId: m.user_id,
        message: m.message,
        senderType: m.sender_type,
        createdAt: m.created_at instanceof Date ? m.created_at.toISOString() : String(m.created_at),
      })),
    });
  } catch (error) {
    console.error("Chat GET error:", error);
    return NextResponse.json({ error: "Internal server error" }, { status: 500 });
  }
}

export async function POST(request: NextRequest) {
  const session = await auth();
  if (!session || session.user.userType !== "user") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const userId = parseInt(session.user.id);

  try {
    const body = await request.json();
    const { message } = body;

    if (!message || !message.trim()) {
      return NextResponse.json({ error: "Message is required" }, { status: 400 });
    }

    const result = await execute(
      "INSERT INTO chat_messages (user_id, message, sender_type, created_at) VALUES (?, ?, 'user', NOW())",
      [userId, message.trim()]
    );

    const insertId = result.insertId;

    return NextResponse.json({
      success: true,
      data: {
        id: insertId,
        userId,
        message: message.trim(),
        senderType: "user",
        createdAt: new Date().toISOString(),
      },
    });
  } catch (error) {
    console.error("Chat POST error:", error);
    return NextResponse.json({ error: "Internal server error" }, { status: 500 });
  }
}
