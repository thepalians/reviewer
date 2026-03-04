import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { prisma } from "@/lib/db";

export async function GET(request: NextRequest) {
  const session = await auth();
  if (!session || session.user.userType !== "admin") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  try {
    const { searchParams } = new URL(request.url);
    const statusFilter = searchParams.get("status") || "";
    const page = parseInt(searchParams.get("page") || "1");
    const limit = parseInt(searchParams.get("limit") || "20");
    const skip = (page - 1) * limit;

    const where = statusFilter ? { status: statusFilter } : {};

    const [documents, total] = await Promise.all([
      prisma.kycDocument.findMany({
        where,
        include: {
          user: { select: { id: true, name: true, email: true } },
        },
        orderBy: { createdAt: "desc" },
        skip,
        take: limit,
      }),
      prisma.kycDocument.count({ where }),
    ]);

    return NextResponse.json({
      success: true,
      data: documents.map((d) => ({
        ...d,
        createdAt: d.createdAt.toISOString(),
        updatedAt: d.updatedAt.toISOString(),
        reviewedAt: d.reviewedAt ? d.reviewedAt.toISOString() : null,
      })),
      total,
      page,
    });
  } catch (error) {
    console.error("Admin KYC GET error:", error);
    return NextResponse.json({ error: "Internal server error" }, { status: 500 });
  }
}
