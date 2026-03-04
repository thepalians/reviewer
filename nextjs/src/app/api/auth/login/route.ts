import { NextRequest, NextResponse } from "next/server";
import bcrypt from "bcryptjs";
import { queryOne } from "@/lib/db";
import type { RowDataPacket } from "mysql2";

interface UserRow extends RowDataPacket {
  id: number;
  name: string;
  email: string;
  password: string;
  user_type: string;
  status: string;
}

interface SellerRow extends RowDataPacket {
  id: number;
  name: string;
  email: string;
  password: string;
  status: string;
}

export async function POST(request: NextRequest) {
  try {
    const body = await request.json();
    const { login, password, userType } = body;

    if (!login || !password) {
      return NextResponse.json({ error: "Login and password are required" }, { status: 400 });
    }

    if (userType === "seller") {
      const seller = await queryOne<SellerRow>(
        "SELECT id, name, email, password, status FROM sellers WHERE email = ? AND status = 'active' LIMIT 1",
        [login]
      );
      if (!seller) return NextResponse.json({ error: "Invalid credentials" }, { status: 401 });

      const fixedHash = seller.password.replace(/^\$2y\$/, "$2a$");
      const match = await bcrypt.compare(password, fixedHash);
      if (!match) return NextResponse.json({ error: "Invalid credentials" }, { status: 401 });

      return NextResponse.json({
        success: true,
        user: { id: seller.id, name: seller.name, email: seller.email, userType: "seller" },
      });
    }

    const isEmail = login.includes("@");
    const user = await queryOne<UserRow>(
      isEmail
        ? "SELECT id, name, email, password, user_type, status FROM users WHERE email = ? AND status = 'active' LIMIT 1"
        : "SELECT id, name, email, password, user_type, status FROM users WHERE mobile = ? AND status = 'active' LIMIT 1",
      [login]
    );

    if (!user) return NextResponse.json({ error: "Invalid credentials" }, { status: 401 });

    const fixedHash = user.password.replace(/^\$2y\$/, "$2a$");
    const match = await bcrypt.compare(password, fixedHash);
    if (!match) return NextResponse.json({ error: "Invalid credentials" }, { status: 401 });

    if (userType === "admin" && user.user_type !== "admin")
      return NextResponse.json({ error: "Not an admin account" }, { status: 401 });
    if (userType === "user" && user.user_type !== "user")
      return NextResponse.json({ error: "Not a user account" }, { status: 401 });

    return NextResponse.json({
      success: true,
      user: { id: user.id, name: user.name, email: user.email, userType: user.user_type },
    });
  } catch (error: unknown) {
    const msg = error instanceof Error ? error.message : "Unknown error";
    console.error("Login API error:", msg);
    return NextResponse.json(
      { error: "Internal server error", debug: process.env.NODE_ENV === "development" ? msg : undefined },
      { status: 500 }
    );
  }
}
