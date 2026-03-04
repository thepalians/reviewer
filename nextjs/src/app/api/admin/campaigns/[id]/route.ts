import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { queryOne, execute } from "@/lib/db";
import type { RowDataPacket } from "mysql2";

interface CampaignRow extends RowDataPacket {
  id: number;
  title: string;
  description: string | null;
  platform_id: number;
  reward_amount: number;
  status: string;
  admin_approved: number;
  seller_id: number;
  created_at: string;
  updated_at: string;
}

export async function PUT(
  request: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const session = await auth();
  if (!session || session.user.userType !== "admin") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const { id } = await params;
  const campaignId = parseInt(id);
  if (isNaN(campaignId)) {
    return NextResponse.json({ error: "Invalid campaign ID" }, { status: 400 });
  }

  try {
    const body = await request.json();
    const { action } = body; // "approve" or "reject"

    if (!["approve", "reject"].includes(action)) {
      return NextResponse.json({ error: "Invalid action" }, { status: 400 });
    }

    const adminApproved = action === "approve" ? 1 : 0;
    const newStatus = action === "approve" ? "active" : "rejected";

    await execute(
      "UPDATE social_campaigns SET admin_approved = ?, status = ?, updated_at = NOW() WHERE id = ?",
      [adminApproved, newStatus, campaignId]
    );

    const updated = await queryOne<CampaignRow>(
      "SELECT * FROM social_campaigns WHERE id = ?",
      [campaignId]
    );

    if (!updated) {
      return NextResponse.json({ error: "Campaign not found" }, { status: 404 });
    }

    return NextResponse.json({
      success: true,
      data: {
        id: updated.id,
        title: updated.title,
        description: updated.description,
        platformId: updated.platform_id,
        rewardAmount: updated.reward_amount,
        status: updated.status,
        adminApproved: updated.admin_approved === 1,
        sellerId: updated.seller_id,
        createdAt: updated.created_at,
        updatedAt: updated.updated_at,
      },
    });
  } catch (error) {
    console.error("Admin campaign update API error:", error);
    return NextResponse.json({ error: "Failed to update campaign" }, { status: 500 });
  }
}
