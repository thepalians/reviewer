import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { queryOne, execute } from "@/lib/db";
import type { RowDataPacket } from "mysql2";
import bcrypt from "bcryptjs";

interface UserRow extends RowDataPacket {
  id: number;
  password: string;
}

export async function PUT(request: NextRequest) {
  const session = await auth();
  if (!session?.user?.id) {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const body = await request.json();
  const { currentPassword, newPassword } = body;

  if (!currentPassword || !newPassword) {
    return NextResponse.json(
      { error: "Current and new password are required" },
      { status: 400 }
    );
  }

  if (newPassword.length < 8) {
    return NextResponse.json(
      { error: "New password must be at least 8 characters" },
      { status: 400 }
    );
  }

  const user = await queryOne<UserRow>(
    "SELECT id, password FROM users WHERE id = ?",
    [parseInt(session.user.id)]
  );

  if (!user) {
    return NextResponse.json({ error: "User not found" }, { status: 404 });
  }

  const fixedHash = user.password.replace(/^\$2y\$/, "$2a$");
  const match = await bcrypt.compare(currentPassword, fixedHash);
  if (!match) {
    return NextResponse.json(
      { error: "Current password is incorrect" },
      { status: 400 }
    );
  }

  const hashedPassword = await bcrypt.hash(newPassword, 12);
  await execute(
    "UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?",
    [hashedPassword, user.id]
  );

  return NextResponse.json({ success: true, message: "Password updated successfully" });
}
