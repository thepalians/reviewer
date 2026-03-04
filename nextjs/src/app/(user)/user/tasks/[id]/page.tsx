import { auth } from "@/lib/auth";
import { redirect, notFound } from "next/navigation";
import { queryOne, query } from "@/lib/db";
import Link from "next/link";
import TaskDetailClient from "./TaskDetailClient";
import type { Task, TaskStep } from "@/types";
import type { Metadata } from "next";
import type { RowDataPacket } from "mysql2";

export const metadata: Metadata = { title: "Task Detail" };

interface TaskRow extends RowDataPacket {
  id: number;
  user_id: number;
  seller_id: number | null;
  order_id: string | null;
  product_name: string | null;
  product_link: string | null;
  platform: string | null;
  commission: string | null;
  status: string;
  deadline: Date | null;
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
  refund_amount: string | null;
  completed_at: Date | null;
  created_at: Date;
  updated_at: Date;
}

export default async function TaskDetailPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const session = await auth();
  if (!session || session.user.userType !== "user") redirect("/login");

  const { id } = await params;
  const taskId = parseInt(id);
  if (isNaN(taskId)) notFound();

  const userId = parseInt(session.user.id);

  const task = await queryOne<TaskRow>(
    "SELECT * FROM tasks WHERE id = ? AND user_id = ? LIMIT 1",
    [taskId, userId]
  );

  if (!task) notFound();

  const stepRows = await query<TaskStepRow>(
    "SELECT * FROM task_steps WHERE task_id = ? ORDER BY step_number ASC",
    [taskId]
  );

  const serialized: Task = {
    id: task.id,
    userId: task.user_id,
    orderId: task.order_id ?? undefined,
    productName: task.product_name ?? undefined,
    productLink: task.product_link ?? undefined,
    platform: task.platform ?? undefined,
    status: task.status as Task["status"],
    commission: task.commission ? Number(task.commission) : undefined,
    deadline: task.deadline ? new Date(task.deadline).toISOString() : undefined,
    refundRequested: false,
    createdAt: new Date(task.created_at).toISOString(),
    updatedAt: new Date(task.updated_at).toISOString(),
    steps: stepRows.map((s): TaskStep => ({
      id: s.id,
      taskId: s.task_id,
      stepNumber: s.step_number,
      stepStatus: s.step_status as TaskStep["stepStatus"],
      submittedByUser: false,
      orderScreenshot: s.order_screenshot ?? undefined,
      deliveryScreenshot: s.delivery_screenshot ?? undefined,
      reviewScreenshot: s.review_screenshot ?? undefined,
      refundAmount: s.refund_amount ? Number(s.refund_amount) : undefined,
      completedAt: s.completed_at ? new Date(s.completed_at).toISOString() : undefined,
      createdAt: new Date(s.created_at).toISOString(),
      updatedAt: new Date(s.updated_at).toISOString(),
    })),
  };

  return (
    <div className="space-y-4">
      {/* Back link */}
      <Link
        href="/user/tasks"
        className="inline-flex items-center gap-1 text-sm text-[#667eea] hover:underline"
      >
        ← Back to Tasks
      </Link>

      {/* Page header */}
      <div className="bg-gradient-to-r from-[#667eea] to-[#764ba2] rounded-2xl p-6 text-white">
        <h1 className="text-xl font-bold">
          {task.product_name ?? `Task #${task.id}`}
        </h1>
        <p className="text-white/80 text-sm mt-1">Complete all 4 steps to earn your commission</p>
      </div>

      <TaskDetailClient task={serialized} />
    </div>
  );
}
