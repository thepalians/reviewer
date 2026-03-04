import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { query, execute } from "@/lib/db";
import type { RowDataPacket } from "mysql2";

interface CampaignRow extends RowDataPacket {
  id: number;
  title: string;
  status: string;
  reward_amount: string | number;
  required_time: number | null;
  admin_approved: number | boolean;
  created_at: Date;
  platform_id: number;
  platform_name: string;
  completions_count: number;
}

interface CountRow extends RowDataPacket {
  total: number;
}

export async function GET(request: NextRequest) {
  const session = await auth();
  if (!session || session.user.userType !== "seller") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const sellerId = parseInt(session.user.id);
  const { searchParams } = new URL(request.url);
  const status = searchParams.get("status") || "";
  const platformId = searchParams.get("platformId") || "";
  const page = parseInt(searchParams.get("page") || "1");
  const limit = parseInt(searchParams.get("limit") || "20");
  const offset = (page - 1) * limit;

  try {
    const conditions: string[] = ["sc.seller_id = ?"];
    const params: unknown[] = [sellerId];
    const countParams: unknown[] = [sellerId];

    if (status) {
      conditions.push("sc.status = ?");
      params.push(status);
      countParams.push(status);
    }
    if (platformId) {
      conditions.push("sc.platform_id = ?");
      params.push(parseInt(platformId));
      countParams.push(parseInt(platformId));
    }

    const whereClause = conditions.join(" AND ");

    const [campaigns, countRows] = await Promise.all([
      query<CampaignRow>(
        `SELECT
           sc.id,
           sc.title,
           sc.status,
           sc.reward_amount,
           sc.required_time,
           sc.admin_approved,
           sc.created_at,
           sp.id AS platform_id,
           sp.name AS platform_name,
           COUNT(stc.id) AS completions_count
         FROM social_campaigns sc
         LEFT JOIN social_platforms sp ON sp.id = sc.platform_id
         LEFT JOIN social_task_completions stc ON stc.campaign_id = sc.id
         WHERE ${whereClause}
         GROUP BY sc.id, sc.title, sc.status, sc.reward_amount, sc.required_time,
                  sc.admin_approved, sc.created_at, sp.id, sp.name
         ORDER BY sc.created_at DESC
         LIMIT ? OFFSET ?`,
        [...params, limit, offset]
      ),
      query<CountRow>(
        `SELECT COUNT(DISTINCT sc.id) AS total
         FROM social_campaigns sc
         WHERE ${whereClause}`,
        countParams
      ),
    ]);

    const total = Number(countRows[0]?.total ?? 0);

    return NextResponse.json({
      success: true,
      data: campaigns.map((c) => ({
        id: c.id,
        title: c.title,
        status: c.status,
        rewardAmount: Number(c.reward_amount),
        requiredTime: c.required_time,
        adminApproved: Boolean(c.admin_approved),
        createdAt: c.created_at,
        platform: { id: c.platform_id, name: c.platform_name },
        _count: { completions: Number(c.completions_count) },
      })),
      total,
      page,
      limit,
    });
  } catch (error) {
    console.error("Seller campaigns API error:", error);
    return NextResponse.json({ error: "Failed to fetch campaigns" }, { status: 500 });
  }
}

export async function POST(request: NextRequest) {
  const session = await auth();
  if (!session || session.user.userType !== "seller") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const sellerId = parseInt(session.user.id);

  try {
    const body = await request.json();
    const { title, description, platformId, url, rewardAmount, requiredTime } = body;

    if (!title || !platformId || !rewardAmount) {
      return NextResponse.json(
        { error: "Title, platform, and reward amount are required" },
        { status: 400 }
      );
    }

    const result = await execute(
      `INSERT INTO social_campaigns
         (seller_id, title, description, platform_id, url, reward_amount, required_time, status, admin_approved, created_at, updated_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 0, NOW(), NOW())`,
      [
        sellerId,
        title,
        description || null,
        parseInt(platformId),
        url || null,
        parseFloat(rewardAmount),
        requiredTime ? parseInt(requiredTime) : null,
      ]
    );

    const newId = result.insertId;

    const campaign = await query<RowDataPacket & {
      id: number;
      title: string;
      status: string;
      reward_amount: string | number;
      created_at: Date;
      platform_name: string;
    }>(
      `SELECT sc.id, sc.title, sc.status, sc.reward_amount, sc.created_at, sp.name AS platform_name
       FROM social_campaigns sc
       LEFT JOIN social_platforms sp ON sp.id = sc.platform_id
       WHERE sc.id = ?`,
      [newId]
    );

    const row = campaign[0];

    return NextResponse.json(
      {
        success: true,
        data: {
          id: row.id,
          title: row.title,
          status: row.status,
          rewardAmount: Number(row.reward_amount),
          createdAt: row.created_at,
          platform: { name: row.platform_name },
        },
      },
      { status: 201 }
    );
  } catch (error) {
    console.error("Create campaign API error:", error);
    return NextResponse.json({ error: "Failed to create campaign" }, { status: 500 });
  }
}
