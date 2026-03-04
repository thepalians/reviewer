import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { query } from "@/lib/db";
import type { RowDataPacket } from "mysql2";

interface TaskRow extends RowDataPacket {
  id: number;
  product_name: string;
  platform: string;
  status: string;
  commission: number;
  deadline: string | null;
  created_at: string;
  user_id: number;
  user_name: string;
  user_email: string;
}

interface StepRow extends RowDataPacket {
  id: number;
  task_id: number;
  step_number: number;
  step_status: string;
}

interface CountRow extends RowDataPacket {
  count: number;
}

export async function GET(request: NextRequest) {
  const session = await auth();
  if (!session || session.user.userType !== "admin") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const { searchParams } = new URL(request.url);
  const status = searchParams.get("status") || "";
  const page = parseInt(searchParams.get("page") || "1");
  const limit = parseInt(searchParams.get("limit") || "20");
  const offset = (page - 1) * limit;

  try {
    const conditions: string[] = [];
    const params: unknown[] = [];
    const countParams: unknown[] = [];

    if (status) {
      conditions.push("t.status = ?");
      params.push(status);
      countParams.push(status);
    }

    const whereClause = conditions.length > 0 ? `WHERE ${conditions.join(" AND ")}` : "";

    const tasksSql = `
      SELECT
        t.id,
        t.product_name,
        t.platform,
        t.status,
        t.commission,
        t.deadline,
        t.created_at,
        t.user_id,
        u.name AS user_name,
        u.email AS user_email
      FROM tasks t
      LEFT JOIN users u ON u.id = t.user_id
      ${whereClause}
      ORDER BY t.created_at DESC
      LIMIT ? OFFSET ?
    `;

    const countSql = `
      SELECT COUNT(*) AS count
      FROM tasks t
      ${whereClause}
    `;

    const [tasks, countRows] = await Promise.all([
      query<TaskRow>(tasksSql, [...params, limit, offset]),
      query<CountRow>(countSql, countParams),
    ]);

    const total = countRows[0]?.count ?? 0;

    if (tasks.length === 0) {
      return NextResponse.json({ success: true, data: [], total, page, limit });
    }

    const taskIds = tasks.map((t) => t.id);
    const placeholders = taskIds.map(() => "?").join(", ");

    const steps = await query<StepRow>(
      `SELECT id, task_id, step_number, step_status FROM task_steps WHERE task_id IN (${placeholders})`,
      taskIds
    );

    const stepsByTask = steps.reduce<Record<number, StepRow[]>>((acc, step) => {
      if (!acc[step.task_id]) acc[step.task_id] = [];
      acc[step.task_id].push(step);
      return acc;
    }, {});

    const data = tasks.map((t) => ({
      id: t.id,
      productName: t.product_name,
      platform: t.platform,
      status: t.status,
      commission: t.commission,
      deadline: t.deadline,
      createdAt: t.created_at,
      user: { id: t.user_id, name: t.user_name, email: t.user_email },
      steps: (stepsByTask[t.id] || []).map((s) => ({
        id: s.id,
        stepNumber: s.step_number,
        stepStatus: s.step_status,
      })),
    }));

    return NextResponse.json({ success: true, data, total, page, limit });
  } catch (error) {
    console.error("Admin tasks API error:", error);
    return NextResponse.json({ error: "Failed to fetch tasks" }, { status: 500 });
  }
}
