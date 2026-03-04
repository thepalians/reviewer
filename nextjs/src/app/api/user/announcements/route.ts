import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { query } from "@/lib/db";
import type { RowDataPacket } from "mysql2";

interface AnnouncementRow extends RowDataPacket {
  id: number;
  title: string;
  content: string;
  target_audience: string;
  start_date: Date | null;
  end_date: Date | null;
  created_at: Date;
  // view join
  view_id: number | null;
}

export async function GET(_req: NextRequest) {
  const session = await auth();
  if (!session || session.user.userType !== "user") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const userId = parseInt(session.user.id);

  try {
    const announcements = await query<AnnouncementRow>(
      `SELECT a.id, a.title, a.content, a.target_audience, a.start_date, a.end_date, a.created_at,
              av.id AS view_id
       FROM announcements a
       LEFT JOIN announcement_views av ON av.announcement_id = a.id AND av.user_id = ?
       WHERE a.target_audience IN ('all', 'users', 'user')
         AND (a.start_date IS NULL OR a.start_date <= NOW())
         AND (a.end_date IS NULL OR a.end_date >= NOW())
       ORDER BY a.created_at DESC`,
      [userId]
    );

    return NextResponse.json({
      success: true,
      data: announcements.map((a) => ({
        id: a.id,
        title: a.title,
        content: a.content,
        targetAudience: a.target_audience,
        startDate: a.start_date
          ? (a.start_date instanceof Date ? a.start_date.toISOString() : String(a.start_date))
          : null,
        endDate: a.end_date
          ? (a.end_date instanceof Date ? a.end_date.toISOString() : String(a.end_date))
          : null,
        createdAt: a.created_at instanceof Date ? a.created_at.toISOString() : String(a.created_at),
        isRead: a.view_id != null,
      })),
    });
  } catch (error) {
    console.error("Announcements GET error:", error);
    return NextResponse.json({ error: "Internal server error" }, { status: 500 });
  }
}
