import { NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { prisma } from "@/lib/db";

export async function GET() {
  const session = await auth();
  if (!session || session.user.userType !== "seller") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  try {
    const platforms = await prisma.socialPlatform.findMany({
      where: { isActive: true },
      select: { id: true, name: true, slug: true, icon: true },
      orderBy: { name: "asc" },
    });

    return NextResponse.json({ success: true, data: platforms });
  } catch (error) {
    console.error("Seller platforms API error:", error);
    return NextResponse.json({ error: "Failed to fetch platforms" }, { status: 500 });
  }
}
