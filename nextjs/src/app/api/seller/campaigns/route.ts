import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { prisma } from "@/lib/db";

export async function GET(request: NextRequest) {
  const session = await auth();
  if (!session || session.user.userType !== "seller") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const sellerId = parseInt(session.user.id);
  const { searchParams } = new URL(request.url);
  const status = searchParams.get("status") || "";
  const platformId = searchParams.get("platformId") || "";
  const page = parseInt(searchParams.get("page") || "1");
  const limit = parseInt(searchParams.get("limit") || "20");
  const skip = (page - 1) * limit;

  try {
    const where: Record<string, unknown> = { sellerId };
    if (status) where.status = status;
    if (platformId) where.platformId = parseInt(platformId);

    const [campaigns, total] = await Promise.all([
      prisma.socialCampaign.findMany({
        where,
        select: {
          id: true,
          title: true,
          status: true,
          rewardAmount: true,
          requiredTime: true,
          adminApproved: true,
          createdAt: true,
          platform: { select: { id: true, name: true } },
          _count: { select: { completions: true } },
        },
        orderBy: { createdAt: "desc" },
        skip,
        take: limit,
      }),
      prisma.socialCampaign.count({ where }),
    ]);

    return NextResponse.json({ success: true, data: campaigns, total, page, limit });
  } catch (error) {
    console.error("Seller campaigns API error:", error);
    return NextResponse.json({ error: "Failed to fetch campaigns" }, { status: 500 });
  }
}

export async function POST(request: NextRequest) {
  const session = await auth();
  if (!session || session.user.userType !== "seller") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const sellerId = parseInt(session.user.id);

  try {
    const body = await request.json();
    const { title, description, platformId, url, rewardAmount, requiredTime } = body;

    if (!title || !platformId || !rewardAmount) {
      return NextResponse.json(
        { error: "Title, platform, and reward amount are required" },
        { status: 400 }
      );
    }

    const campaign = await prisma.socialCampaign.create({
      data: {
        sellerId,
        title,
        description: description || null,
        platformId: parseInt(platformId),
        url: url || null,
        rewardAmount: parseFloat(rewardAmount),
        requiredTime: requiredTime ? parseInt(requiredTime) : null,
        status: "pending",
        adminApproved: false,
      },
      select: {
        id: true,
        title: true,
        status: true,
        rewardAmount: true,
        createdAt: true,
        platform: { select: { name: true } },
      },
    });

    return NextResponse.json({ success: true, data: campaign }, { status: 201 });
  } catch (error) {
    console.error("Create campaign API error:", error);
    return NextResponse.json({ error: "Failed to create campaign" }, { status: 500 });
  }
}
