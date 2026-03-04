import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { queryOne, execute } from "@/lib/db";
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
}

export async function PUT(
  request: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const session = await auth();
  if (!session || session.user.userType !== "admin") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const { id } = await params;
  const docId = parseInt(id);

  if (isNaN(docId)) {
    return NextResponse.json({ error: "Invalid document ID" }, { status: 400 });
  }

  try {
    const body = await request.json();
    const { status, notes } = body;

    if (!["approved", "rejected"].includes(status)) {
      return NextResponse.json(
        { error: "Status must be approved or rejected" },
        { status: 400 }
      );
    }

    await execute(
      `UPDATE kyc_documents
       SET status = ?, rejection_reason = ?, verified_by = ?, verified_at = NOW(), updated_at = NOW()
       WHERE id = ?`,
      [status, notes ?? null, parseInt(session.user.id), docId]
    );

    const document = await queryOne<KycRow>(
      "SELECT * FROM kyc_documents WHERE id = ?",
      [docId]
    );

    if (!document) {
      return NextResponse.json({ error: "Document not found" }, { status: 404 });
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
        verifiedAt: document.verified_at,
        verifiedBy: document.verified_by,
        createdAt: document.created_at,
        updatedAt: document.updated_at,
      },
    });
  } catch (error) {
    console.error("Admin KYC PUT error:", error);
    return NextResponse.json({ error: "Internal server error" }, { status: 500 });
  }
}
