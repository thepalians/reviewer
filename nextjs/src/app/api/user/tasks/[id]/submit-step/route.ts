import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { prisma } from "@/lib/db";
import { writeFile, mkdir } from "fs/promises";
import path from "path";

const STEP_FIELDS: Record<
  number,
  { fileField: string; dbField: string; textFields?: string[] }
> = {
  1: {
    fileField: "orderScreenshot",
    dbField: "orderScreenshot",
    textFields: ["orderId"],
  },
  2: { fileField: "deliveryScreenshot", dbField: "deliveryScreenshot" },
  3: {
    fileField: "reviewScreenshot",
    dbField: "reviewScreenshot",
    textFields: ["reviewText", "reviewRating", "reviewLiveScreenshot"],
  },
  4: {
    fileField: "paymentQrCode",
    dbField: "paymentQrCode",
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

  const task = await prisma.task.findFirst({
    where: { id: taskId, userId },
    include: { steps: { orderBy: { stepNumber: "asc" } } },
  });

  if (!task) {
    return NextResponse.json({ error: "Task not found" }, { status: 404 });
  }

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
  const existingStep = task.steps.find((s) => s.stepNumber === stepNumber);

  // Check step is unlocked: step N requires step N-1 to be approved
  if (stepNumber > 1) {
    const prevStep = task.steps.find((s) => s.stepNumber === stepNumber - 1);
    if (!prevStep || prevStep.stepStatus !== "approved") {
      return NextResponse.json(
        { error: "Previous step must be approved before submitting this step" },
        { status: 400 }
      );
    }
  }

  if (existingStep && existingStep.stepStatus === "approved") {
    return NextResponse.json(
      { error: "This step has already been approved" },
      { status: 400 }
    );
  }

  try {
    const updateData: Record<string, unknown> = {
      stepStatus: "submitted",
      submittedByUser: true,
    };

    // Handle file upload
    const uploadedFile = formData.get(stepConfig.fileField) as File | null;
    if (uploadedFile && uploadedFile.size > 0) {
      const result = await saveUploadedFile(uploadedFile, `task-${taskId}`);
      if ("error" in result) {
        return NextResponse.json({ error: result.error }, { status: 400 });
      }
      updateData[stepConfig.dbField] = result.path;
    } else if (!existingStep) {
      return NextResponse.json(
        { error: "Screenshot is required for this step" },
        { status: 400 }
      );
    }

    // Handle text fields
    if (stepNumber === 1) {
      const orderId = formData.get("orderId") as string | null;
      if (orderId) {
        await prisma.task.update({
          where: { id: taskId },
          data: { orderId, status: "in_progress" },
        });
      }
    }

    if (stepNumber === 3) {
      const reviewText = formData.get("reviewText") as string | null;
      const reviewRating = formData.get("reviewRating") as string | null;
      const reviewLiveFile = formData.get("reviewLiveScreenshot") as File | null;

      if (reviewText) updateData.reviewText = reviewText;
      if (reviewRating) updateData.reviewRating = parseInt(reviewRating) || null;
      if (reviewLiveFile && reviewLiveFile.size > 0) {
        const result = await saveUploadedFile(reviewLiveFile, `task-${taskId}`);
        if ("error" in result) {
          return NextResponse.json({ error: result.error }, { status: 400 });
        }
        updateData.reviewLiveScreenshot = result.path;
      }
    }

    if (stepNumber === 4) {
      const feedbackText = formData.get("feedback") as string | null;
      const rating = formData.get("rating") as string | null;

      if (feedbackText) updateData.reviewText = feedbackText;
      if (rating) updateData.reviewRating = parseInt(rating) || null;
    }

    if (existingStep) {
      await prisma.taskStep.update({
        where: { id: existingStep.id },
        data: updateData,
      });
    } else {
      await prisma.taskStep.create({
        data: {
          taskId,
          stepNumber,
          stepName: getStepName(stepNumber),
          ...updateData,
        },
      });
    }

    // Update task status to reflect the submitted step
    const statusMap: Record<number, string> = {
      1: "step1_pending",
      2: "step2_pending",
      3: "step3_pending",
      4: "step4_pending",
    };
    await prisma.task.update({
      where: { id: taskId },
      data: { status: statusMap[stepNumber] },
    });

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

function getStepName(stepNumber: number): string {
  const names: Record<number, string> = {
    1: "Order & Screenshot",
    2: "Delivery Confirmation",
    3: "Review Submission",
    4: "Refund & Feedback",
  };
  return names[stepNumber] ?? `Step ${stepNumber}`;
}
