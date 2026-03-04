import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { queryOne, execute } from "@/lib/db";
import type { RowDataPacket } from "mysql2";

interface UserRow extends RowDataPacket {
  id: number;
  name: string;
  email: string;
  mobile: string | null;
  upi_id: string | null;
  bank_name: string | null;
  bank_account: string | null;
  bank_ifsc: string | null;
  wallet_balance: number;
  referral_code: string | null;
  kyc_status: string | null;
  created_at: Date;
}

export async function GET() {
  const session = await auth();
  if (!session?.user?.id) {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const user = await queryOne<UserRow>(
    `SELECT id, name, email, mobile, upi_id, bank_name, bank_account, bank_ifsc,
            wallet_balance, referral_code, kyc_status, created_at
     FROM users WHERE id = ?`,
    [parseInt(session.user.id)]
  );

  if (!user) {
    return NextResponse.json({ error: "User not found" }, { status: 404 });
  }

  return NextResponse.json({
    user: {
      id: user.id,
      name: user.name,
      email: user.email,
      mobile: user.mobile,
      upiId: user.upi_id,
      bankName: user.bank_name,
      bankAccount: user.bank_account,
      bankIfsc: user.bank_ifsc,
      walletBalance: Number(user.wallet_balance),
      referralCode: user.referral_code,
      kycStatus: user.kyc_status,
      createdAt: user.created_at instanceof Date ? user.created_at.toISOString() : String(user.created_at),
    },
  });
}

export async function PUT(request: NextRequest) {
  const session = await auth();
  if (!session?.user?.id) {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const body = await request.json();
  const { name, email, mobile, bankName, bankAccount, bankIfsc, upiId } = body;

  try {
    const userId = parseInt(session.user.id);

    // Build dynamic SET clause from provided fields
    const fields: string[] = [];
    const values: unknown[] = [];

    if (name !== undefined) { fields.push("name = ?"); values.push(name); }
    if (email !== undefined) { fields.push("email = ?"); values.push(email); }
    if (mobile !== undefined) { fields.push("mobile = ?"); values.push(mobile); }
    if (bankName !== undefined) { fields.push("bank_name = ?"); values.push(bankName); }
    if (bankAccount !== undefined) { fields.push("bank_account = ?"); values.push(bankAccount); }
    if (bankIfsc !== undefined) { fields.push("bank_ifsc = ?"); values.push(bankIfsc); }
    if (upiId !== undefined) { fields.push("upi_id = ?"); values.push(upiId); }

    if (fields.length === 0) {
      return NextResponse.json({ error: "No fields to update" }, { status: 400 });
    }

    fields.push("updated_at = NOW()");
    values.push(userId);

    await execute(
      `UPDATE users SET ${fields.join(", ")} WHERE id = ?`,
      values
    );

    const updated = await queryOne<UserRow>(
      `SELECT id, name, email, mobile, upi_id, bank_name, bank_account, bank_ifsc
       FROM users WHERE id = ?`,
      [userId]
    );

    return NextResponse.json({
      success: true,
      user: {
        id: updated?.id,
        name: updated?.name,
        email: updated?.email,
        mobile: updated?.mobile,
        upiId: updated?.upi_id,
        bankName: updated?.bank_name,
        bankAccount: updated?.bank_account,
        bankIfsc: updated?.bank_ifsc,
      },
    });
  } catch (error: unknown) {
    const message = error instanceof Error ? error.message : "Update failed";
    return NextResponse.json({ error: message }, { status: 400 });
  }
}
