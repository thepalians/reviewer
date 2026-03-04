import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { query } from "@/lib/db";
import type { RowDataPacket } from "mysql2";

const ALLOWED_STATUSES = ["assigned", "in_progress", "pending", "completed", "rejected", "cancelled"];

interface TaskRow extends RowDataPacket {
  id: number;
  user_id: number;
  seller_id: number | null;
  order_id: string | null;
  product_name: string;
  product_link: string | null;
  platform: string;
  instructions: string | null;
  commission: number | null;
  status: string;
  deadline: Date | null;
  refund_requested: boolean;
  refund_date: Date | null;
  review_text: string | null;
  review_rating: number | null;
  brand: string | null;
  created_at: Date;
  updated_at: Date;
}

interface TaskStepRow extends RowDataPacket {
  id: number;
  task_id: number;
  step_number: number;
  step_status: string;
  order_screenshot: string | null;
  delivery_screenshot: string | null;
  review_screenshot: string | null;
  review_submitted_screenshot: string | null;
  review_live_screenshot: string | null;
  refund_amount: number | null;
  admin_payment_screenshot: string | null;
  refund_processed_at: Date | null;
  completed_at: Date | null;
  created_at: Date;
  updated_at: Date;
}

interface CountRow extends RowDataPacket {
  count: number;
}

export async function GET(req: NextRequest) {
  const session = await auth();

  if (!session || session.user.userType !== "user") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const userId = parseInt(session.user.id);
  const { searchParams } = new URL(req.url);

  const status = searchParams.get("status") ?? undefined;
  const page = Math.max(1, parseInt(searchParams.get("page") ?? "1"));
  const limit = Math.min(50, Math.max(1, parseInt(searchParams.get("limit") ?? "10")));
  const offset = (page - 1) * limit;

  const statusFilter = status && ALLOWED_STATUSES.includes(status) ? status : null;

  try {
    const tasksSql = statusFilter
      ? `SELECT * FROM tasks WHERE user_id = ? AND status = ? ORDER BY created_at DESC LIMIT ? OFFSET ?`
      : `SELECT * FROM tasks WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?`;

    const tasksParams = statusFilter
      ? [userId, statusFilter, limit, offset]
      : [userId, limit, offset];

    const countSql = statusFilter
      ? `SELECT COUNT(*) AS count FROM tasks WHERE user_id = ? AND status = ?`
      : `SELECT COUNT(*) AS count FROM tasks WHERE user_id = ?`;

    const countParams = statusFilter ? [userId, statusFilter] : [userId];

    const [tasks, countRows] = await Promise.all([
      query<TaskRow>(tasksSql, tasksParams),
      query<CountRow>(countSql, countParams),
    ]);

    const total = Number(countRows[0]?.count ?? 0);

    // Fetch steps for each task
    const taskIds = tasks.map((t) => t.id);
    let stepsMap = new Map<number, TaskStepRow[]>();

    if (taskIds.length > 0) {
      const placeholders = taskIds.map(() => "?").join(",");
      const steps = await query<TaskStepRow>(
        `SELECT * FROM task_steps WHERE task_id IN (${placeholders}) ORDER BY step_number ASC`,
        taskIds
      );
      for (const step of steps) {
        if (!stepsMap.has(step.task_id)) stepsMap.set(step.task_id, []);
        stepsMap.get(step.task_id)!.push(step);
      }
    }

    return NextResponse.json({
      success: true,
      data: {
        items: tasks.map((t) => ({
          id: t.id,
          userId: t.user_id,
          sellerId: t.seller_id,
          orderId: t.order_id,
          productName: t.product_name,
          productLink: t.product_link,
          platform: t.platform,
          instructions: t.instructions,
          commission: t.commission != null ? Number(t.commission) : null,
          status: t.status,
          deadline: t.deadline
            ? (t.deadline instanceof Date ? t.deadline.toISOString() : String(t.deadline))
            : null,
          refundRequested: Boolean(t.refund_requested),
          refundDate: t.refund_date
            ? (t.refund_date instanceof Date ? t.refund_date.toISOString() : String(t.refund_date))
            : null,
          reviewText: t.review_text,
          reviewRating: t.review_rating,
          brand: t.brand,
          createdAt: t.created_at instanceof Date ? t.created_at.toISOString() : String(t.created_at),
          updatedAt: t.updated_at instanceof Date ? t.updated_at.toISOString() : String(t.updated_at),
          steps: (stepsMap.get(t.id) ?? []).map((s) => ({
            id: s.id,
            taskId: s.task_id,
            stepNumber: s.step_number,
            stepStatus: s.step_status,
            orderScreenshot: s.order_screenshot,
            deliveryScreenshot: s.delivery_screenshot,
            reviewScreenshot: s.review_screenshot,
            reviewSubmittedScreenshot: s.review_submitted_screenshot,
            reviewLiveScreenshot: s.review_live_screenshot,
            refundAmount: s.refund_amount != null ? Number(s.refund_amount) : null,
            adminPaymentScreenshot: s.admin_payment_screenshot,
            refundProcessedAt: s.refund_processed_at
              ? (s.refund_processed_at instanceof Date ? s.refund_processed_at.toISOString() : String(s.refund_processed_at))
              : null,
            completedAt: s.completed_at
              ? (s.completed_at instanceof Date ? s.completed_at.toISOString() : String(s.completed_at))
              : null,
            createdAt: s.created_at instanceof Date ? s.created_at.toISOString() : String(s.created_at),
            updatedAt: s.updated_at instanceof Date ? s.updated_at.toISOString() : String(s.updated_at),
          })),
        })),
        total,
        page,
        limit,
        totalPages: Math.ceil(total / limit),
      },
    });
  } catch (error) {
    console.error("Tasks API error:", error);
    return NextResponse.json(
      { error: "Failed to fetch tasks" },
      { status: 500 }
    );
  }
}
