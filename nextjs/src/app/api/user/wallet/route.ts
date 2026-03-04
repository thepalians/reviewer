import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { queryOne, query } from "@/lib/db";
import type { RowDataPacket } from "mysql2";

interface UserRow extends RowDataPacket {
  wallet_balance: number;
}

interface WalletTransactionRow extends RowDataPacket {
  id: number;
  user_id: number;
  type: string;
  amount: number;
  description: string | null;
  reference_id: string | null;
  balance_before: number | null;
  balance_after: number | null;
  created_at: Date;
}

interface AggRow extends RowDataPacket {
  total: number | null;
}

export async function GET(_req: NextRequest) {
  const session = await auth();

  if (!session || session.user.userType !== "user") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const userId = parseInt(session.user.id);

  try {
    const [user, transactions, earnedRow, withdrawnRow] = await Promise.all([
      queryOne<UserRow>(
        "SELECT wallet_balance FROM users WHERE id = ?",
        [userId]
      ),
      query<WalletTransactionRow>(
        "SELECT * FROM wallet_transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 50",
        [userId]
      ),
      queryOne<AggRow>(
        "SELECT SUM(amount) AS total FROM wallet_transactions WHERE user_id = ? AND type = 'credit'",
        [userId]
      ),
      queryOne<AggRow>(
        "SELECT SUM(amount) AS total FROM wallet_transactions WHERE user_id = ? AND type = 'debit'",
        [userId]
      ),
    ]);

    const balance = Number(user?.wallet_balance ?? 0);

    return NextResponse.json({
      success: true,
      data: {
        balance,
        totalEarned: Number(earnedRow?.total ?? 0),
        totalWithdrawn: Number(withdrawnRow?.total ?? 0),
        transactions: transactions.map((t) => ({
          id: t.id,
          userId: t.user_id,
          type: t.type,
          amount: Number(t.amount),
          description: t.description,
          referenceId: t.reference_id,
          balanceBefore: t.balance_before != null ? Number(t.balance_before) : null,
          balanceAfter: t.balance_after != null ? Number(t.balance_after) : null,
          createdAt: t.created_at instanceof Date ? t.created_at.toISOString() : String(t.created_at),
        })),
      },
    });
  } catch (error) {
    console.error("Wallet API error:", error);
    return NextResponse.json(
      { error: "Failed to fetch wallet data" },
      { status: 500 }
    );
  }
}
