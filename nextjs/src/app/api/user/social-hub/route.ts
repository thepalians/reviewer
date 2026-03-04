import { NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { query } from "@/lib/db";
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

export async function GET() {
  const session = await auth();

  if (!session || session.user.userType !== "user") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  try {
    const campaigns = await query<CampaignRow>(
      `SELECT sc.*, sp.name AS platform_name, sp.icon AS platform_icon
       FROM social_campaigns sc
       LEFT JOIN social_platforms sp ON sp.id = sc.platform_id
       WHERE sc.status = 'active' AND sc.admin_approved = 1
       ORDER BY sc.created_at DESC`,
      []
    );

    return NextResponse.json({
      success: true,
      data: campaigns.map((c) => ({
        id: c.id,
        title: c.title,
        description: c.description,
        platformId: c.platform_id,
        rewardAmount: Number(c.reward_amount),
        status: c.status,
        adminApproved: Boolean(c.admin_approved),
        sellerId: c.seller_id,
        createdAt: c.created_at instanceof Date ? c.created_at.toISOString() : String(c.created_at),
        updatedAt: c.updated_at instanceof Date ? c.updated_at.toISOString() : String(c.updated_at),
        platform: c.platform_id
          ? {
              id: c.platform_id,
              name: c.platform_name,
              icon: c.platform_icon,
            }
          : null,
      })),
    });
  } catch (error) {
    console.error("Social hub API error:", error);
    return NextResponse.json({ error: "Failed to fetch campaigns" }, { status: 500 });
  }
}
