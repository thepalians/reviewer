import { NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { prisma } from "@/lib/db";

export async function GET() {
  const session = await auth();

  if (!session || session.user.userType !== "user") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const userId = parseInt(session.user.id);

  try {
    const [user, taskCounts, recentTasks, activeCampaigns] = await Promise.all([
      prisma.user.findUnique({
        where: { id: userId },
        select: { name: true, walletBalance: true },
      }),
      prisma.task.groupBy({
        by: ["status"],
        where: { userId },
        _count: true,
      }),
      prisma.task.findMany({
        where: { userId },
        orderBy: { createdAt: "desc" },
        take: 5,
        select: {
          id: true,
          productName: true,
          platform: true,
          status: true,
          commission: true,
          createdAt: true,
          orderId: true,
          deadline: true,
        },
      }),
      prisma.socialCampaign.count({
        where: { status: "active", adminApproved: true },
      }),
    ]);

    const totalTasks = taskCounts.reduce((sum, g) => sum + g._count, 0);
    const completedTasks = taskCounts.find((g) => g.status === "completed")?._count ?? 0;
    const pendingTasks = taskCounts
      .filter((g) => ["pending", "assigned", "in_progress"].includes(g.status))
      .reduce((sum, g) => sum + g._count, 0);

    return NextResponse.json({
      success: true,
      data: {
        user: {
          name: user?.name,
          walletBalance: Number(user?.walletBalance ?? 0),
        },
        stats: {
          totalTasks,
          completedTasks,
          pendingTasks,
          activeCampaigns,
        },
        recentTasks: recentTasks.map((t) => ({
          ...t,
          commission: t.commission ? Number(t.commission) : null,
          createdAt: t.createdAt.toISOString(),
          deadline: t.deadline?.toISOString() ?? null,
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
