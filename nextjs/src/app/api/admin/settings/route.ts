import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { query, execute } from "@/lib/db";
import type { RowDataPacket } from "mysql2";

interface SettingRow extends RowDataPacket {
  id: number;
  key: string;
  value: string;
  created_at: string;
  updated_at: string;
}

interface CountRow extends RowDataPacket {
  count: number;
}

const defaultSettings: Record<string, string> = {
  siteName: "ReviewFlow",
  commissionRate: "10",
  minWithdrawal: "100",
  maintenanceMode: "false",
  emailNotifications: "true",
  smsNotifications: "false",
};

export async function GET() {
  const session = await auth();
  if (!session?.user || session.user.userType !== "admin") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  try {
    const settingRows = await query<SettingRow>(
      "SELECT `key`, `value` FROM system_settings"
    );

    // Build settings object from DB rows, falling back to defaults for missing keys
    const dbSettings: Record<string, string> = {};
    for (const row of settingRows) {
      dbSettings[row.key] = row.value;
    }

    const settings: Record<string, unknown> = { ...defaultSettings };
    for (const [k, v] of Object.entries(dbSettings)) {
      // Parse booleans and numbers stored as strings
      if (v === "true") settings[k] = true;
      else if (v === "false") settings[k] = false;
      else if (!isNaN(Number(v)) && v !== "") settings[k] = Number(v);
      else settings[k] = v;
    }

    // Live stats from DB
    const [totalUsersRows, totalSellersRows] = await Promise.all([
      query<CountRow>("SELECT COUNT(*) AS count FROM users WHERE user_type = 'user'"),
      query<CountRow>("SELECT COUNT(*) AS count FROM sellers"),
    ]);

    return NextResponse.json({
      settings,
      stats: {
        totalUsers: totalUsersRows[0]?.count ?? 0,
        totalSellers: totalSellersRows[0]?.count ?? 0,
      },
    });
  } catch (error) {
    console.error("Admin settings GET error:", error);
    return NextResponse.json({ error: "Internal server error" }, { status: 500 });
  }
}

export async function PUT(request: NextRequest) {
  const session = await auth();
  if (!session?.user || session.user.userType !== "admin") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  try {
    const body = await request.json();

    // Upsert each key-value pair into system_settings
    for (const [key, val] of Object.entries(body)) {
      const valueStr = String(val);
      // Check if key exists
      const existing = await query<SettingRow>(
        "SELECT id FROM system_settings WHERE `key` = ? LIMIT 1",
        [key]
      );

      if (existing.length > 0) {
        await execute(
          "UPDATE system_settings SET `value` = ?, updated_at = NOW() WHERE `key` = ?",
          [valueStr, key]
        );
      } else {
        await execute(
          "INSERT INTO system_settings (`key`, `value`, created_at, updated_at) VALUES (?, ?, NOW(), NOW())",
          [key, valueStr]
        );
      }
    }

    // Return the updated settings
    const settingRows = await query<SettingRow>(
      "SELECT `key`, `value` FROM system_settings"
    );

    const dbSettings: Record<string, string> = {};
    for (const row of settingRows) {
      dbSettings[row.key] = row.value;
    }

    const settings: Record<string, unknown> = { ...defaultSettings };
    for (const [k, v] of Object.entries(dbSettings)) {
      if (v === "true") settings[k] = true;
      else if (v === "false") settings[k] = false;
      else if (!isNaN(Number(v)) && v !== "") settings[k] = Number(v);
      else settings[k] = v;
    }

    return NextResponse.json({ success: true, settings });
  } catch (error) {
    console.error("Admin settings PUT error:", error);
    return NextResponse.json({ error: "Internal server error" }, { status: 500 });
  }
}
