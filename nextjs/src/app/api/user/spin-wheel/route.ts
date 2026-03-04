import { NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { prisma } from "@/lib/db";

const WHEEL_SEGMENTS = [
  { label: "₹5", type: "cash", value: 5 },
  { label: "5 Points", type: "points", value: 5 },
  { label: "₹10", type: "cash", value: 10 },
  { label: "Better Luck", type: "none", value: 0 },
  { label: "₹20", type: "cash", value: 20 },
  { label: "10 Points", type: "points", value: 10 },
  { label: "₹50", type: "cash", value: 50 },
  { label: "₹100", type: "cash", value: 100 },
];

export async function POST() {
  const session = await auth();

  if (!session || session.user.userType !== "user") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const userId = parseInt(session.user.id);

  try {
    // Check last spin — allow only 1 per day
    const todayStart = new Date();
    todayStart.setHours(0, 0, 0, 0);

    const lastSpin = await prisma.userPoint.findFirst({
      where: { userId, type: "spin_wheel", createdAt: { gte: todayStart } },
      orderBy: { createdAt: "desc" },
    });

    // Also check wallet transactions for cash rewards today
    const lastCashSpin = await prisma.walletTransaction.findFirst({
      where: {
        userId,
        referenceType: "spin_wheel",
        createdAt: { gte: todayStart },
      },
      orderBy: { createdAt: "desc" },
    });

    if (lastSpin || lastCashSpin) {
      return NextResponse.json(
        { error: "You have already used your daily spin. Come back tomorrow!" },
        { status: 429 }
      );
    }

    // Pick a random segment
    const segmentIndex = Math.floor(Math.random() * WHEEL_SEGMENTS.length);
    const reward = WHEEL_SEGMENTS[segmentIndex];

    if (reward.type === "points" && reward.value > 0) {
      await prisma.userPoint.create({
        data: {
          userId,
          points: reward.value,
          type: "spin_wheel",
          description: `Spin Wheel reward: ${reward.label}`,
        },
      });
    } else if (reward.type === "cash" && reward.value > 0) {
      // Add to wallet
      await prisma.$transaction(async (tx) => {
        await tx.user.update({
          where: { id: userId },
          data: { walletBalance: { increment: reward.value } },
        });
        await tx.walletTransaction.create({
          data: {
            userId,
            type: "credit",
            amount: reward.value,
            description: `Spin Wheel reward: ${reward.label}`,
            referenceType: "spin_wheel",
          },
        });
      });
    } else {
      // "Better Luck" — record a spin so they can't spin again today
      await prisma.userPoint.create({
        data: {
          userId,
          points: 0,
          type: "spin_wheel",
          description: "Spin Wheel: Better Luck Next Time",
        },
      });
    }

    return NextResponse.json({
      success: true,
      data: {
        segmentIndex,
        reward,
      },
    });
  } catch (error) {
    console.error("Spin wheel API error:", error);
    return NextResponse.json(
      { error: "Failed to process spin" },
      { status: 500 }
    );
  }
}
