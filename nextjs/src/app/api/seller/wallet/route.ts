import { NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { queryOne, query } from "@/lib/db";
import type { RowDataPacket } from "mysql2";

interface SellerWalletRow extends RowDataPacket {
  wallet_balance: string | number;
  updated_at: Date;
}

interface WalletTransactionRow extends RowDataPacket {
  id: number;
  seller_id: number;
  type: string;
  amount: string | number;
  description: string | null;
  created_at: Date;
}

export async function GET() {
  const session = await auth();
  if (!session || session.user.userType !== "seller") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const sellerId = parseInt(session.user.id);

  try {
    const [seller, transactions] = await Promise.all([
      queryOne<SellerWalletRow>(
        "SELECT wallet_balance, updated_at FROM sellers WHERE id = ?",
        [sellerId]
      ),
      query<WalletTransactionRow>(
        `SELECT id, seller_id, type, amount, description, created_at
         FROM seller_wallet_transactions
         WHERE seller_id = ?
         ORDER BY created_at DESC
         LIMIT 50`,
        [sellerId]
      ),
    ]);

    return NextResponse.json({
      success: true,
      data: {
        balance: Number(seller?.wallet_balance ?? 0),
        updatedAt: seller?.updated_at ?? null,
        transactions: transactions.map((t) => ({
          id: t.id,
          sellerId: t.seller_id,
          type: t.type,
          amount: Number(t.amount),
          description: t.description,
          createdAt: t.created_at,
        })),
      },
    });
  } catch (error) {
    console.error("Seller wallet API error:", error);
    return NextResponse.json({ error: "Failed to fetch wallet data" }, { status: 500 });
  }
}
