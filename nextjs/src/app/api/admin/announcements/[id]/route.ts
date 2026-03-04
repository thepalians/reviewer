import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { queryOne, execute } from "@/lib/db";
import type { RowDataPacket } from "mysql2";

interface AnnouncementRow extends RowDataPacket {
  id: number;
  title: string;
  content: string;
  target_audience: string;
  status: string;
  start_date: string | null;
  end_date: string | null;
  created_at: string;
  updated_at: string;
}

export async function PUT(
  request: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const session = await auth();
  if (!session || session.user.userType !== "admin") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const { id } = await params;
  const announcementId = parseInt(id);

  if (isNaN(announcementId)) {
    return NextResponse.json({ error: "Invalid announcement ID" }, { status: 400 });
  }

  try {
    const body = await request.json();
    const { title, content, targetAudience, status, startDate, endDate } = body;

    const setClauses: string[] = [];
    const values: unknown[] = [];

    if (title !== undefined) { setClauses.push("title = ?"); values.push(title); }
    if (content !== undefined) { setClauses.push("content = ?"); values.push(content); }
    if (targetAudience !== undefined) { setClauses.push("target_audience = ?"); values.push(targetAudience); }
    if (status !== undefined) { setClauses.push("status = ?"); values.push(status); }
    if (startDate !== undefined) {
      setClauses.push("start_date = ?");
      values.push(startDate ? new Date(startDate).toISOString().slice(0, 19).replace("T", " ") : null);
    }
    if (endDate !== undefined) {
      setClauses.push("end_date = ?");
      values.push(endDate ? new Date(endDate).toISOString().slice(0, 19).replace("T", " ") : null);
    }

    setClauses.push("updated_at = NOW()");
    values.push(announcementId);

    await execute(
      `UPDATE announcements SET ${setClauses.join(", ")} WHERE id = ?`,
      values
    );

    const announcement = await queryOne<AnnouncementRow>(
      "SELECT * FROM announcements WHERE id = ?",
      [announcementId]
    );

    if (!announcement) {
      return NextResponse.json({ error: "Announcement not found" }, { status: 404 });
    }

    return NextResponse.json({
      success: true,
      data: {
        id: announcement.id,
        title: announcement.title,
        content: announcement.content,
        targetAudience: announcement.target_audience,
        status: announcement.status,
        startDate: announcement.start_date,
        endDate: announcement.end_date,
        createdAt: announcement.created_at,
        updatedAt: announcement.updated_at,
      },
    });
  } catch (error) {
    console.error("Admin Announcement PUT error:", error);
    return NextResponse.json({ error: "Internal server error" }, { status: 500 });
  }
}
