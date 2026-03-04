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
    const [user, referrals] = await Promise.all([
      prisma.user.findUnique({
        where: { id: userId },
        select: { referralCode: true },
      }),
      prisma.referral.findMany({
        where: { referrerId: userId },
        include: {
          referred: {
            select: { id: true, name: true, email: true, createdAt: true, status: true },
          },
        },
        orderBy: { createdAt: "desc" },
      }),
    ]);

    const totalRewards = referrals
      .filter((r) => r.rewardPaid)
      .reduce((sum, r) => sum + Number(r.rewardAmount ?? 0), 0);

    return NextResponse.json({
      success: true,
      data: {
        referralCode: user?.referralCode ?? null,
        totalReferred: referrals.length,
        totalRewards,
        referrals: referrals.map((r) => ({
          id: r.id,
          rewardPaid: r.rewardPaid,
          rewardAmount: r.rewardAmount ? Number(r.rewardAmount) : null,
          createdAt: r.createdAt.toISOString(),
          referred: {
            ...r.referred,
            createdAt: r.referred.createdAt.toISOString(),
          },
        })),
      },
    });
  } catch (error) {
    console.error("Referrals GET error:", error);
    return NextResponse.json({ error: "Internal server error" }, { status: 500 });
  }
}
