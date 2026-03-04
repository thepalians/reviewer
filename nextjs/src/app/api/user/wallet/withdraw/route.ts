import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { prisma } from "@/lib/db";
import { z } from "zod";

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
    // Check balance (use user's walletBalance as source of truth)
    const user = await prisma.user.findUnique({
      where: { id: userId },
      select: { walletBalance: true },
    });

    if (!user || Number(user.walletBalance) < amount) {
      return NextResponse.json(
        { error: "Insufficient wallet balance" },
        { status: 400 }
      );
    }

    // Record a pending withdrawal transaction
    const description =
      paymentMethod === "upi"
        ? `Withdrawal request via UPI (${upiId ?? ""})`
        : `Withdrawal request via Bank Transfer (${accountNumber ?? ""})`;

    const roundedAmount = Math.round(amount * 100) / 100;

    await prisma.$transaction(async (tx) => {
      await tx.walletTransaction.create({
        data: {
          userId,
          type: "debit",
          amount: roundedAmount,
          description,
          referenceType: "withdrawal_request",
          balanceBefore: user.walletBalance,
          balanceAfter: Number(user.walletBalance) - roundedAmount,
        },
      });

      // Deduct from user wallet balance
      await tx.user.update({
        where: { id: userId },
        data: { walletBalance: { decrement: roundedAmount } },
      });

      // Update UserWallet if exists
      await tx.userWallet.updateMany({
        where: { userId },
        data: { balance: { decrement: roundedAmount } },
      });
    });

    // Persist payment details
    if (paymentMethod === "upi" && upiId) {
      await prisma.user.update({ where: { id: userId }, data: { upiId } });
    } else if (paymentMethod === "bank" && accountNumber) {
      await prisma.user.update({
        where: { id: userId },
        data: { accountName, accountNumber, bankName, ifscCode },
      });
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
