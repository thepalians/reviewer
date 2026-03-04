import { NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { query } from "@/lib/db";
import type { RowDataPacket } from "mysql2";

interface CountRow extends RowDataPacket {
  count: number;
}

export async function GET() {
  const session = await auth();

  if (!session || session.user.userType !== "admin") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  try {
    const [
      totalUsersRows,
      totalSellersRows,
      totalTasksRows,
      pendingTasksRows,
      completedTasksRows,
      pendingWithdrawalsRows,
      pendingKycRows,
    ] = await Promise.all([
      query<CountRow>("SELECT COUNT(*) AS count FROM users WHERE user_type = 'user'"),
      query<CountRow>("SELECT COUNT(*) AS count FROM sellers"),
      query<CountRow>("SELECT COUNT(*) AS count FROM tasks"),
      query<CountRow>(
        "SELECT COUNT(*) AS count FROM tasks WHERE status IN ('pending', 'assigned', 'in_progress')"
      ),
      query<CountRow>("SELECT COUNT(*) AS count FROM tasks WHERE status = 'completed'"),
      query<CountRow>(
        "SELECT COUNT(*) AS count FROM withdrawal_requests WHERE status = 'pending'"
      ),
      query<CountRow>("SELECT COUNT(*) AS count FROM kyc_documents WHERE status = 'pending'"),
    ]);

    return NextResponse.json({
      success: true,
      data: {
        totalUsers: totalUsersRows[0].count,
        totalSellers: totalSellersRows[0].count,
        totalTasks: totalTasksRows[0].count,
        pendingTasks: pendingTasksRows[0].count,
        completedTasks: completedTasksRows[0].count,
        pendingWithdrawals: pendingWithdrawalsRows[0].count,
        pendingKyc: pendingKycRows[0].count,
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
