import { auth } from "@/lib/auth";
import { redirect, notFound } from "next/navigation";
import { prisma } from "@/lib/db";
import Link from "next/link";
import TaskDetailClient from "./TaskDetailClient";
import type { Task } from "@/types";
import type { Metadata } from "next";

export const metadata: Metadata = { title: "Task Detail" };

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

  const task = await prisma.task.findFirst({
    where: { id: taskId, userId },
    include: { steps: { orderBy: { stepNumber: "asc" } } },
  });

  if (!task) notFound();

  const serialized = {
    ...task,
    commission: task.commission ? Number(task.commission) : null,
    createdAt: task.createdAt.toISOString(),
    updatedAt: task.updatedAt.toISOString(),
    deadline: task.deadline?.toISOString() ?? null,
    refundDate: task.refundDate?.toISOString() ?? null,
    steps: task.steps.map((s) => ({
      ...s,
      refundAmount: s.refundAmount ? Number(s.refundAmount) : null,
      createdAt: s.createdAt.toISOString(),
      updatedAt: s.updatedAt.toISOString(),
      refundProcessedAt: s.refundProcessedAt?.toISOString() ?? null,
      completedAt: s.completedAt?.toISOString() ?? null,
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
          {task.productName ?? `Task #${task.id}`}
        </h1>
        <p className="text-white/80 text-sm mt-1">Complete all 4 steps to earn your commission</p>
      </div>

      <TaskDetailClient task={serialized as unknown as Task} />
    </div>
  );
}
