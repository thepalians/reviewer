import { auth } from "@/lib/auth";
import { redirect } from "next/navigation";
import { prisma } from "@/lib/db";
import WalletCard from "@/components/WalletCard";
import TransactionHistory from "@/components/TransactionHistory";
import WalletPageClient from "./WalletPageClient";
import type { WalletTransaction } from "@/types";
import type { Metadata } from "next";

export const metadata: Metadata = { title: "Wallet" };

export default async function WalletPage() {
  const session = await auth();
  if (!session || session.user.userType !== "user") redirect("/login");

  const userId = parseInt(session.user.id);

  const [wallet, user, transactions, earnedAgg, withdrawnAgg] = await Promise.all([
    prisma.userWallet.findUnique({ where: { userId } }),
    prisma.user.findUnique({
      where: { id: userId },
      select: {
        walletBalance: true,
        upiId: true,
        accountName: true,
        accountNumber: true,
        bankName: true,
        ifscCode: true,
      },
    }),
    prisma.walletTransaction.findMany({
      where: { userId },
      orderBy: { createdAt: "desc" },
      take: 50,
    }),
    prisma.walletTransaction.aggregate({
      where: { userId, type: "credit" },
      _sum: { amount: true },
    }),
    prisma.walletTransaction.aggregate({
      where: { userId, type: "debit" },
      _sum: { amount: true },
    }),
  ]);

  const balance = wallet
    ? Number(wallet.balance)
    : Number(user?.walletBalance ?? 0);
  const totalEarned = Number(earnedAgg._sum.amount ?? 0);
  const totalWithdrawn = Number(withdrawnAgg._sum.amount ?? 0);

  const serializedTransactions: WalletTransaction[] = transactions.map((t) => ({
    ...t,
    amount: Number(t.amount),
    balanceBefore: t.balanceBefore ? Number(t.balanceBefore) : undefined,
    balanceAfter: t.balanceAfter ? Number(t.balanceAfter) : undefined,
    description: t.description ?? undefined,
    referenceId: t.referenceId ?? undefined,
    referenceType: t.referenceType ?? undefined,
    createdAt: t.createdAt.toISOString(),
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
        userUpiId={user?.upiId ?? undefined}
        userAccountName={user?.accountName ?? undefined}
        userAccountNumber={user?.accountNumber ?? undefined}
        userBankName={user?.bankName ?? undefined}
        userIfscCode={user?.ifscCode ?? undefined}
      />

      {/* Transaction history */}
      <div>
        <h2 className="text-lg font-semibold text-gray-900 mb-3">Transaction History</h2>
        <TransactionHistory transactions={serializedTransactions} />
      </div>
    </div>
  );
}
