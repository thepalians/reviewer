import { NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { query } from "@/lib/db";
import type { RowDataPacket } from "mysql2";

interface PlatformRow extends RowDataPacket {
  id: number;
  name: string;
  icon: string | null;
}

export async function GET() {
  const session = await auth();
  if (!session || session.user.userType !== "seller") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  try {
    const platforms = await query<PlatformRow>(
      "SELECT id, name, icon FROM social_platforms ORDER BY name ASC"
    );

    return NextResponse.json({
      success: true,
      data: platforms.map((p) => ({
        id: p.id,
        name: p.name,
        icon: p.icon,
      })),
    });
  } catch (error) {
    console.error("Seller platforms API error:", error);
    return NextResponse.json({ error: "Failed to fetch platforms" }, { status: 500 });
  }
}
