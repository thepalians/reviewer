import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { prisma } from "@/lib/db";

export async function PUT(
  request: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const session = await auth();
  if (!session || session.user.userType !== "admin") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const { id } = await params;
  const taskId = parseInt(id);
  if (isNaN(taskId)) {
    return NextResponse.json({ error: "Invalid task ID" }, { status: 400 });
  }

  try {
    const body = await request.json();
    const { stepNumber } = body;

    const step = await prisma.taskStep.findFirst({
      where: { taskId, stepNumber },
    });

    if (!step) {
      return NextResponse.json({ error: "Step not found" }, { status: 404 });
    }

    const updatedStep = await prisma.taskStep.update({
      where: { id: step.id },
      data: { stepStatus: "approved", completedAt: new Date() },
    });

    // Update task status if all steps approved
    const allSteps = await prisma.taskStep.findMany({ where: { taskId } });
    const allApproved = allSteps.every((s) => s.stepStatus === "approved");
    if (allApproved) {
      await prisma.task.update({ where: { id: taskId }, data: { status: "completed" } });
    }

    return NextResponse.json({ success: true, data: updatedStep });
  } catch (error) {
    console.error("Admin approve step API error:", error);
    return NextResponse.json({ error: "Failed to approve step" }, { status: 500 });
  }
}
