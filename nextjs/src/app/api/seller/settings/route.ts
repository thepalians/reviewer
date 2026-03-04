import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { queryOne, execute } from "@/lib/db";
import type { RowDataPacket } from "mysql2";

interface SellerRow extends RowDataPacket {
  id: number;
  name: string;
  email: string;
  created_at: Date;
}

export async function GET() {
  const session = await auth();
  if (!session || session.user.userType !== "seller") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const sellerId = parseInt(session.user.id);

  try {
    const seller = await queryOne<SellerRow>(
      "SELECT id, name, email, created_at FROM sellers WHERE id = ?",
      [sellerId]
    );

    if (!seller) {
      return NextResponse.json({ error: "Seller not found" }, { status: 404 });
    }

    return NextResponse.json({
      success: true,
      data: {
        id: seller.id,
        name: seller.name,
        email: seller.email,
        createdAt: seller.created_at,
      },
    });
  } catch (error) {
    console.error("Seller settings GET error:", error);
    return NextResponse.json({ error: "Failed to fetch profile" }, { status: 500 });
  }
}

export async function PUT(request: NextRequest) {
  const session = await auth();
  if (!session || session.user.userType !== "seller") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const sellerId = parseInt(session.user.id);

  try {
    const body = await request.json();
    const { name, email } = body;

    if (!name && !email) {
      return NextResponse.json({ error: "No fields to update" }, { status: 400 });
    }

    // Check email uniqueness if changing
    if (email) {
      const existing = await queryOne<RowDataPacket & { id: number }>(
        "SELECT id FROM sellers WHERE email = ? AND id != ?",
        [email, sellerId]
      );
      if (existing) {
        return NextResponse.json({ error: "Email already in use" }, { status: 400 });
      }
    }

    const setClauses: string[] = ["updated_at = NOW()"];
    const values: unknown[] = [];

    if (name) {
      setClauses.push("name = ?");
      values.push(name);
    }
    if (email) {
      setClauses.push("email = ?");
      values.push(email);
    }

    values.push(sellerId);

    await execute(
      `UPDATE sellers SET ${setClauses.join(", ")} WHERE id = ?`,
      values
    );

    const updated = await queryOne<RowDataPacket & { id: number; name: string; email: string }>(
      "SELECT id, name, email FROM sellers WHERE id = ?",
      [sellerId]
    );

    return NextResponse.json({
      success: true,
      data: {
        id: updated!.id,
        name: updated!.name,
        email: updated!.email,
      },
    });
  } catch (error) {
    console.error("Seller settings PUT error:", error);
    return NextResponse.json({ error: "Failed to update profile" }, { status: 500 });
  }
}
