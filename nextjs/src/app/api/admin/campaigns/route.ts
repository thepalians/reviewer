import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { query } from "@/lib/db";
import type { RowDataPacket } from "mysql2";

interface CampaignRow extends RowDataPacket {
  id: number;
  title: string;
  description: string | null;
  platform_id: number;
  reward_amount: number;
  status: string;
  admin_approved: number;
  seller_id: number;
  created_at: string;
  updated_at: string;
  seller_name: string;
  seller_email: string;
  platform_name: string;
}

interface CountRow extends RowDataPacket {
  count: number;
}

export async function GET(request: NextRequest) {
  const session = await auth();
  if (!session || session.user.userType !== "admin") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const { searchParams } = new URL(request.url);
  const status = searchParams.get("status") || "";
  const page = parseInt(searchParams.get("page") || "1");
  const limit = parseInt(searchParams.get("limit") || "20");
  const offset = (page - 1) * limit;

  try {
    const conditions: string[] = [];
    const params: unknown[] = [];
    const countParams: unknown[] = [];

    if (status) {
      conditions.push("sc.status = ?");
      params.push(status);
      countParams.push(status);
    }

    const whereClause = conditions.length > 0 ? `WHERE ${conditions.join(" AND ")}` : "";

    const campaignsSql = `
      SELECT
        sc.id,
        sc.title,
        sc.description,
        sc.platform_id,
        sc.reward_amount,
        sc.status,
        sc.admin_approved,
        sc.seller_id,
        sc.created_at,
        sc.updated_at,
        s.name AS seller_name,
        s.email AS seller_email,
        sp.name AS platform_name
      FROM social_campaigns sc
      LEFT JOIN sellers s ON s.id = sc.seller_id
      LEFT JOIN social_platforms sp ON sp.id = sc.platform_id
      ${whereClause}
      ORDER BY sc.created_at DESC
      LIMIT ? OFFSET ?
    `;

    const countSql = `
      SELECT COUNT(*) AS count
      FROM social_campaigns sc
      ${whereClause}
    `;

    const [campaigns, countRows] = await Promise.all([
      query<CampaignRow>(campaignsSql, [...params, limit, offset]),
      query<CountRow>(countSql, countParams),
    ]);

    const total = countRows[0]?.count ?? 0;

    const data = campaigns.map((c) => ({
      id: c.id,
      title: c.title,
      description: c.description,
      rewardAmount: c.reward_amount,
      status: c.status,
      adminApproved: c.admin_approved === 1,
      createdAt: c.created_at,
      updatedAt: c.updated_at,
      seller: { id: c.seller_id, name: c.seller_name, email: c.seller_email },
      platform: { id: c.platform_id, name: c.platform_name },
    }));

    return NextResponse.json({ success: true, data, total, page, limit });
  } catch (error) {
    console.error("Admin campaigns API error:", error);
    return NextResponse.json({ error: "Failed to fetch campaigns" }, { status: 500 });
  }
}
