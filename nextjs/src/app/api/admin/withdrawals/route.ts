import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { prisma } from "@/lib/db";

export async function GET(request: NextRequest) {
  const session = await auth();
  if (!session || session.user.userType !== "admin") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const { searchParams } = new URL(request.url);
  const status = searchParams.get("status") || "";
  const page = parseInt(searchParams.get("page") || "1");
  const limit = parseInt(searchParams.get("limit") || "20");
  const skip = (page - 1) * limit;

  try {
    const typeFilter =
      status === "pending" ? "withdrawal_pending" :
      status === "approved" ? "withdrawal_approved" :
      status === "rejected" ? "withdrawal_rejected" : null;

    const where: Record<string, unknown> = typeFilter
      ? { type: typeFilter }
      : { type: { in: ["withdrawal_pending", "withdrawal_approved", "withdrawal_rejected"] } };

    const [withdrawals, total] = await Promise.all([
      prisma.walletTransaction.findMany({
        where,
        select: {
          id: true,
          amount: true,
          type: true,
          description: true,
          createdAt: true,
          user: { select: { id: true, name: true, email: true, upiId: true, accountNumber: true, bankName: true } },
        },
        orderBy: { createdAt: "desc" },
        skip,
        take: limit,
      }),
      prisma.walletTransaction.count({ where }),
    ]);

    return NextResponse.json({ success: true, data: withdrawals, total, page, limit });
  } catch (error) {
    console.error("Admin withdrawals API error:", error);
    return NextResponse.json({ error: "Failed to fetch withdrawals" }, { status: 500 });
  }
}
