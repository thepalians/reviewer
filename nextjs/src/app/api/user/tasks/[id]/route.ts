import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { queryOne, query } from "@/lib/db";
import type { RowDataPacket } from "mysql2";

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

export async function GET(
  _req: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const session = await auth();

  if (!session || session.user.userType !== "user") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const { id } = await params;
  const taskId = parseInt(id);
  if (isNaN(taskId)) {
    return NextResponse.json({ error: "Invalid task ID" }, { status: 400 });
  }

  const userId = parseInt(session.user.id);

  try {
    const task = await queryOne<TaskRow>(
      "SELECT * FROM tasks WHERE id = ? AND user_id = ?",
      [taskId, userId]
    );

    if (!task) {
      return NextResponse.json({ error: "Task not found" }, { status: 404 });
    }

    const steps = await query<TaskStepRow>(
      "SELECT * FROM task_steps WHERE task_id = ? ORDER BY step_number ASC",
      [taskId]
    );

    return NextResponse.json({
      success: true,
      data: {
        id: task.id,
        userId: task.user_id,
        sellerId: task.seller_id,
        orderId: task.order_id,
        productName: task.product_name,
        productLink: task.product_link,
        platform: task.platform,
        instructions: task.instructions,
        commission: task.commission != null ? Number(task.commission) : null,
        status: task.status,
        deadline: task.deadline
          ? (task.deadline instanceof Date ? task.deadline.toISOString() : String(task.deadline))
          : null,
        refundRequested: Boolean(task.refund_requested),
        refundDate: task.refund_date
          ? (task.refund_date instanceof Date ? task.refund_date.toISOString() : String(task.refund_date))
          : null,
        reviewText: task.review_text,
        reviewRating: task.review_rating,
        brand: task.brand,
        createdAt: task.created_at instanceof Date ? task.created_at.toISOString() : String(task.created_at),
        updatedAt: task.updated_at instanceof Date ? task.updated_at.toISOString() : String(task.updated_at),
        steps: steps.map((s) => ({
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
      },
    });
  } catch (error) {
    console.error("Task detail API error:", error);
    return NextResponse.json(
      { error: "Failed to fetch task" },
      { status: 500 }
    );
  }
}
