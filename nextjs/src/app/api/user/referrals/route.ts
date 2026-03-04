import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { queryOne, query } from "@/lib/db";
import type { RowDataPacket } from "mysql2";

interface UserRow extends RowDataPacket {
  referral_code: string | null;
}

interface ReferralRow extends RowDataPacket {
  id: number;
  referrer_id: number;
  referred_id: number;
  created_at: Date;
  // joined from users
  referred_name: string;
  referred_email: string;
  referred_created_at: Date;
  referred_status: string;
}

export async function GET(_req: NextRequest) {
  const session = await auth();
  if (!session || session.user.userType !== "user") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const userId = parseInt(session.user.id);

  try {
    const [user, referrals] = await Promise.all([
      queryOne<UserRow>(
        "SELECT referral_code FROM users WHERE id = ?",
        [userId]
      ),
      query<ReferralRow>(
        `SELECT r.id, r.referrer_id, r.referred_id, r.created_at,
                u.name AS referred_name, u.email AS referred_email,
                u.created_at AS referred_created_at, u.status AS referred_status
         FROM referrals r
         JOIN users u ON u.id = r.referred_id
         WHERE r.referrer_id = ?
         ORDER BY r.created_at DESC`,
        [userId]
      ),
    ]);

    return NextResponse.json({
      success: true,
      data: {
        referralCode: user?.referral_code ?? null,
        totalReferred: referrals.length,
        totalRewards: 0,
        referrals: referrals.map((r) => ({
          id: r.id,
          rewardPaid: false,
          rewardAmount: null,
          createdAt: r.created_at instanceof Date ? r.created_at.toISOString() : String(r.created_at),
          referred: {
            id: r.referred_id,
            name: r.referred_name,
            email: r.referred_email,
            createdAt: r.referred_created_at instanceof Date
              ? r.referred_created_at.toISOString()
              : String(r.referred_created_at),
            status: r.referred_status,
          },
        })),
      },
    });
  } catch (error) {
    console.error("Referrals GET error:", error);
    return NextResponse.json({ error: "Internal server error" }, { status: 500 });
  }
}
