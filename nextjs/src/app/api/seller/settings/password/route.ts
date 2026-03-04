import { NextRequest, NextResponse } from "next/server";
import bcrypt from "bcryptjs";
import { auth } from "@/lib/auth";
import { prisma } from "@/lib/db";

export async function PUT(request: NextRequest) {
  const session = await auth();
  if (!session || session.user.userType !== "seller") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const sellerId = parseInt(session.user.id);

  try {
    const body = await request.json();
    const { currentPassword, newPassword } = body;

    if (!currentPassword || !newPassword) {
      return NextResponse.json(
        { error: "Current and new passwords are required" },
        { status: 400 }
      );
    }

    if (newPassword.length < 6) {
      return NextResponse.json(
        { error: "New password must be at least 6 characters" },
        { status: 400 }
      );
    }

    const seller = await prisma.seller.findUnique({
      where: { id: sellerId },
      select: { password: true },
    });

    if (!seller) {
      return NextResponse.json({ error: "Seller not found" }, { status: 404 });
    }

    const fixedHash = seller.password.replace(/^\$2y\$/, "$2a$");
    const isValid = await bcrypt.compare(currentPassword, fixedHash);
    if (!isValid) {
      return NextResponse.json({ error: "Current password is incorrect" }, { status: 400 });
    }

    const hashedPassword = await bcrypt.hash(newPassword, 12);
    await prisma.seller.update({
      where: { id: sellerId },
      data: { password: hashedPassword },
    });

    return NextResponse.json({ success: true, message: "Password updated successfully" });
  } catch (error) {
    console.error("Seller password change error:", error);
    return NextResponse.json({ error: "Failed to update password" }, { status: 500 });
  }
}
