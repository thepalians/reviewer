import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { prisma } from "@/lib/db";

export async function GET(_req: NextRequest) {
  const session = await auth();

  if (!session || session.user.userType !== "user") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const userId = parseInt(session.user.id);

  try {
    const [wallet, transactions, earnedAgg, withdrawnAgg] = await Promise.all([
      prisma.userWallet.findUnique({ where: { userId } }),
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

    // Fallback to user's walletBalance if UserWallet record doesn't exist
    const user = wallet
      ? null
      : await prisma.user.findUnique({
          where: { id: userId },
          select: { walletBalance: true },
        });

    const balance = wallet
      ? Number(wallet.balance)
      : Number(user?.walletBalance ?? 0);

    return NextResponse.json({
      success: true,
      data: {
        balance,
        totalEarned: Number(earnedAgg._sum.amount ?? 0),
        totalWithdrawn: Number(withdrawnAgg._sum.amount ?? 0),
        transactions: transactions.map((t) => ({
          ...t,
          amount: Number(t.amount),
          balanceBefore: t.balanceBefore ? Number(t.balanceBefore) : null,
          balanceAfter: t.balanceAfter ? Number(t.balanceAfter) : null,
          createdAt: t.createdAt.toISOString(),
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
