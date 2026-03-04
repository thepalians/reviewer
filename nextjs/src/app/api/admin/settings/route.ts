import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { prisma } from "@/lib/db";

// In-memory settings store (replace with DB table as needed)
const defaultSettings = {
  siteName: "ReviewFlow",
  commissionRate: 10,
  minWithdrawal: 100,
  maintenanceMode: false,
  emailNotifications: true,
  smsNotifications: false,
};

// eslint-disable-next-line @typescript-eslint/no-explicit-any
let currentSettings: Record<string, any> = { ...defaultSettings };

export async function GET() {
  const session = await auth();
  if (!session?.user || session.user.userType !== "admin") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  // Include some live stats from DB
  const [totalUsers, totalSellers] = await Promise.all([
    prisma.user.count({ where: { userType: "user" } }),
    prisma.seller.count(),
  ]);

  return NextResponse.json({
    settings: currentSettings,
    stats: { totalUsers, totalSellers },
  });
}

export async function PUT(request: NextRequest) {
  const session = await auth();
  if (!session?.user || session.user.userType !== "admin") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const body = await request.json();
  currentSettings = { ...currentSettings, ...body };

  return NextResponse.json({ success: true, settings: currentSettings });
}
