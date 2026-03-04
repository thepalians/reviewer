import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { query } from "@/lib/db";
import type { RowDataPacket } from "mysql2";

interface WithdrawalRow extends RowDataPacket {
  id: number;
  user_id: number;
  amount: number;
  method: string;
  upi_id: string | null;
  account_number: string | null;
  ifsc_code: string | null;
  bank_name: string | null;
  status: string;
  created_at: string;
  updated_at: string;
  user_name: string;
  user_email: string;
  user_upi_id: string | null;
  user_bank_account: string | null;
  user_bank_name: string | null;
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
      conditions.push("wr.status = ?");
      params.push(status);
      countParams.push(status);
    }

    const whereClause = conditions.length > 0 ? `WHERE ${conditions.join(" AND ")}` : "";

    const withdrawalsSql = `
      SELECT
        wr.id,
        wr.user_id,
        wr.amount,
        wr.method,
        wr.upi_id,
        wr.account_number,
        wr.ifsc_code,
        wr.bank_name,
        wr.status,
        wr.created_at,
        wr.updated_at,
        u.name AS user_name,
        u.email AS user_email,
        u.upi_id AS user_upi_id,
        u.bank_account AS user_bank_account,
        u.bank_name AS user_bank_name
      FROM withdrawal_requests wr
      LEFT JOIN users u ON u.id = wr.user_id
      ${whereClause}
      ORDER BY wr.created_at DESC
      LIMIT ? OFFSET ?
    `;

    const countSql = `
      SELECT COUNT(*) AS count
      FROM withdrawal_requests wr
      ${whereClause}
    `;

    const [withdrawals, countRows] = await Promise.all([
      query<WithdrawalRow>(withdrawalsSql, [...params, limit, offset]),
      query<CountRow>(countSql, countParams),
    ]);

    const total = countRows[0]?.count ?? 0;

    const data = withdrawals.map((w) => ({
      id: w.id,
      amount: w.amount,
      method: w.method,
      upiId: w.upi_id,
      accountNumber: w.account_number,
      ifscCode: w.ifsc_code,
      bankName: w.bank_name,
      status: w.status,
      createdAt: w.created_at,
      updatedAt: w.updated_at,
      user: {
        id: w.user_id,
        name: w.user_name,
        email: w.user_email,
        upiId: w.user_upi_id,
        accountNumber: w.user_bank_account,
        bankName: w.user_bank_name,
      },
    }));

    return NextResponse.json({ success: true, data, total, page, limit });
  } catch (error) {
    console.error("Admin withdrawals API error:", error);
    return NextResponse.json({ error: "Failed to fetch withdrawals" }, { status: 500 });
  }
}
