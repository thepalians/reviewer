import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { queryOne, transaction } from "@/lib/db";
import type { RowDataPacket } from "mysql2";

interface CampaignRow extends RowDataPacket {
  id: number;
  reward_amount: number;
  status: string;
  admin_approved: boolean;
}

interface CompletionRow extends RowDataPacket {
  id: number;
}

export async function POST(
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

  const userId = parseInt(session.user.id);

  try {
    const campaign = await queryOne<CampaignRow>(
      "SELECT id, reward_amount, status, admin_approved FROM social_campaigns WHERE id = ? AND status = 'active' AND admin_approved = 1",
      [campaignId]
    );

    if (!campaign) {
      return NextResponse.json({ error: "Campaign not found" }, { status: 404 });
    }

    const existing = await queryOne<CompletionRow>(
      "SELECT id FROM social_task_completions WHERE user_id = ? AND campaign_id = ?",
      [userId, campaignId]
    );

    if (existing) {
      return NextResponse.json({ error: "Already completed" }, { status: 409 });
    }

    const reward = Number(campaign.reward_amount);

    await transaction(async (conn) => {
      // Insert completion record
      await conn.execute(
        "INSERT INTO social_task_completions (user_id, campaign_id, created_at) VALUES (?, ?, NOW())",
        [userId, campaignId]
      );

      // Credit user wallet
      await conn.execute(
        "UPDATE users SET wallet_balance = wallet_balance + ?, updated_at = NOW() WHERE id = ?",
        [reward, userId]
      );

      // Record wallet transaction
      await conn.execute(
        `INSERT INTO wallet_transactions (user_id, type, amount, description, created_at)
         VALUES (?, 'credit', ?, ?, NOW())`,
        [userId, reward, `Social campaign reward: campaign #${campaignId}`]
      );
    });

    return NextResponse.json({ success: true, reward });
  } catch (error) {
    // Handle unique constraint violation (concurrent completion attempts)
    if (
      error instanceof Error &&
      (error.message.includes("Duplicate entry") || error.message.includes("ER_DUP_ENTRY"))
    ) {
      return NextResponse.json({ error: "Already completed" }, { status: 409 });
    }
    console.error("Social hub complete API error:", error);
    return NextResponse.json({ error: "Failed to complete campaign" }, { status: 500 });
  }
}
