import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { query, queryOne, execute } from "@/lib/db";
import type { RowDataPacket } from "mysql2";

interface KycDocumentRow extends RowDataPacket {
  id: number;
  user_id: number;
  full_name: string | null;
  dob: string | null;
  aadhaar_number: string | null;
  aadhaar_file: string | null;
  pan_number: string | null;
  pan_file: string | null;
  status: string;
  rejection_reason: string | null;
  verified_at: Date | null;
  verified_by: number | null;
  created_at: Date;
  updated_at: Date;
}

export async function GET(_req: NextRequest) {
  const session = await auth();
  if (!session || session.user.userType !== "user") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const userId = parseInt(session.user.id);

  try {
    const documents = await query<KycDocumentRow>(
      "SELECT * FROM kyc_documents WHERE user_id = ? ORDER BY created_at DESC",
      [userId]
    );

    return NextResponse.json({
      success: true,
      data: documents.map((d) => ({
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
        verifiedAt: d.verified_at
          ? (d.verified_at instanceof Date ? d.verified_at.toISOString() : String(d.verified_at))
          : null,
        verifiedBy: d.verified_by,
        createdAt: d.created_at instanceof Date ? d.created_at.toISOString() : String(d.created_at),
        updatedAt: d.updated_at instanceof Date ? d.updated_at.toISOString() : String(d.updated_at),
      })),
    });
  } catch (error) {
    console.error("KYC GET error:", error);
    return NextResponse.json({ error: "Internal server error" }, { status: 500 });
  }
}

export async function POST(request: NextRequest) {
  const session = await auth();
  if (!session || session.user.userType !== "user") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const userId = parseInt(session.user.id);

  try {
    const body = await request.json();
    const {
      fullName,
      dob,
      aadhaarNumber,
      aadhaarFile,
      panNumber,
      panFile,
    } = body;

    // Check if a KYC document already exists for this user
    const existing = await queryOne<KycDocumentRow>(
      "SELECT * FROM kyc_documents WHERE user_id = ? ORDER BY created_at DESC LIMIT 1",
      [userId]
    );

    let docId: number;

    if (existing) {
      // Re-upload: update existing record, reset to pending
      await execute(
        `UPDATE kyc_documents
         SET full_name = ?, dob = ?, aadhaar_number = ?, aadhaar_file = ?,
             pan_number = ?, pan_file = ?, status = 'pending',
             rejection_reason = NULL, verified_at = NULL, verified_by = NULL,
             updated_at = NOW()
         WHERE id = ?`,
        [
          fullName ?? existing.full_name,
          dob ?? existing.dob,
          aadhaarNumber ?? existing.aadhaar_number,
          aadhaarFile ?? existing.aadhaar_file,
          panNumber ?? existing.pan_number,
          panFile ?? existing.pan_file,
          existing.id,
        ]
      );
      docId = existing.id;
    } else {
      const result = await execute(
        `INSERT INTO kyc_documents (user_id, full_name, dob, aadhaar_number, aadhaar_file, pan_number, pan_file, status, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())`,
        [
          userId,
          fullName ?? null,
          dob ?? null,
          aadhaarNumber ?? null,
          aadhaarFile ?? null,
          panNumber ?? null,
          panFile ?? null,
        ]
      );
      docId = result.insertId;
    }

    const document = await queryOne<KycDocumentRow>(
      "SELECT * FROM kyc_documents WHERE id = ?",
      [docId]
    );

    if (!document) {
      return NextResponse.json({ error: "Failed to retrieve document" }, { status: 500 });
    }

    return NextResponse.json({
      success: true,
      data: {
        id: document.id,
        userId: document.user_id,
        fullName: document.full_name,
        dob: document.dob,
        aadhaarNumber: document.aadhaar_number,
        aadhaarFile: document.aadhaar_file,
        panNumber: document.pan_number,
        panFile: document.pan_file,
        status: document.status,
        rejectionReason: document.rejection_reason,
        verifiedAt: document.verified_at
          ? (document.verified_at instanceof Date ? document.verified_at.toISOString() : String(document.verified_at))
          : null,
        verifiedBy: document.verified_by,
        createdAt: document.created_at instanceof Date ? document.created_at.toISOString() : String(document.created_at),
        updatedAt: document.updated_at instanceof Date ? document.updated_at.toISOString() : String(document.updated_at),
      },
    });
  } catch (error) {
    console.error("KYC POST error:", error);
    return NextResponse.json({ error: "Internal server error" }, { status: 500 });
  }
}
