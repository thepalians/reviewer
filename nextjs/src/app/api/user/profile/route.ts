import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { prisma } from "@/lib/db";

export async function GET() {
  const session = await auth();
  if (!session?.user?.id) {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const user = await prisma.user.findUnique({
    where: { id: parseInt(session.user.id) },
    select: {
      id: true,
      name: true,
      email: true,
      mobile: true,
      accountName: true,
      accountNumber: true,
      bankName: true,
      bankAccount: true,
      bankIfsc: true,
      ifscCode: true,
      upiId: true,
      walletBalance: true,
      referralCode: true,
      kycStatus: true,
      userLevel: true,
      preferredLanguage: true,
      createdAt: true,
    },
  });

  if (!user) {
    return NextResponse.json({ error: "User not found" }, { status: 404 });
  }

  return NextResponse.json({ user });
}

export async function PUT(request: NextRequest) {
  const session = await auth();
  if (!session?.user?.id) {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const body = await request.json();
  const { name, email, mobile, accountName, accountNumber, bankName, bankAccount, bankIfsc, upiId } = body;

  try {
    const updated = await prisma.user.update({
      where: { id: parseInt(session.user.id) },
      data: {
        ...(name && { name }),
        ...(email && { email }),
        ...(mobile && { mobile }),
        ...(accountName !== undefined && { accountName }),
        ...(accountNumber !== undefined && { accountNumber }),
        ...(bankName !== undefined && { bankName }),
        ...(bankAccount !== undefined && { bankAccount }),
        ...(bankIfsc !== undefined && { bankIfsc }),
        ...(upiId !== undefined && { upiId }),
      },
      select: {
        id: true,
        name: true,
        email: true,
        mobile: true,
        accountName: true,
        accountNumber: true,
        bankName: true,
        bankAccount: true,
        bankIfsc: true,
        upiId: true,
      },
    });

    return NextResponse.json({ success: true, user: updated });
  } catch (error: unknown) {
    const message = error instanceof Error ? error.message : "Update failed";
    return NextResponse.json({ error: message }, { status: 400 });
  }
}
