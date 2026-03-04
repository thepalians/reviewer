import { auth } from "@/lib/auth";
import { redirect } from "next/navigation";
import { query, queryOne } from "@/lib/db";
import WalletCard from "@/components/WalletCard";
import TransactionHistory from "@/components/TransactionHistory";
import WalletPageClient from "./WalletPageClient";
import type { WalletTransaction } from "@/types";
import type { Metadata } from "next";
import type { RowDataPacket } from "mysql2";

export const metadata: Metadata = { title: "Wallet" };

interface UserRow extends RowDataPacket {
  wallet_balance: string;
  upi_id: string | null;
  account_name: string | null;
  account_number: string | null;
  bank_name: string | null;
  ifsc_code: string | null;
}

interface TransactionRow extends RowDataPacket {
  id: number;
  user_id: number;
  type: string;
  amount: string;
  description: string | null;
  reference_id: number | null;
  reference_type: string | null;
  balance_before: string | null;
  balance_after: string | null;
  created_at: Date;
}

interface SumRow extends RowDataPacket {
  total: string | null;
}

export default async function WalletPage() {
  const session = await auth();
  if (!session || session.user.userType !== "user") redirect("/login");

  const userId = parseInt(session.user.id);

  const [user, transactions, earnedRows, withdrawnRows] = await Promise.all([
    queryOne<UserRow>(
      `SELECT wallet_balance, upi_id, account_name, account_number, bank_name, ifsc_code
       FROM users WHERE id = ? LIMIT 1`,
      [userId]
    ),
    query<TransactionRow>(
      "SELECT * FROM wallet_transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 50",
      [userId]
    ),
    query<SumRow>(
      "SELECT SUM(amount) AS total FROM wallet_transactions WHERE user_id = ? AND type = 'credit'",
      [userId]
    ),
    query<SumRow>(
      "SELECT SUM(amount) AS total FROM wallet_transactions WHERE user_id = ? AND type = 'debit'",
      [userId]
    ),
  ]);

  const balance = Number(user?.wallet_balance ?? 0);
  const totalEarned = Number(earnedRows[0]?.total ?? 0);
  const totalWithdrawn = Number(withdrawnRows[0]?.total ?? 0);

  const serializedTransactions: WalletTransaction[] = transactions.map((t) => ({
    id: t.id,
    userId: t.user_id,
    type: t.type,
    amount: Number(t.amount),
    description: t.description ?? undefined,
    referenceId: t.reference_id ?? undefined,
    referenceType: t.reference_type ?? undefined,
    balanceBefore: t.balance_before ? Number(t.balance_before) : undefined,
    balanceAfter: t.balance_after ? Number(t.balance_after) : undefined,
    createdAt: new Date(t.created_at).toISOString(),
  }));

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="bg-gradient-to-r from-[#667eea] to-[#764ba2] rounded-2xl p-6 text-white">
        <h1 className="text-2xl font-bold">💰 Wallet</h1>
        <p className="text-white/80 mt-1">Manage your earnings and withdrawals</p>
      </div>

      {/* Wallet balance card */}
      <WalletCard
        balance={balance}
        totalEarned={totalEarned}
        totalWithdrawn={totalWithdrawn}
      />

      {/* Withdrawal form */}
      <WalletPageClient
        balance={balance}
        userUpiId={user?.upi_id ?? undefined}
        userAccountName={user?.account_name ?? undefined}
        userAccountNumber={user?.account_number ?? undefined}
        userBankName={user?.bank_name ?? undefined}
        userIfscCode={user?.ifsc_code ?? undefined}
      />

      {/* Transaction history */}
      <div>
        <h2 className="text-lg font-semibold text-gray-900 mb-3">Transaction History</h2>
        <TransactionHistory transactions={serializedTransactions} />
      </div>
    </div>
  );
}
