import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { query } from "@/lib/db";
import type { RowDataPacket } from "mysql2";

interface UserRow extends RowDataPacket {
  id: number;
  name: string;
  email: string;
  mobile: string;
  status: string;
  wallet_balance: number;
  created_at: string;
  task_count: number;
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
  const search = searchParams.get("search") || "";
  const status = searchParams.get("status") || "";
  const page = parseInt(searchParams.get("page") || "1");
  const limit = parseInt(searchParams.get("limit") || "20");
  const offset = (page - 1) * limit;

  try {
    const conditions: string[] = ["u.user_type = 'user'"];
    const params: unknown[] = [];
    const countParams: unknown[] = [];

    if (search) {
      conditions.push("(u.name LIKE ? OR u.email LIKE ? OR u.mobile LIKE ?)");
      const likeVal = `%${search}%`;
      params.push(likeVal, likeVal, likeVal);
      countParams.push(likeVal, likeVal, likeVal);
    }

    if (status) {
      conditions.push("u.status = ?");
      params.push(status);
      countParams.push(status);
    }

    const whereClause = conditions.length > 0 ? `WHERE ${conditions.join(" AND ")}` : "";

    const usersSql = `
      SELECT
        u.id,
        u.name,
        u.email,
        u.mobile,
        u.status,
        u.wallet_balance,
        u.created_at,
        COUNT(t.id) AS task_count
      FROM users u
      LEFT JOIN tasks t ON t.user_id = u.id
      ${whereClause}
      GROUP BY u.id
      ORDER BY u.created_at DESC
      LIMIT ? OFFSET ?
    `;

    const countSql = `
      SELECT COUNT(*) AS count
      FROM users u
      ${whereClause}
    `;

    const [users, countRows] = await Promise.all([
      query<UserRow>(usersSql, [...params, limit, offset]),
      query<CountRow>(countSql, countParams),
    ]);

    const total = countRows[0]?.count ?? 0;

    const data = users.map((u) => ({
      id: u.id,
      name: u.name,
      email: u.email,
      mobile: u.mobile,
      status: u.status,
      walletBalance: u.wallet_balance,
      createdAt: u.created_at,
      _count: { tasks: u.task_count },
    }));

    return NextResponse.json({ success: true, data, total, page, limit });
  } catch (error) {
    console.error("Admin users API error:", error);
    return NextResponse.json({ error: "Failed to fetch users" }, { status: 500 });
  }
}
