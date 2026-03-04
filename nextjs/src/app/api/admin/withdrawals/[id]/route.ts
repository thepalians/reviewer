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
  const txId = parseInt(id);
  if (isNaN(txId)) {
    return NextResponse.json({ error: "Invalid ID" }, { status: 400 });
  }

  try {
    const body = await request.json();
    const { action } = body; // "approve" or "reject"

    if (!["approve", "reject"].includes(action)) {
      return NextResponse.json({ error: "Invalid action" }, { status: 400 });
    }

    const newType = action === "approve" ? "withdrawal_approved" : "withdrawal_rejected";

    const updated = await prisma.walletTransaction.update({
      where: { id: txId },
      data: { type: newType },
    });

    return NextResponse.json({ success: true, data: updated });
  } catch (error) {
    console.error("Admin withdrawal update API error:", error);
    return NextResponse.json({ error: "Failed to update withdrawal" }, { status: 500 });
  }
}
