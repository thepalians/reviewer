import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { queryOne, execute } from "@/lib/db";
import type { RowDataPacket } from "mysql2";

interface WithdrawalRow extends RowDataPacket {
  id: number;
  user_id: number;
  amount: number;
  method: string;
  upi_id: string | null;
  account_number: string | null;
  ifsc_code: string | null;
  bank_name: string | null;
  status: string;
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
  const withdrawalId = parseInt(id);
  if (isNaN(withdrawalId)) {
    return NextResponse.json({ error: "Invalid ID" }, { status: 400 });
  }

  try {
    const body = await request.json();
    const { action } = body; // "approve" or "reject"

    if (!["approve", "reject"].includes(action)) {
      return NextResponse.json({ error: "Invalid action" }, { status: 400 });
    }

    const newStatus = action === "approve" ? "approved" : "rejected";

    await execute(
      "UPDATE withdrawal_requests SET status = ?, updated_at = NOW() WHERE id = ?",
      [newStatus, withdrawalId]
    );

    const updated = await queryOne<WithdrawalRow>(
      "SELECT * FROM withdrawal_requests WHERE id = ?",
      [withdrawalId]
    );

    if (!updated) {
      return NextResponse.json({ error: "Withdrawal request not found" }, { status: 404 });
    }

    return NextResponse.json({
      success: true,
      data: {
        id: updated.id,
        userId: updated.user_id,
        amount: updated.amount,
        method: updated.method,
        upiId: updated.upi_id,
        accountNumber: updated.account_number,
        ifscCode: updated.ifsc_code,
        bankName: updated.bank_name,
        status: updated.status,
        createdAt: updated.created_at,
        updatedAt: updated.updated_at,
      },
    });
  } catch (error) {
    console.error("Admin withdrawal update API error:", error);
    return NextResponse.json({ error: "Failed to update withdrawal" }, { status: 500 });
  }
}
