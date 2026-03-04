import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { queryOne, query, execute } from "@/lib/db";
import type { RowDataPacket } from "mysql2";
import { writeFile, mkdir } from "fs/promises";
import path from "path";

interface TaskRow extends RowDataPacket {
  id: number;
  user_id: number;
  status: string;
}

interface TaskStepRow extends RowDataPacket {
  id: number;
  task_id: number;
  step_number: number;
  step_status: string;
}

const STEP_FIELDS: Record<
  number,
  { fileField: string; dbField: string; textFields?: string[] }
> = {
  1: {
    fileField: "orderScreenshot",
    dbField: "order_screenshot",
    textFields: ["orderId"],
  },
  2: { fileField: "deliveryScreenshot", dbField: "delivery_screenshot" },
  3: {
    fileField: "reviewScreenshot",
    dbField: "review_screenshot",
    textFields: ["reviewText", "reviewRating", "reviewLiveScreenshot"],
  },
  4: {
    fileField: "paymentQrCode",
    dbField: "order_screenshot",
    textFields: ["feedback", "rating"],
  },
};

const ALLOWED_MIME_TYPES = ["image/jpeg", "image/png", "image/gif", "image/webp"];
const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5 MB

async function saveUploadedFile(
  file: File,
  subDir: string
): Promise<{ path: string } | { error: string }> {
  if (!ALLOWED_MIME_TYPES.includes(file.type)) {
    return { error: `Invalid file type: ${file.type}. Only JPEG, PNG, GIF, and WebP are allowed.` };
  }
  if (file.size > MAX_FILE_SIZE) {
    return { error: "File size exceeds the 5 MB limit." };
  }

  const uploadBase =
    process.env.UPLOAD_DIR ?? path.join(process.cwd(), "..", "uploads");
  const uploadDir = path.join(uploadBase, subDir);
  await mkdir(uploadDir, { recursive: true });

  const ext = path.extname(file.name) || ".jpg";
  const fileName = `${Date.now()}-${Math.random().toString(36).slice(2)}${ext}`;
  const filePath = path.join(uploadDir, fileName);

  const buffer = Buffer.from(await file.arrayBuffer());
  await writeFile(filePath, buffer);

  return { path: `uploads/${subDir}/${fileName}` };
}

export async function POST(
  req: NextRequest,
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

  const task = await queryOne<TaskRow>(
    "SELECT id, user_id, status FROM tasks WHERE id = ? AND user_id = ?",
    [taskId, userId]
  );

  if (!task) {
    return NextResponse.json({ error: "Task not found" }, { status: 404 });
  }

  const steps = await query<TaskStepRow>(
    "SELECT id, task_id, step_number, step_status FROM task_steps WHERE task_id = ? ORDER BY step_number ASC",
    [taskId]
  );

  let formData: FormData;
  try {
    formData = await req.formData();
  } catch {
    return NextResponse.json({ error: "Invalid form data" }, { status: 400 });
  }

  const stepNumber = parseInt(formData.get("stepNumber") as string);
  if (isNaN(stepNumber) || stepNumber < 1 || stepNumber > 4) {
    return NextResponse.json({ error: "Invalid step number" }, { status: 400 });
  }

  const stepConfig = STEP_FIELDS[stepNumber];
  const existingStep = steps.find((s) => s.step_number === stepNumber);

  // Check step is unlocked: step N requires step N-1 to be approved
  if (stepNumber > 1) {
    const prevStep = steps.find((s) => s.step_number === stepNumber - 1);
    if (!prevStep || prevStep.step_status !== "approved") {
      return NextResponse.json(
        { error: "Previous step must be approved before submitting this step" },
        { status: 400 }
      );
    }
  }

  if (existingStep && existingStep.step_status === "approved") {
    return NextResponse.json(
      { error: "This step has already been approved" },
      { status: 400 }
    );
  }

  try {
    const updateFields: Record<string, unknown> = {
      step_status: "submitted",
    };

    // Handle file upload
    const uploadedFile = formData.get(stepConfig.fileField) as File | null;
    if (uploadedFile && uploadedFile.size > 0) {
      const result = await saveUploadedFile(uploadedFile, `task-${taskId}`);
      if ("error" in result) {
        return NextResponse.json({ error: result.error }, { status: 400 });
      }
      updateFields[stepConfig.dbField] = result.path;
    } else if (!existingStep) {
      return NextResponse.json(
        { error: "Screenshot is required for this step" },
        { status: 400 }
      );
    }

    // Handle text fields for step 1 (orderId on task)
    if (stepNumber === 1) {
      const orderId = formData.get("orderId") as string | null;
      if (orderId) {
        await execute(
          "UPDATE tasks SET order_id = ?, status = 'in_progress', updated_at = NOW() WHERE id = ?",
          [orderId, taskId]
        );
      }
    }

    // Handle step 3 review fields
    if (stepNumber === 3) {
      const reviewText = formData.get("reviewText") as string | null;
      const reviewRating = formData.get("reviewRating") as string | null;
      const reviewLiveFile = formData.get("reviewLiveScreenshot") as File | null;

      if (reviewText) updateFields.review_text = reviewText;
      if (reviewRating) updateFields.review_rating = parseInt(reviewRating) || null;
      if (reviewLiveFile && reviewLiveFile.size > 0) {
        const result = await saveUploadedFile(reviewLiveFile, `task-${taskId}`);
        if ("error" in result) {
          return NextResponse.json({ error: result.error }, { status: 400 });
        }
        updateFields.review_live_screenshot = result.path;
      }
    }

    // Handle step 4 feedback/rating fields
    if (stepNumber === 4) {
      const feedbackText = formData.get("feedback") as string | null;
      const rating = formData.get("rating") as string | null;

      if (feedbackText) updateFields.review_text = feedbackText;
      if (rating) updateFields.review_rating = parseInt(rating) || null;
    }

    if (existingStep) {
      // Build dynamic UPDATE
      const setClauses = Object.keys(updateFields)
        .map((k) => `${k} = ?`)
        .join(", ");
      const values = [...Object.values(updateFields), existingStep.id];
      await execute(
        `UPDATE task_steps SET ${setClauses}, updated_at = NOW() WHERE id = ?`,
        values
      );
    } else {
      // INSERT new step
      const columns = ["task_id", "step_number", ...Object.keys(updateFields)];
      const placeholders = columns.map(() => "?").join(", ");
      const values = [taskId, stepNumber, ...Object.values(updateFields)];
      await execute(
        `INSERT INTO task_steps (${columns.join(", ")}, created_at, updated_at) VALUES (${placeholders}, NOW(), NOW())`,
        values
      );
    }

    // Update task status to reflect the submitted step
    const statusMap: Record<number, string> = {
      1: "step1_pending",
      2: "step2_pending",
      3: "step3_pending",
      4: "step4_pending",
    };
    await execute(
      "UPDATE tasks SET status = ?, updated_at = NOW() WHERE id = ?",
      [statusMap[stepNumber], taskId]
    );

    return NextResponse.json({
      success: true,
      message: `Step ${stepNumber} submitted successfully`,
    });
  } catch (error) {
    console.error("Submit step error:", error);
    return NextResponse.json(
      { error: "Failed to submit step" },
      { status: 500 }
    );
  }
}
