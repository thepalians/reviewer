import { NextRequest, NextResponse } from "next/server";
import bcrypt from "bcryptjs";
import { auth } from "@/lib/auth";
import { queryOne, execute } from "@/lib/db";
import type { RowDataPacket } from "mysql2";

interface SellerPasswordRow extends RowDataPacket {
  password: string;
}

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

    const seller = await queryOne<SellerPasswordRow>(
      "SELECT password FROM sellers WHERE id = ?",
      [sellerId]
    );

    if (!seller) {
      return NextResponse.json({ error: "Seller not found" }, { status: 404 });
    }

    const fixedHash = seller.password.replace(/^\$2y\$/, "$2a$");
    const isValid = await bcrypt.compare(currentPassword, fixedHash);
    if (!isValid) {
      return NextResponse.json({ error: "Current password is incorrect" }, { status: 400 });
    }

    const hashedPassword = await bcrypt.hash(newPassword, 12);

    await execute(
      "UPDATE sellers SET password = ?, updated_at = NOW() WHERE id = ?",
      [hashedPassword, sellerId]
    );

    return NextResponse.json({ success: true, message: "Password updated successfully" });
  } catch (error) {
    console.error("Seller password change error:", error);
    return NextResponse.json({ error: "Failed to update password" }, { status: 500 });
  }
}
