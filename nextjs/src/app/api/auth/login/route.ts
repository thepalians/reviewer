import { NextRequest, NextResponse } from "next/server";
import bcrypt from "bcryptjs";
import { PrismaClient } from "@prisma/client";

// Use a fresh PrismaClient to avoid any caching/import issues
const prisma = new PrismaClient();

export async function POST(request: NextRequest) {
  try {
    const body = await request.json();
    const { login, password, userType } = body;

    if (!login || !password) {
      return NextResponse.json({ error: "Login and password are required" }, { status: 400 });
    }

    if (userType === "seller") {
      const seller = await prisma.seller.findFirst({
        where: { email: login, status: "active" },
      });
      if (!seller) {
        return NextResponse.json({ error: "Invalid credentials" }, { status: 401 });
      }
      const fixedHash = seller.password.replace(/^\$2y\$/, "$2a$");
      const match = await bcrypt.compare(password, fixedHash);
      if (!match) {
        return NextResponse.json({ error: "Invalid credentials" }, { status: 401 });
      }
      return NextResponse.json({
        success: true,
        user: { id: seller.id, name: seller.name, email: seller.email, userType: "seller" },
      });
    }

    // User or Admin login
    const isEmail = login.includes("@");
    const user = await prisma.user.findFirst({
      where: isEmail
        ? { email: login, status: "active" }
        : { mobile: login, status: "active" },
    });

    if (!user) {
      return NextResponse.json({ error: "Invalid credentials" }, { status: 401 });
    }

    const fixedHash = user.password.replace(/^\$2y\$/, "$2a$");
    const match = await bcrypt.compare(password, fixedHash);
    if (!match) {
      return NextResponse.json({ error: "Invalid credentials" }, { status: 401 });
    }

    if (userType === "admin" && user.userType !== "admin") {
      return NextResponse.json({ error: "Not an admin account" }, { status: 401 });
    }
    if (userType === "user" && user.userType !== "user") {
      return NextResponse.json({ error: "Not a user account" }, { status: 401 });
    }

    return NextResponse.json({
      success: true,
      user: { id: user.id, name: user.name, email: user.email, userType: user.userType },
    });
  } catch (error: unknown) {
    const errorMessage = error instanceof Error ? error.message : "Unknown error";
    const errorStack = error instanceof Error ? error.stack : "";
    console.error("Login API error:", errorMessage);
    console.error("Login API stack:", errorStack);
    return NextResponse.json({
      error: "Internal server error",
      debug: process.env.NODE_ENV === "development" ? errorMessage : undefined,
    }, { status: 500 });
  }
}
