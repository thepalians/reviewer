import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { prisma } from "@/lib/db";

export async function GET(_req: NextRequest) {
  const session = await auth();
  if (!session || session.user.userType !== "user") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const userId = parseInt(session.user.id);

  try {
    const documents = await prisma.kycDocument.findMany({
      where: { userId },
      orderBy: { createdAt: "desc" },
    });

    return NextResponse.json({
      success: true,
      data: documents.map((d) => ({
        ...d,
        createdAt: d.createdAt.toISOString(),
        updatedAt: d.updatedAt.toISOString(),
        reviewedAt: d.reviewedAt ? d.reviewedAt.toISOString() : null,
      })),
    });
  } catch (error) {
    console.error("KYC GET error:", error);
    return NextResponse.json({ error: "Internal server error" }, { status: 500 });
  }
}

export async function POST(request: NextRequest) {
  const session = await auth();
  if (!session || session.user.userType !== "user") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const userId = parseInt(session.user.id);

  try {
    const body = await request.json();
    const { documentType, documentPath } = body;

    if (!documentType || !documentPath) {
      return NextResponse.json(
        { error: "documentType and documentPath are required" },
        { status: 400 }
      );
    }

    // Check if a document of this type already exists
    const existing = await prisma.kycDocument.findFirst({
      where: { userId, documentType },
    });

    let document;
    if (existing) {
      // Re-upload: update existing record, reset to pending
      document = await prisma.kycDocument.update({
        where: { id: existing.id },
        data: { documentPath, status: "pending", notes: null, reviewedAt: null, reviewedBy: null },
      });
    } else {
      document = await prisma.kycDocument.create({
        data: { userId, documentType, documentPath },
      });
    }

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
    console.error("KYC POST error:", error);
    return NextResponse.json({ error: "Internal server error" }, { status: 500 });
  }
}
