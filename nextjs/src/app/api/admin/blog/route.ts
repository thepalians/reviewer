import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { query, execute } from "@/lib/db";
import type { RowDataPacket } from "mysql2";

interface BlogPostRow extends RowDataPacket {
  id: number;
  title: string;
  slug: string;
  content: string;
  excerpt: string | null;
  status: string;
  author_id: number;
  created_at: string;
  updated_at: string;
}

interface CountRow extends RowDataPacket {
  count: number;
}

interface InsertIdRow extends RowDataPacket {
  id: number;
  title: string;
  slug: string;
  content: string;
  excerpt: string | null;
  status: string;
  author_id: number;
  created_at: string;
  updated_at: string;
}

export async function GET(request: NextRequest) {
  const session = await auth();
  if (!session || session.user.userType !== "admin") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  try {
    const { searchParams } = new URL(request.url);
    const page = parseInt(searchParams.get("page") || "1");
    const limit = parseInt(searchParams.get("limit") || "20");
    const offset = (page - 1) * limit;

    const [posts, countRows] = await Promise.all([
      query<BlogPostRow>(
        "SELECT * FROM blog_posts ORDER BY created_at DESC LIMIT ? OFFSET ?",
        [limit, offset]
      ),
      query<CountRow>("SELECT COUNT(*) AS count FROM blog_posts"),
    ]);

    const total = countRows[0]?.count ?? 0;

    return NextResponse.json({
      success: true,
      data: posts.map((p) => ({
        id: p.id,
        title: p.title,
        slug: p.slug,
        content: p.content,
        excerpt: p.excerpt,
        status: p.status,
        authorId: p.author_id,
        createdAt: p.created_at,
        updatedAt: p.updated_at,
      })),
      total,
      page,
    });
  } catch (error) {
    console.error("Admin Blog GET error:", error);
    return NextResponse.json({ error: "Internal server error" }, { status: 500 });
  }
}

export async function POST(request: NextRequest) {
  const session = await auth();
  if (!session || session.user.userType !== "admin") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  try {
    const body = await request.json();
    const { title, slug, content, excerpt, status } = body;

    if (!title || !slug || !content) {
      return NextResponse.json(
        { error: "title, slug, and content are required" },
        { status: 400 }
      );
    }

    const postStatus = status ?? "draft";
    const authorId = parseInt(session.user.id);

    const result = await execute(
      `INSERT INTO blog_posts (title, slug, content, excerpt, status, author_id, created_at, updated_at)
       VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())`,
      [title, slug, content, excerpt ?? null, postStatus, authorId]
    );

    const post = await query<InsertIdRow>(
      "SELECT * FROM blog_posts WHERE id = ?",
      [result.insertId]
    );

    const p = post[0];

    return NextResponse.json({
      success: true,
      data: {
        id: p.id,
        title: p.title,
        slug: p.slug,
        content: p.content,
        excerpt: p.excerpt,
        status: p.status,
        authorId: p.author_id,
        createdAt: p.created_at,
        updatedAt: p.updated_at,
      },
    });
  } catch (error) {
    console.error("Admin Blog POST error:", error);
    return NextResponse.json({ error: "Internal server error" }, { status: 500 });
  }
}
