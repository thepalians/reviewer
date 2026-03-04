import { NextRequest, NextResponse } from "next/server";
import bcrypt from "bcryptjs";
import { prisma } from "@/lib/db";
import { registerSchema } from "@/lib/validators";

export async function POST(req: NextRequest) {
  try {
    const body = await req.json();
    const parsed = registerSchema.safeParse(body);

    if (!parsed.success) {
      return NextResponse.json(
        { error: "Invalid input", details: parsed.error.errors },
        { status: 400 }
      );
    }

    const { name, email, mobile, password, referralCode } = parsed.data;

    // Check if email or mobile already exists
    const existing = await prisma.user.findFirst({
      where: { OR: [{ email }, { mobile }] },
    });

    if (existing) {
      if (existing.email === email) {
        return NextResponse.json(
          { error: "Email address is already registered" },
          { status: 409 }
        );
      }
      return NextResponse.json(
        { error: "Mobile number is already registered" },
        { status: 409 }
      );
    }

    // Validate referral code if provided
    let referredById: number | undefined;
    if (referralCode) {
      const referrer = await prisma.user.findUnique({
        where: { referralCode },
        select: { id: true },
      });
      if (!referrer) {
        return NextResponse.json(
          { error: "Invalid referral code" },
          { status: 400 }
        );
      }
      referredById = referrer.id;
    }

    const hashedPassword = await bcrypt.hash(password, 12);

    // Generate unique referral code
    const newReferralCode = `RF${Date.now().toString(36).toUpperCase()}`;

    const user = await prisma.user.create({
      data: {
        name,
        email,
        mobile,
        password: hashedPassword,
        referralCode: newReferralCode,
        referredBy: referredById,
      },
      select: { id: true, name: true, email: true },
    });

    // Create referral record if applicable
    if (referredById) {
      await prisma.referral.create({
        data: {
          referrerId: referredById,
          referredId: user.id,
        },
      });
    }

    return NextResponse.json(
      { success: true, message: "Account created successfully", data: user },
      { status: 201 }
    );
  } catch (error) {
    console.error("Registration error:", error);
    return NextResponse.json(
      { error: "An error occurred during registration" },
      { status: 500 }
    );
  }
}
