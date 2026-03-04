import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { prisma } from "@/lib/db";

export async function GET() {
  const session = await auth();
  if (!session || session.user.userType !== "seller") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const sellerId = parseInt(session.user.id);

  try {
    const seller = await prisma.seller.findUnique({
      where: { id: sellerId },
      select: { id: true, name: true, email: true, createdAt: true },
    });

    if (!seller) {
      return NextResponse.json({ error: "Seller not found" }, { status: 404 });
    }

    return NextResponse.json({ success: true, data: seller });
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
      const existing = await prisma.seller.findFirst({
        where: { email, id: { not: sellerId } },
      });
      if (existing) {
        return NextResponse.json({ error: "Email already in use" }, { status: 400 });
      }
    }

    const updated = await prisma.seller.update({
      where: { id: sellerId },
      data: {
        ...(name && { name }),
        ...(email && { email }),
      },
      select: { id: true, name: true, email: true },
    });

    return NextResponse.json({ success: true, data: updated });
  } catch (error) {
    console.error("Seller settings PUT error:", error);
    return NextResponse.json({ error: "Failed to update profile" }, { status: 500 });
  }
}
