import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { prisma } from "@/lib/db";

export async function GET(request: NextRequest) {
  const session = await auth();
  if (!session || session.user.userType !== "admin") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const { searchParams } = new URL(request.url);
  const status = searchParams.get("status") || "";
  const page = parseInt(searchParams.get("page") || "1");
  const limit = parseInt(searchParams.get("limit") || "20");
  const skip = (page - 1) * limit;

  try {
    const where: Record<string, unknown> = {};
    if (status) where.status = status;

    const [campaigns, total] = await Promise.all([
      prisma.socialCampaign.findMany({
        where,
        select: {
          id: true,
          title: true,
          rewardAmount: true,
          status: true,
          adminApproved: true,
          createdAt: true,
          seller: { select: { id: true, name: true, email: true } },
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
    console.error("Admin campaigns API error:", error);
    return NextResponse.json({ error: "Failed to fetch campaigns" }, { status: 500 });
  }
}
