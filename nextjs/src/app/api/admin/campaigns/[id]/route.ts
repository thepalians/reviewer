import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { prisma } from "@/lib/db";

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

    const updated = await prisma.socialCampaign.update({
      where: { id: campaignId },
      data: {
        adminApproved: action === "approve",
        status: action === "approve" ? "active" : "rejected",
      },
    });

    return NextResponse.json({ success: true, data: updated });
  } catch (error) {
    console.error("Admin campaign update API error:", error);
    return NextResponse.json({ error: "Failed to update campaign" }, { status: 500 });
  }
}
