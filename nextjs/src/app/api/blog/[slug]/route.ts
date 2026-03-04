import { NextRequest, NextResponse } from "next/server";
import { queryOne } from "@/lib/db";
import type { RowDataPacket } from "mysql2";

interface BlogPostRow extends RowDataPacket {
  id: number;
  title: string;
  slug: string;
  content: string | null;
  excerpt: string | null;
  status: string;
  created_at: Date;
  updated_at: Date;
}

export async function GET(
  _req: NextRequest,
  { params }: { params: Promise<{ slug: string }> }
) {
  const { slug } = await params;

  try {
    const post = await queryOne<BlogPostRow>(
      `SELECT id, title, slug, content, excerpt, status, created_at, updated_at
       FROM blog_posts
       WHERE slug = ? AND status = 'published'`,
      [slug]
    );

    if (!post) {
      return NextResponse.json({ error: "Post not found" }, { status: 404 });
    }

    return NextResponse.json({
      success: true,
      data: {
        id: post.id,
        title: post.title,
        slug: post.slug,
        content: post.content,
        excerpt: post.excerpt,
        status: post.status,
        createdAt:
          post.created_at instanceof Date ? post.created_at.toISOString() : post.created_at,
        updatedAt:
          post.updated_at instanceof Date ? post.updated_at.toISOString() : post.updated_at,
      },
    });
  } catch (error) {
    console.error("Blog slug GET error:", error);
    return NextResponse.json({ error: "Internal server error" }, { status: 500 });
  }
}
