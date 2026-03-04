import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { queryOne, query, execute } from "@/lib/db";
import type { RowDataPacket } from "mysql2";

interface StepRow extends RowDataPacket {
  id: number;
  task_id: number;
  step_number: number;
  step_status: string;
  completed_at: string | null;
}

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

    const step = await queryOne<StepRow>(
      "SELECT id, task_id, step_number, step_status, completed_at FROM task_steps WHERE task_id = ? AND step_number = ? LIMIT 1",
      [taskId, stepNumber]
    );

    if (!step) {
      return NextResponse.json({ error: "Step not found" }, { status: 404 });
    }

    await execute(
      "UPDATE task_steps SET step_status = 'approved', completed_at = NOW(), updated_at = NOW() WHERE id = ?",
      [step.id]
    );

    // Re-fetch updated step
    const updatedStep = await queryOne<StepRow>(
      "SELECT id, task_id, step_number, step_status, completed_at FROM task_steps WHERE id = ?",
      [step.id]
    );

    // Check if all steps are approved; if so, mark task as completed
    const allSteps = await query<StepRow>(
      "SELECT step_status FROM task_steps WHERE task_id = ?",
      [taskId]
    );

    const allApproved = allSteps.length > 0 && allSteps.every((s) => s.step_status === "approved");
    if (allApproved) {
      await execute(
        "UPDATE tasks SET status = 'completed', updated_at = NOW() WHERE id = ?",
        [taskId]
      );
    }

    return NextResponse.json({ success: true, data: updatedStep });
  } catch (error) {
    console.error("Admin approve step API error:", error);
    return NextResponse.json({ error: "Failed to approve step" }, { status: 500 });
  }
}
