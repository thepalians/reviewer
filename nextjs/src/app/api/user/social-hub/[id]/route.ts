import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { queryOne } from "@/lib/db";
import type { RowDataPacket } from "mysql2";

interface CampaignRow extends RowDataPacket {
  id: number;
  title: string;
  description: string | null;
  platform_id: number | null;
  reward_amount: number;
  status: string;
  admin_approved: boolean;
  seller_id: number | null;
  created_at: Date;
  updated_at: Date;
  // platform join fields
  platform_name: string | null;
  platform_icon: string | null;
}

export async function GET(
  _req: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const session = await auth();

  if (!session || session.user.userType !== "user") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const { id } = await params;
  const campaignId = parseInt(id);
  if (isNaN(campaignId)) {
    return NextResponse.json({ error: "Invalid campaign id" }, { status: 400 });
  }

  try {
    const campaign = await queryOne<CampaignRow>(
      `SELECT sc.*, sp.name AS platform_name, sp.icon AS platform_icon
       FROM social_campaigns sc
       LEFT JOIN social_platforms sp ON sp.id = sc.platform_id
       WHERE sc.id = ? AND sc.status = 'active' AND sc.admin_approved = 1`,
      [campaignId]
    );

    if (!campaign) {
      return NextResponse.json({ error: "Campaign not found" }, { status: 404 });
    }

    return NextResponse.json({
      success: true,
      data: {
        id: campaign.id,
        title: campaign.title,
        description: campaign.description,
        platformId: campaign.platform_id,
        rewardAmount: Number(campaign.reward_amount),
        status: campaign.status,
        adminApproved: Boolean(campaign.admin_approved),
        sellerId: campaign.seller_id,
        createdAt: campaign.created_at instanceof Date ? campaign.created_at.toISOString() : String(campaign.created_at),
        updatedAt: campaign.updated_at instanceof Date ? campaign.updated_at.toISOString() : String(campaign.updated_at),
        platform: campaign.platform_id
          ? {
              id: campaign.platform_id,
              name: campaign.platform_name,
              icon: campaign.platform_icon,
            }
          : null,
      },
    });
  } catch (error) {
    console.error("Social hub campaign API error:", error);
    return NextResponse.json({ error: "Failed to fetch campaign" }, { status: 500 });
  }
}
