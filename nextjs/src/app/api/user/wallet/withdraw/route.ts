import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { queryOne, transaction } from "@/lib/db";
import type { RowDataPacket } from "mysql2";
import { z } from "zod";

interface UserRow extends RowDataPacket {
  wallet_balance: number;
}

const withdrawSchema = z.object({
  amount: z.number().positive().min(1),
  paymentMethod: z.enum(["upi", "bank"]),
  upiId: z.string().optional(),
  accountName: z.string().optional(),
  accountNumber: z.string().optional(),
  bankName: z.string().optional(),
  ifscCode: z.string().optional(),
});

export async function POST(req: NextRequest) {
  const session = await auth();

  if (!session || session.user.userType !== "user") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const userId = parseInt(session.user.id);

  let body: unknown;
  try {
    body = await req.json();
  } catch {
    return NextResponse.json({ error: "Invalid JSON body" }, { status: 400 });
  }

  const parsed = withdrawSchema.safeParse(body);
  if (!parsed.success) {
    return NextResponse.json(
      { error: "Invalid request data", details: parsed.error.flatten() },
      { status: 400 }
    );
  }

  const { amount, paymentMethod, upiId, accountName, accountNumber, bankName, ifscCode } =
    parsed.data;

  try {
    // Check balance (use user's wallet_balance as source of truth)
    const user = await queryOne<UserRow>(
      "SELECT wallet_balance FROM users WHERE id = ?",
      [userId]
    );

    if (!user || Number(user.wallet_balance) < amount) {
      return NextResponse.json(
        { error: "Insufficient wallet balance" },
        { status: 400 }
      );
    }

    const description =
      paymentMethod === "upi"
        ? `Withdrawal request via UPI (${upiId ?? ""})`
        : `Withdrawal request via Bank Transfer (${accountNumber ?? ""})`;

    const roundedAmount = Math.round(amount * 100) / 100;
    const balanceBefore = Number(user.wallet_balance);
    const balanceAfter = balanceBefore - roundedAmount;

    await transaction(async (conn) => {
      // Insert wallet transaction record
      await conn.execute(
        `INSERT INTO wallet_transactions (user_id, type, amount, description, balance_before, balance_after, created_at)
         VALUES (?, 'debit', ?, ?, ?, ?, NOW())`,
        [userId, roundedAmount, description, balanceBefore, balanceAfter]
      );

      // Deduct from user wallet balance
      await conn.execute(
        "UPDATE users SET wallet_balance = wallet_balance - ?, updated_at = NOW() WHERE id = ?",
        [roundedAmount, userId]
      );

      // Insert into withdrawal_requests
      await conn.execute(
        `INSERT INTO withdrawal_requests (user_id, amount, method, upi_id, account_number, ifsc_code, bank_name, status, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())`,
        [
          userId,
          roundedAmount,
          paymentMethod,
          upiId ?? null,
          accountNumber ?? null,
          ifscCode ?? null,
          bankName ?? null,
        ]
      );
    });

    // Persist payment details on user profile
    if (paymentMethod === "upi" && upiId) {
      await queryOne(
        "UPDATE users SET upi_id = ?, updated_at = NOW() WHERE id = ?",
        [upiId, userId]
      );
    } else if (paymentMethod === "bank" && accountNumber) {
      await queryOne(
        "UPDATE users SET bank_account = ?, bank_name = ?, bank_ifsc = ?, updated_at = NOW() WHERE id = ?",
        [accountNumber, bankName ?? null, ifscCode ?? null, userId]
      );
    }

    return NextResponse.json({
      success: true,
      message: "Withdrawal request submitted successfully",
    });
  } catch (error) {
    console.error("Withdrawal error:", error);
    return NextResponse.json(
      { error: "Failed to process withdrawal" },
      { status: 500 }
    );
  }
}
