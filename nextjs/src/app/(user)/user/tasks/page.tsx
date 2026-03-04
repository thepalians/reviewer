import { auth } from "@/lib/auth";
import { redirect } from "next/navigation";
import { query } from "@/lib/db";
import TaskList from "@/components/TaskList";
import type { Task, TaskStep } from "@/types";
import type { Metadata } from "next";
import type { RowDataPacket } from "mysql2";

export const metadata: Metadata = { title: "My Tasks" };

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

interface CountRow extends RowDataPacket {
  cnt: number;
}

export default async function TasksPage() {
  const session = await auth();
  if (!session || session.user.userType !== "user") redirect("/login");

  const userId = parseInt(session.user.id);

  const [tasks, countRows] = await Promise.all([
    query<TaskRow>(
      "SELECT * FROM tasks WHERE user_id = ? ORDER BY created_at DESC LIMIT 10",
      [userId]
    ),
    query<CountRow>(
      "SELECT COUNT(*) AS cnt FROM tasks WHERE user_id = ?",
      [userId]
    ),
  ]);

  const total = Number(countRows[0]?.cnt ?? 0);

  const taskIds = tasks.map((t) => t.id);

  let stepRows: TaskStepRow[] = [];
  if (taskIds.length > 0) {
    const placeholders = taskIds.map(() => "?").join(",");
    stepRows = await query<TaskStepRow>(
      `SELECT * FROM task_steps WHERE task_id IN (${placeholders}) ORDER BY step_number ASC`,
      taskIds
    );
  }

  const stepsByTaskId = new Map<number, TaskStepRow[]>();
  for (const step of stepRows) {
    const existing = stepsByTaskId.get(step.task_id) ?? [];
    existing.push(step);
    stepsByTaskId.set(step.task_id, existing);
  }

  const serialized: Task[] = tasks.map((t) => {
    const steps = stepsByTaskId.get(t.id) ?? [];
    return {
      id: t.id,
      userId: t.user_id,
      orderId: t.order_id ?? undefined,
      productName: t.product_name ?? undefined,
      productLink: t.product_link ?? undefined,
      platform: t.platform ?? undefined,
      status: t.status as Task["status"],
      commission: t.commission ? Number(t.commission) : undefined,
      deadline: t.deadline ? new Date(t.deadline).toISOString() : undefined,
      refundRequested: false,
      createdAt: new Date(t.created_at).toISOString(),
      updatedAt: new Date(t.updated_at).toISOString(),
      steps: steps.map((s): TaskStep => ({
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
  });

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="bg-gradient-to-r from-[#667eea] to-[#764ba2] rounded-2xl p-6 text-white">
        <h1 className="text-2xl font-bold">📋 My Tasks</h1>
        <p className="text-white/80 mt-1">
          {total} task{total !== 1 ? "s" : ""} assigned to you
        </p>
      </div>

      <TaskList initialTasks={serialized} initialTotal={total} />
    </div>
  );
}
