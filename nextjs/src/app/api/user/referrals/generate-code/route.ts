import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { queryOne, execute } from "@/lib/db";
import type { RowDataPacket } from "mysql2";

interface UserRow extends RowDataPacket {
  referral_code: string | null;
}

function generateCode(length = 8): string {
  const chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
  let result = "";
  for (let i = 0; i < length; i++) {
    result += chars.charAt(Math.floor(Math.random() * chars.length));
  }
  return result;
}

export async function POST(_req: NextRequest) {
  const session = await auth();
  if (!session || session.user.userType !== "user") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const userId = parseInt(session.user.id);

  try {
    const user = await queryOne<UserRow>(
      "SELECT referral_code FROM users WHERE id = ?",
      [userId]
    );

    if (user?.referral_code) {
      return NextResponse.json({
        success: true,
        data: { referralCode: user.referral_code },
      });
    }

    // Generate a unique code
    let code = generateCode();
    let attempts = 0;
    while (attempts < 10) {
      const existing = await queryOne<UserRow>(
        "SELECT referral_code FROM users WHERE referral_code = ?",
        [code]
      );
      if (!existing) break;
      code = generateCode();
      attempts++;
    }

    if (attempts >= 10) {
      return NextResponse.json(
        { error: "Failed to generate a unique code. Please try again." },
        { status: 500 }
      );
    }

    await execute(
      "UPDATE users SET referral_code = ?, updated_at = NOW() WHERE id = ?",
      [code, userId]
    );

    return NextResponse.json({
      success: true,
      data: { referralCode: code },
    });
  } catch (error) {
    console.error("Generate referral code error:", error);
    return NextResponse.json({ error: "Internal server error" }, { status: 500 });
  }
}
