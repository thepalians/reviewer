import { NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { queryOne, execute, transaction } from "@/lib/db";
import type { RowDataPacket } from "mysql2";

interface SpinCheckRow extends RowDataPacket {
  id: number;
}

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
    const todayStartStr = todayStart.toISOString().slice(0, 19).replace("T", " ");

    const lastSpin = await queryOne<SpinCheckRow>(
      "SELECT id FROM user_points WHERE user_id = ? AND type = 'spin_wheel' AND created_at >= ?",
      [userId, todayStartStr]
    );

    // Also check wallet transactions for cash rewards today
    const lastCashSpin = await queryOne<SpinCheckRow>(
      "SELECT id FROM wallet_transactions WHERE user_id = ? AND description LIKE 'Spin Wheel%' AND created_at >= ?",
      [userId, todayStartStr]
    );

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
      await execute(
        `INSERT INTO user_points (user_id, points, type, description, created_at)
         VALUES (?, ?, 'spin_wheel', ?, NOW())`,
        [userId, reward.value, `Spin Wheel reward: ${reward.label}`]
      );
    } else if (reward.type === "cash" && reward.value > 0) {
      await transaction(async (conn) => {
        await conn.execute(
          "UPDATE users SET wallet_balance = wallet_balance + ?, updated_at = NOW() WHERE id = ?",
          [reward.value, userId]
        );
        await conn.execute(
          `INSERT INTO wallet_transactions (user_id, type, amount, description, created_at)
           VALUES (?, 'credit', ?, ?, NOW())`,
          [userId, reward.value, `Spin Wheel reward: ${reward.label}`]
        );
      });
    } else {
      // "Better Luck" — record a spin so they can't spin again today
      await execute(
        `INSERT INTO user_points (user_id, points, type, description, created_at)
         VALUES (?, 0, 'spin_wheel', 'Spin Wheel: Better Luck Next Time', NOW())`,
        [userId]
      );
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
