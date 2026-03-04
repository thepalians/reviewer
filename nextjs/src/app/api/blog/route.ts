import { NextRequest, NextResponse } from "next/server";
import { query } from "@/lib/db";
import type { RowDataPacket } from "mysql2";

interface BlogPostRow extends RowDataPacket {
  id: number;
  title: string;
  slug: string;
  excerpt: string | null;
  created_at: Date;
}

interface CountRow extends RowDataPacket {
  total: number;
}

export async function GET(request: NextRequest) {
  try {
    const { searchParams } = new URL(request.url);
    const page = parseInt(searchParams.get("page") || "1");
    const limit = parseInt(searchParams.get("limit") || "12");
    const search = searchParams.get("search") || "";
    const offset = (page - 1) * limit;

    const conditions: string[] = ["status = 'published'"];
    const params: unknown[] = [];
    const countParams: unknown[] = [];

    if (search) {
      conditions.push("title LIKE ?");
      params.push(`%${search}%`);
      countParams.push(`%${search}%`);
    }

    const whereClause = conditions.join(" AND ");

    const [posts, countRows] = await Promise.all([
      query<BlogPostRow>(
        `SELECT id, title, slug, excerpt, created_at
         FROM blog_posts
         WHERE ${whereClause}
         ORDER BY created_at DESC
         LIMIT ? OFFSET ?`,
        [...params, limit, offset]
      ),
      query<CountRow>(
        `SELECT COUNT(*) AS total FROM blog_posts WHERE ${whereClause}`,
        countParams
      ),
    ]);

    const total = Number(countRows[0]?.total ?? 0);

    return NextResponse.json({
      success: true,
      data: posts.map((p) => ({
        id: p.id,
        title: p.title,
        slug: p.slug,
        excerpt: p.excerpt,
        createdAt: p.created_at instanceof Date ? p.created_at.toISOString() : p.created_at,
      })),
      total,
      page,
      totalPages: Math.ceil(total / limit),
    });
  } catch (error) {
    console.error("Blog GET error:", error);
    return NextResponse.json({ error: "Internal server error" }, { status: 500 });
  }
}
