import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { prisma } from "@/lib/db";

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
    const campaign = await prisma.socialCampaign.findFirst({
      where: { id: campaignId, sellerId },
      include: {
        platform: { select: { id: true, name: true } },
        completions: {
          select: {
            id: true,
            completedAt: true,
            reward: true,
            user: { select: { id: true, name: true, email: true } },
          },
          orderBy: { completedAt: "desc" },
          take: 50,
        },
        sessions: {
          select: {
            id: true,
            startedAt: true,
            endedAt: true,
            duration: true,
            user: { select: { id: true, name: true } },
          },
          orderBy: { startedAt: "desc" },
          take: 50,
        },
        _count: { select: { completions: true, sessions: true } },
      },
    });

    if (!campaign) {
      return NextResponse.json({ error: "Campaign not found" }, { status: 404 });
    }

    const totalSpent = await prisma.socialTaskCompletion.aggregate({
      where: { campaignId },
      _sum: { reward: true },
    });

    const avgDuration = await prisma.socialWatchSession.aggregate({
      where: { campaignId, duration: { not: null } },
      _avg: { duration: true },
    });

    return NextResponse.json({
      success: true,
      data: {
        ...campaign,
        totalSpent: Number(totalSpent._sum.reward ?? 0),
        avgWatchTime: Math.round(avgDuration._avg.duration ?? 0),
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
    const existing = await prisma.socialCampaign.findFirst({
      where: { id: campaignId, sellerId },
    });

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

    const updated = await prisma.socialCampaign.update({
      where: { id: campaignId },
      data: {
        ...(title && { title }),
        ...(description !== undefined && { description }),
        ...(url !== undefined && { url }),
        ...(rewardAmount && { rewardAmount: parseFloat(rewardAmount) }),
        ...(requiredTime !== undefined && {
          requiredTime: requiredTime ? parseInt(requiredTime) : null,
        }),
      },
      select: {
        id: true,
        title: true,
        status: true,
        rewardAmount: true,
        updatedAt: true,
      },
    });

    return NextResponse.json({ success: true, data: updated });
  } catch (error) {
    console.error("Update campaign API error:", error);
    return NextResponse.json({ error: "Failed to update campaign" }, { status: 500 });
  }
}
