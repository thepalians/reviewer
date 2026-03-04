import { NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { query, queryOne } from "@/lib/db";
import type { RowDataPacket } from "mysql2";

interface UserRow extends RowDataPacket {
  name: string;
  wallet_balance: number;
}

interface TaskCountRow extends RowDataPacket {
  status: string;
  count: number;
}

interface RecentTaskRow extends RowDataPacket {
  id: number;
  product_name: string;
  platform: string;
  status: string;
  commission: number | null;
  created_at: Date;
  order_id: string | null;
  deadline: Date | null;
}

interface CampaignCountRow extends RowDataPacket {
  count: number;
}

export async function GET() {
  const session = await auth();

  if (!session || session.user.userType !== "user") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const userId = parseInt(session.user.id);

  try {
    const [user, taskCounts, recentTasks, campaignCountRow] = await Promise.all([
      queryOne<UserRow>(
        "SELECT name, wallet_balance FROM users WHERE id = ?",
        [userId]
      ),
      query<TaskCountRow>(
        "SELECT status, COUNT(*) AS count FROM tasks WHERE user_id = ? GROUP BY status",
        [userId]
      ),
      query<RecentTaskRow>(
        `SELECT id, product_name, platform, status, commission, created_at, order_id, deadline
         FROM tasks
         WHERE user_id = ?
         ORDER BY created_at DESC
         LIMIT 5`,
        [userId]
      ),
      queryOne<CampaignCountRow>(
        "SELECT COUNT(*) AS count FROM social_campaigns WHERE status = 'active' AND admin_approved = 1",
        []
      ),
    ]);

    const totalTasks = taskCounts.reduce((sum, g) => sum + Number(g.count), 0);
    const completedTasks = taskCounts.find((g) => g.status === "completed")
      ? Number(taskCounts.find((g) => g.status === "completed")!.count)
      : 0;
    const pendingTasks = taskCounts
      .filter((g) => ["pending", "assigned", "in_progress"].includes(g.status))
      .reduce((sum, g) => sum + Number(g.count), 0);

    const activeCampaigns = Number(campaignCountRow?.count ?? 0);

    return NextResponse.json({
      success: true,
      data: {
        user: {
          name: user?.name ?? null,
          walletBalance: Number(user?.wallet_balance ?? 0),
        },
        stats: {
          totalTasks,
          completedTasks,
          pendingTasks,
          activeCampaigns,
        },
        recentTasks: recentTasks.map((t) => ({
          id: t.id,
          productName: t.product_name,
          platform: t.platform,
          status: t.status,
          commission: t.commission != null ? Number(t.commission) : null,
          createdAt: t.created_at instanceof Date ? t.created_at.toISOString() : String(t.created_at),
          orderId: t.order_id,
          deadline: t.deadline
            ? (t.deadline instanceof Date ? t.deadline.toISOString() : String(t.deadline))
            : null,
        })),
      },
    });
  } catch (error) {
    console.error("Dashboard API error:", error);
    return NextResponse.json(
      { error: "Failed to fetch dashboard data" },
      { status: 500 }
    );
  }
}
