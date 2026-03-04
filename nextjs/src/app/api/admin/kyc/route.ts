import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { query } from "@/lib/db";
import type { RowDataPacket } from "mysql2";

interface KycRow extends RowDataPacket {
  id: number;
  user_id: number;
  full_name: string;
  dob: string | null;
  aadhaar_number: string | null;
  aadhaar_file: string | null;
  pan_number: string | null;
  pan_file: string | null;
  status: string;
  rejection_reason: string | null;
  verified_at: string | null;
  verified_by: number | null;
  created_at: string;
  updated_at: string;
  user_name: string;
  user_email: string;
}

interface CountRow extends RowDataPacket {
  count: number;
}

export async function GET(request: NextRequest) {
  const session = await auth();
  if (!session || session.user.userType !== "admin") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  try {
    const { searchParams } = new URL(request.url);
    const statusFilter = searchParams.get("status") || "";
    const page = parseInt(searchParams.get("page") || "1");
    const limit = parseInt(searchParams.get("limit") || "20");
    const offset = (page - 1) * limit;

    const conditions: string[] = [];
    const params: unknown[] = [];
    const countParams: unknown[] = [];

    if (statusFilter) {
      conditions.push("k.status = ?");
      params.push(statusFilter);
      countParams.push(statusFilter);
    }

    const whereClause = conditions.length > 0 ? `WHERE ${conditions.join(" AND ")}` : "";

    const kycSql = `
      SELECT
        k.id,
        k.user_id,
        k.full_name,
        k.dob,
        k.aadhaar_number,
        k.aadhaar_file,
        k.pan_number,
        k.pan_file,
        k.status,
        k.rejection_reason,
        k.verified_at,
        k.verified_by,
        k.created_at,
        k.updated_at,
        u.name AS user_name,
        u.email AS user_email
      FROM kyc_documents k
      LEFT JOIN users u ON u.id = k.user_id
      ${whereClause}
      ORDER BY k.created_at DESC
      LIMIT ? OFFSET ?
    `;

    const countSql = `
      SELECT COUNT(*) AS count
      FROM kyc_documents k
      ${whereClause}
    `;

    const [documents, countRows] = await Promise.all([
      query<KycRow>(kycSql, [...params, limit, offset]),
      query<CountRow>(countSql, countParams),
    ]);

    const total = countRows[0]?.count ?? 0;

    const data = documents.map((d) => ({
      id: d.id,
      userId: d.user_id,
      fullName: d.full_name,
      dob: d.dob,
      aadhaarNumber: d.aadhaar_number,
      aadhaarFile: d.aadhaar_file,
      panNumber: d.pan_number,
      panFile: d.pan_file,
      status: d.status,
      rejectionReason: d.rejection_reason,
      verifiedAt: d.verified_at,
      verifiedBy: d.verified_by,
      createdAt: d.created_at,
      updatedAt: d.updated_at,
      user: { id: d.user_id, name: d.user_name, email: d.user_email },
    }));

    return NextResponse.json({ success: true, data, total, page });
  } catch (error) {
    console.error("Admin KYC GET error:", error);
    return NextResponse.json({ error: "Internal server error" }, { status: 500 });
  }
}
