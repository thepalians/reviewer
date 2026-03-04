import { NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { query, queryOne } from "@/lib/db";
import { formatCurrency } from "@/lib/utils";
import type { RowDataPacket } from "mysql2";

interface SellerRow extends RowDataPacket {
  name: string;
  email: string;
  wallet_balance: string | number;
}

interface CampaignStatusRow extends RowDataPacket {
  status: string;
  count: number;
}

interface RecentCampaignRow extends RowDataPacket {
  id: number;
  title: string;
  status: string;
  reward_amount: string | number;
  created_at: Date;
  platform_name: string;
  completions_count: number;
}

interface CompletionStatsRow extends RowDataPacket {
  total_completions: number;
  total_spent: string | number;
}

export async function GET() {
  const session = await auth();
  if (!session || session.user.userType !== "seller") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const sellerId = parseInt(session.user.id);

  try {
    const [seller, campaignStatuses, recentCampaigns, completionStats] = await Promise.all([
      queryOne<SellerRow>(
        "SELECT name, email, wallet_balance FROM sellers WHERE id = ?",
        [sellerId]
      ),
      query<CampaignStatusRow>(
        "SELECT status, COUNT(*) AS count FROM social_campaigns WHERE seller_id = ? GROUP BY status",
        [sellerId]
      ),
      query<RecentCampaignRow>(
        `SELECT
           sc.id,
           sc.title,
           sc.status,
           sc.reward_amount,
           sc.created_at,
           sp.name AS platform_name,
           COUNT(stc.id) AS completions_count
         FROM social_campaigns sc
         LEFT JOIN social_platforms sp ON sp.id = sc.platform_id
         LEFT JOIN social_task_completions stc ON stc.campaign_id = sc.id
         WHERE sc.seller_id = ?
         GROUP BY sc.id, sc.title, sc.status, sc.reward_amount, sc.created_at, sp.name
         ORDER BY sc.created_at DESC
         LIMIT 5`,
        [sellerId]
      ),
      queryOne<CompletionStatsRow>(
        `SELECT
           COUNT(stc.id) AS total_completions,
           COALESCE(SUM(sc.reward_amount), 0) AS total_spent
         FROM social_task_completions stc
         INNER JOIN social_campaigns sc ON sc.id = stc.campaign_id
         WHERE sc.seller_id = ?`,
        [sellerId]
      ),
    ]);

    const totalCampaigns = campaignStatuses.reduce((sum, g) => sum + Number(g.count), 0);
    const activeCampaigns = campaignStatuses.find((g) => g.status === "active")?.count ?? 0;
    const pendingCampaigns = campaignStatuses.find((g) => g.status === "pending")?.count ?? 0;
    const totalCompletions = Number(completionStats?.total_completions ?? 0);
    const totalSpent = Number(completionStats?.total_spent ?? 0);
    const walletBalance = Number(seller?.wallet_balance ?? 0);

    return NextResponse.json({
      success: true,
      data: {
        seller: seller
          ? { name: seller.name, email: seller.email }
          : null,
        stats: {
          totalCampaigns,
          activeCampaigns: Number(activeCampaigns),
          pendingCampaigns: Number(pendingCampaigns),
          totalCompletions,
          totalSpent: formatCurrency(totalSpent),
          walletBalance: formatCurrency(walletBalance),
        },
        recentCampaigns: recentCampaigns.map((c) => ({
          id: c.id,
          title: c.title,
          status: c.status,
          rewardAmount: Number(c.reward_amount),
          createdAt: c.created_at,
          platform: { name: c.platform_name },
          _count: { completions: Number(c.completions_count) },
        })),
      },
    });
  } catch (error) {
    console.error("Seller dashboard API error:", error);
    return NextResponse.json({ error: "Failed to fetch dashboard data" }, { status: 500 });
  }
}
