import { NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { prisma } from "@/lib/db";

export async function GET() {
  const session = await auth();

  if (!session || session.user.userType !== "admin") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  try {
    const [
      totalUsers,
      totalSellers,
      totalTasks,
      pendingTasks,
      completedTasks,
      pendingWithdrawals,
      pendingKyc,
    ] = await Promise.all([
      prisma.user.count({ where: { userType: "user" } }),
      prisma.seller.count(),
      prisma.task.count(),
      prisma.task.count({
        where: { status: { in: ["pending", "assigned", "in_progress"] } },
      }),
      prisma.task.count({ where: { status: "completed" } }),
      prisma.walletTransaction.count({
        where: { type: "withdrawal_pending" },
      }),
      prisma.kycDocument.count({ where: { status: "pending" } }),
    ]);

    return NextResponse.json({
      success: true,
      data: {
        totalUsers,
        totalSellers,
        totalTasks,
        pendingTasks,
        completedTasks,
        pendingWithdrawals,
        pendingKyc,
      },
    });
  } catch (error) {
    console.error("Admin dashboard API error:", error);
    return NextResponse.json(
      { error: "Failed to fetch admin dashboard data" },
      { status: 500 }
    );
  }
}
