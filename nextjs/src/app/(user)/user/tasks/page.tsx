import { auth } from "@/lib/auth";
import { redirect } from "next/navigation";
import { prisma } from "@/lib/db";
import TaskList from "@/components/TaskList";
import type { Task } from "@/types";
import type { Metadata } from "next";

export const metadata: Metadata = { title: "My Tasks" };

export default async function TasksPage() {
  const session = await auth();
  if (!session || session.user.userType !== "user") redirect("/login");

  const userId = parseInt(session.user.id);

  const [tasks, total] = await Promise.all([
    prisma.task.findMany({
      where: { userId },
      orderBy: { createdAt: "desc" },
      take: 10,
      include: { steps: { orderBy: { stepNumber: "asc" } } },
    }),
    prisma.task.count({ where: { userId } }),
  ]);

  const serialized = tasks.map((t) => ({
    ...t,
    commission: t.commission ? Number(t.commission) : null,
    createdAt: t.createdAt.toISOString(),
    updatedAt: t.updatedAt.toISOString(),
    deadline: t.deadline?.toISOString() ?? null,
    refundDate: t.refundDate?.toISOString() ?? null,
    steps: t.steps.map((s) => ({
      ...s,
      refundAmount: s.refundAmount ? Number(s.refundAmount) : null,
      createdAt: s.createdAt.toISOString(),
      updatedAt: s.updatedAt.toISOString(),
      refundProcessedAt: s.refundProcessedAt?.toISOString() ?? null,
      completedAt: s.completedAt?.toISOString() ?? null,
    })),
  }));

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="bg-gradient-to-r from-[#667eea] to-[#764ba2] rounded-2xl p-6 text-white">
        <h1 className="text-2xl font-bold">📋 My Tasks</h1>
        <p className="text-white/80 mt-1">
          {total} task{total !== 1 ? "s" : ""} assigned to you
        </p>
      </div>

      <TaskList initialTasks={serialized as unknown as Task[]} initialTotal={total} />
    </div>
  );
}
