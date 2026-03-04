import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { queryOne, execute } from "@/lib/db";
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
      "UPDATE task_steps SET step_status = 'rejected', updated_at = NOW() WHERE id = ?",
      [step.id]
    );

    await execute(
      "UPDATE tasks SET status = 'rejected', updated_at = NOW() WHERE id = ?",
      [taskId]
    );

    // Re-fetch updated step
    const updatedStep = await queryOne<StepRow>(
      "SELECT id, task_id, step_number, step_status, completed_at FROM task_steps WHERE id = ?",
      [step.id]
    );

    return NextResponse.json({ success: true, data: updatedStep });
  } catch (error) {
    console.error("Admin reject step API error:", error);
    return NextResponse.json({ error: "Failed to reject step" }, { status: 500 });
  }
}
