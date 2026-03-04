import { NextRequest, NextResponse } from "next/server";
import bcrypt from "bcryptjs";
import { queryOne, execute } from "@/lib/db";
import { registerSchema } from "@/lib/validators";
import type { RowDataPacket } from "mysql2";

interface UserRow extends RowDataPacket {
  id: number;
  email: string;
  mobile: string;
}

interface ReferrerRow extends RowDataPacket {
  id: number;
}

export async function POST(req: NextRequest) {
  try {
    const body = await req.json();
    const parsed = registerSchema.safeParse(body);

    if (!parsed.success) {
      return NextResponse.json({ error: "Invalid input", details: parsed.error.errors }, { status: 400 });
    }

    const { name, email, mobile, password, referralCode } = parsed.data;

    // Check duplicates
    const existing = await queryOne<UserRow>(
      "SELECT id, email, mobile FROM users WHERE email = ? OR mobile = ? LIMIT 1",
      [email, mobile]
    );

    if (existing) {
      if (existing.email === email)
        return NextResponse.json({ error: "Email address is already registered" }, { status: 409 });
      return NextResponse.json({ error: "Mobile number is already registered" }, { status: 409 });
    }

    // Validate referral code
    let referredById: number | null = null;
    if (referralCode) {
      const referrer = await queryOne<ReferrerRow>(
        "SELECT id FROM users WHERE referral_code = ? LIMIT 1",
        [referralCode]
      );
      if (!referrer)
        return NextResponse.json({ error: "Invalid referral code" }, { status: 400 });
      referredById = referrer.id;
    }

    const hashedPassword = await bcrypt.hash(password, 12);
    const newReferralCode = `RF${Date.now().toString(36).toUpperCase()}`;

    const result = await execute(
      "INSERT INTO users (name, email, mobile, password, referral_code, referred_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())",
      [name, email, mobile, hashedPassword, newReferralCode, referredById]
    );

    const userId = result.insertId;

    if (referredById) {
      await execute(
        "INSERT INTO referrals (referrer_id, referred_id, created_at) VALUES (?, ?, NOW())",
        [referredById, userId]
      );
    }

    return NextResponse.json(
      { success: true, message: "Account created successfully", data: { id: userId, name, email } },
      { status: 201 }
    );
  } catch (error) {
    console.error("Registration error:", error);
    return NextResponse.json({ error: "An error occurred during registration" }, { status: 500 });
  }
}
