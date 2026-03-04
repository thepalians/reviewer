import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { queryOne, query, execute } from "@/lib/db";
import type { RowDataPacket } from "mysql2";

interface CampaignRow extends RowDataPacket {
  id: number;
  title: string;
  description: string | null;
  url: string | null;
  status: string;
  reward_amount: string | number;
  required_time: number | null;
  admin_approved: number | boolean;
  seller_id: number;
  platform_id: number;
  created_at: Date;
  updated_at: Date;
  platform_name: string;
}

interface CompletionRow extends RowDataPacket {
  id: number;
  completed_at: Date;
  user_id: number;
  user_name: string;
  user_email: string;
}

interface AggStatsRow extends RowDataPacket {
  total_completions: number;
  total_spent: string | number;
}

export async function GET(
  _request: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const session = await auth();
  if (!session || session.user.userType !== "seller") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const sellerId = parseInt(session.user.id);
  const { id } = await params;
  const campaignId = parseInt(id);

  try {
    const campaign = await queryOne<CampaignRow>(
      `SELECT
         sc.id,
         sc.title,
         sc.description,
         sc.url,
         sc.status,
         sc.reward_amount,
         sc.required_time,
         sc.admin_approved,
         sc.seller_id,
         sc.platform_id,
         sc.created_at,
         sc.updated_at,
         sp.name AS platform_name
       FROM social_campaigns sc
       LEFT JOIN social_platforms sp ON sp.id = sc.platform_id
       WHERE sc.id = ? AND sc.seller_id = ?`,
      [campaignId, sellerId]
    );

    if (!campaign) {
      return NextResponse.json({ error: "Campaign not found" }, { status: 404 });
    }

    const [completions, aggStats] = await Promise.all([
      query<CompletionRow>(
        `SELECT
           stc.id,
           stc.created_at AS completed_at,
           u.id AS user_id,
           u.name AS user_name,
           u.email AS user_email
         FROM social_task_completions stc
         INNER JOIN users u ON u.id = stc.user_id
         WHERE stc.campaign_id = ?
         ORDER BY stc.created_at DESC
         LIMIT 50`,
        [campaignId]
      ),
      queryOne<AggStatsRow>(
        `SELECT
           COUNT(stc.id) AS total_completions,
           COALESCE(SUM(sc.reward_amount), 0) AS total_spent
         FROM social_task_completions stc
         INNER JOIN social_campaigns sc ON sc.id = stc.campaign_id
         WHERE stc.campaign_id = ?`,
        [campaignId]
      ),
    ]);

    return NextResponse.json({
      success: true,
      data: {
        id: campaign.id,
        title: campaign.title,
        description: campaign.description,
        url: campaign.url,
        status: campaign.status,
        rewardAmount: Number(campaign.reward_amount),
        requiredTime: campaign.required_time,
        adminApproved: Boolean(campaign.admin_approved),
        sellerId: campaign.seller_id,
        createdAt: campaign.created_at,
        updatedAt: campaign.updated_at,
        platform: { id: campaign.platform_id, name: campaign.platform_name },
        completions: completions.map((c) => ({
          id: c.id,
          completedAt: c.completed_at,
          user: { id: c.user_id, name: c.user_name, email: c.user_email },
        })),
        _count: {
          completions: Number(aggStats?.total_completions ?? 0),
        },
        totalSpent: Number(aggStats?.total_spent ?? 0),
        avgWatchTime: 0,
      },
    });
  } catch (error) {
    console.error("Seller campaign detail API error:", error);
    return NextResponse.json({ error: "Failed to fetch campaign" }, { status: 500 });
  }
}

export async function PUT(
  request: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const session = await auth();
  if (!session || session.user.userType !== "seller") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const sellerId = parseInt(session.user.id);
  const { id } = await params;
  const campaignId = parseInt(id);

  try {
    // Only allow editing pending campaigns
    const existing = await queryOne<RowDataPacket & { id: number; status: string }>(
      "SELECT id, status FROM social_campaigns WHERE id = ? AND seller_id = ?",
      [campaignId, sellerId]
    );

    if (!existing) {
      return NextResponse.json({ error: "Campaign not found" }, { status: 404 });
    }

    if (existing.status !== "pending") {
      return NextResponse.json(
        { error: "Only pending campaigns can be edited" },
        { status: 400 }
      );
    }

    const body = await request.json();
    const { title, description, url, rewardAmount, requiredTime } = body;

    const setClauses: string[] = ["updated_at = NOW()"];
    const values: unknown[] = [];

    if (title) {
      setClauses.push("title = ?");
      values.push(title);
    }
    if (description !== undefined) {
      setClauses.push("description = ?");
      values.push(description);
    }
    if (url !== undefined) {
      setClauses.push("url = ?");
      values.push(url);
    }
    if (rewardAmount) {
      setClauses.push("reward_amount = ?");
      values.push(parseFloat(rewardAmount));
    }
    if (requiredTime !== undefined) {
      setClauses.push("required_time = ?");
      values.push(requiredTime ? parseInt(requiredTime) : null);
    }

    values.push(campaignId);

    await execute(
      `UPDATE social_campaigns SET ${setClauses.join(", ")} WHERE id = ?`,
      values
    );

    const updated = await queryOne<RowDataPacket & {
      id: number;
      title: string;
      status: string;
      reward_amount: string | number;
      updated_at: Date;
    }>(
      "SELECT id, title, status, reward_amount, updated_at FROM social_campaigns WHERE id = ?",
      [campaignId]
    );

    return NextResponse.json({
      success: true,
      data: {
        id: updated!.id,
        title: updated!.title,
        status: updated!.status,
        rewardAmount: Number(updated!.reward_amount),
        updatedAt: updated!.updated_at,
      },
    });
  } catch (error) {
    console.error("Update campaign API error:", error);
    return NextResponse.json({ error: "Failed to update campaign" }, { status: 500 });
  }
}
