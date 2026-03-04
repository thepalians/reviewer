import { NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { prisma } from "@/lib/db";
import { formatCurrency } from "@/lib/utils";

export async function GET() {
  const session = await auth();
  if (!session || session.user.userType !== "seller") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const sellerId = parseInt(session.user.id);

  try {
    const [seller, campaigns, wallet, recentCampaigns] = await Promise.all([
      prisma.seller.findUnique({
        where: { id: sellerId },
        select: { name: true, email: true },
      }),
      prisma.socialCampaign.groupBy({
        by: ["status"],
        where: { sellerId },
        _count: true,
      }),
      prisma.sellerWallet.findUnique({
        where: { sellerId },
        select: { balance: true },
      }),
      prisma.socialCampaign.findMany({
        where: { sellerId },
        select: {
          id: true,
          title: true,
          status: true,
          rewardAmount: true,
          createdAt: true,
          platform: { select: { name: true } },
          _count: { select: { completions: true } },
        },
        orderBy: { createdAt: "desc" },
        take: 5,
      }),
    ]);

    const totalCampaigns = campaigns.reduce((sum, g) => sum + g._count, 0);
    const activeCampaigns = campaigns.find((g) => g.status === "active")?._count ?? 0;
    const pendingCampaigns = campaigns.find((g) => g.status === "pending")?._count ?? 0;
    const totalCompletions = await prisma.socialTaskCompletion.count({
      where: { campaign: { sellerId } },
    });
    const totalSpentResult = await prisma.socialTaskCompletion.aggregate({
      where: { campaign: { sellerId } },
      _sum: { reward: true },
    });

    return NextResponse.json({
      success: true,
      data: {
        seller,
        stats: {
          totalCampaigns,
          activeCampaigns,
          pendingCampaigns,
          totalCompletions,
          totalSpent: formatCurrency(Number(totalSpentResult._sum.reward ?? 0)),
          walletBalance: formatCurrency(Number(wallet?.balance ?? 0)),
        },
        recentCampaigns,
      },
    });
  } catch (error) {
    console.error("Seller dashboard API error:", error);
    return NextResponse.json({ error: "Failed to fetch dashboard data" }, { status: 500 });
  }
}
