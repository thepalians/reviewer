import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { prisma } from "@/lib/db";

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
    const campaign = await prisma.socialCampaign.findFirst({
      where: { id: campaignId, status: "active", adminApproved: true },
    });

    if (!campaign) {
      return NextResponse.json({ error: "Campaign not found" }, { status: 404 });
    }

    const existing = await prisma.socialTaskCompletion.findUnique({
      where: { userId_campaignId: { userId, campaignId } },
    });

    if (existing) {
      return NextResponse.json({ error: "Already completed" }, { status: 409 });
    }

    const reward = Number(campaign.rewardAmount);

    await prisma.$transaction([
      prisma.socialTaskCompletion.create({
        data: { userId, campaignId, reward: campaign.rewardAmount },
      }),
      prisma.userWallet.upsert({
        where: { userId },
        create: { userId, balance: reward },
        update: { balance: { increment: reward } },
      }),
    ]);

    return NextResponse.json({ success: true, reward });
  } catch (error) {
    // Handle unique constraint violation (concurrent completion attempts)
    if (
      error instanceof Error &&
      error.message.includes("Unique constraint failed")
    ) {
      return NextResponse.json({ error: "Already completed" }, { status: 409 });
    }
    console.error("Social hub complete API error:", error);
    return NextResponse.json({ error: "Failed to complete campaign" }, { status: 500 });
  }
}
