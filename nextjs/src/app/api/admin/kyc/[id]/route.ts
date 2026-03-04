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
  const docId = parseInt(id);

  if (isNaN(docId)) {
    return NextResponse.json({ error: "Invalid document ID" }, { status: 400 });
  }

  try {
    const body = await request.json();
    const { status, notes } = body;

    if (!["approved", "rejected"].includes(status)) {
      return NextResponse.json(
        { error: "Status must be approved or rejected" },
        { status: 400 }
      );
    }

    const document = await prisma.kycDocument.update({
      where: { id: docId },
      data: {
        status,
        notes: notes ?? null,
        reviewedBy: parseInt(session.user.id),
        reviewedAt: new Date(),
      },
    });

    return NextResponse.json({
      success: true,
      data: {
        ...document,
        createdAt: document.createdAt.toISOString(),
        updatedAt: document.updatedAt.toISOString(),
        reviewedAt: document.reviewedAt ? document.reviewedAt.toISOString() : null,
      },
    });
  } catch (error) {
    console.error("Admin KYC PUT error:", error);
    return NextResponse.json({ error: "Internal server error" }, { status: 500 });
  }
}
