import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { prisma } from "@/lib/db";

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
    const task = await prisma.task.findFirst({
      where: { id: taskId, userId },
      include: {
        steps: { orderBy: { stepNumber: "asc" } },
      },
    });

    if (!task) {
      return NextResponse.json({ error: "Task not found" }, { status: 404 });
    }

    return NextResponse.json({
      success: true,
      data: {
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
