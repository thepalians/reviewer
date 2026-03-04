import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { query, execute } from "@/lib/db";
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

export async function GET(_req: NextRequest) {
  const session = await auth();
  if (!session || session.user.userType !== "admin") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  try {
    const announcements = await query<AnnouncementRow>(
      "SELECT * FROM announcements ORDER BY created_at DESC"
    );

    return NextResponse.json({
      success: true,
      data: announcements.map((a) => ({
        id: a.id,
        title: a.title,
        content: a.content,
        targetAudience: a.target_audience,
        status: a.status,
        startDate: a.start_date,
        endDate: a.end_date,
        createdAt: a.created_at,
        updatedAt: a.updated_at,
      })),
    });
  } catch (error) {
    console.error("Admin Announcements GET error:", error);
    return NextResponse.json({ error: "Internal server error" }, { status: 500 });
  }
}

export async function POST(request: NextRequest) {
  const session = await auth();
  if (!session || session.user.userType !== "admin") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  try {
    const body = await request.json();
    const { title, content, targetAudience, status, startDate, endDate } = body;

    if (!title || !content) {
      return NextResponse.json({ error: "title and content are required" }, { status: 400 });
    }

    const audience = targetAudience ?? "all";
    const announcementStatus = status ?? "active";
    const parsedStartDate = startDate ? new Date(startDate).toISOString().slice(0, 19).replace("T", " ") : null;
    const parsedEndDate = endDate ? new Date(endDate).toISOString().slice(0, 19).replace("T", " ") : null;

    const result = await execute(
      `INSERT INTO announcements (title, content, target_audience, status, start_date, end_date, created_at, updated_at)
       VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())`,
      [title, content, audience, announcementStatus, parsedStartDate, parsedEndDate]
    );

    const rows = await query<AnnouncementRow>(
      "SELECT * FROM announcements WHERE id = ?",
      [result.insertId]
    );

    const a = rows[0];

    return NextResponse.json({
      success: true,
      data: {
        id: a.id,
        title: a.title,
        content: a.content,
        targetAudience: a.target_audience,
        status: a.status,
        startDate: a.start_date,
        endDate: a.end_date,
        createdAt: a.created_at,
        updatedAt: a.updated_at,
      },
    });
  } catch (error) {
    console.error("Admin Announcements POST error:", error);
    return NextResponse.json({ error: "Internal server error" }, { status: 500 });
  }
}
