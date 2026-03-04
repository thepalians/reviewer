import { NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { prisma } from "@/lib/db";

export async function GET() {
  const session = await auth();
  if (!session || session.user.userType !== "seller") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const sellerId = parseInt(session.user.id);

  try {
    const [wallet, transactions] = await Promise.all([
      prisma.sellerWallet.findUnique({
        where: { sellerId },
        select: { balance: true, updatedAt: true },
      }),
      prisma.sellerWalletTransaction.findMany({
        where: { wallet: { sellerId } },
        orderBy: { createdAt: "desc" },
        take: 50,
      }),
    ]);

    return NextResponse.json({
      success: true,
      data: {
        balance: Number(wallet?.balance ?? 0),
        updatedAt: wallet?.updatedAt,
        transactions,
      },
    });
  } catch (error) {
    console.error("Seller wallet API error:", error);
    return NextResponse.json({ error: "Failed to fetch wallet data" }, { status: 500 });
  }
}
