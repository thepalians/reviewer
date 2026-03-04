import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { queryOne, execute } from "@/lib/db";
import type { RowDataPacket } from "mysql2";

interface ViewRow extends RowDataPacket {
  id: number;
}

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
    // Upsert: insert view record if it doesn't already exist
    const existing = await queryOne<ViewRow>(
      "SELECT id FROM announcement_views WHERE announcement_id = ? AND user_id = ?",
      [announcementId, userId]
    );

    if (!existing) {
      await execute(
        "INSERT INTO announcement_views (user_id, announcement_id, created_at) VALUES (?, ?, NOW())",
        [userId, announcementId]
      );
    }

    return NextResponse.json({ success: true });
  } catch (error) {
    console.error("Announcement view error:", error);
    return NextResponse.json({ error: "Internal server error" }, { status: 500 });
  }
}
