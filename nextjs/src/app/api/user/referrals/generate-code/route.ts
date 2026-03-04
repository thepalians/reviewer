import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { prisma } from "@/lib/db";

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
    const user = await prisma.user.findUnique({
      where: { id: userId },
      select: { referralCode: true },
    });

    if (user?.referralCode) {
      return NextResponse.json({
        success: true,
        data: { referralCode: user.referralCode },
      });
    }

    // Generate a unique code
    let code = generateCode();
    let attempts = 0;
    while (attempts < 10) {
      const existing = await prisma.user.findUnique({ where: { referralCode: code } });
      if (!existing) break;
      code = generateCode();
      attempts++;
    }

    if (attempts >= 10) {
      return NextResponse.json({ error: "Failed to generate a unique code. Please try again." }, { status: 500 });
    }

    const updated = await prisma.user.update({
      where: { id: userId },
      data: { referralCode: code },
      select: { referralCode: true },
    });

    return NextResponse.json({
      success: true,
      data: { referralCode: updated.referralCode },
    });
  } catch (error) {
    console.error("Generate referral code error:", error);
    return NextResponse.json({ error: "Internal server error" }, { status: 500 });
  }
}
