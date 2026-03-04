import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { queryOne, execute } from "@/lib/db";
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

export async function PUT(
  request: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const session = await auth();
  if (!session || session.user.userType !== "admin") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const { id } = await params;
  const postId = parseInt(id);

  if (isNaN(postId)) {
    return NextResponse.json({ error: "Invalid post ID" }, { status: 400 });
  }

  try {
    const body = await request.json();
    const { title, slug, content, excerpt, status } = body;

    const existing = await queryOne<BlogPostRow>(
      "SELECT * FROM blog_posts WHERE id = ?",
      [postId]
    );

    if (!existing) {
      return NextResponse.json({ error: "Post not found" }, { status: 404 });
    }

    const setClauses: string[] = [];
    const values: unknown[] = [];

    if (title !== undefined) { setClauses.push("title = ?"); values.push(title); }
    if (slug !== undefined) { setClauses.push("slug = ?"); values.push(slug); }
    if (content !== undefined) { setClauses.push("content = ?"); values.push(content); }
    if (excerpt !== undefined) { setClauses.push("excerpt = ?"); values.push(excerpt); }
    if (status !== undefined) { setClauses.push("status = ?"); values.push(status); }

    setClauses.push("updated_at = NOW()");

    if (setClauses.length === 1) {
      // Only updated_at, nothing else to update
      const post = existing;
      return NextResponse.json({
        success: true,
        data: {
          id: post.id,
          title: post.title,
          slug: post.slug,
          content: post.content,
          excerpt: post.excerpt,
          status: post.status,
          authorId: post.author_id,
          createdAt: post.created_at,
          updatedAt: post.updated_at,
        },
      });
    }

    values.push(postId);

    await execute(
      `UPDATE blog_posts SET ${setClauses.join(", ")} WHERE id = ?`,
      values
    );

    const post = await queryOne<BlogPostRow>(
      "SELECT * FROM blog_posts WHERE id = ?",
      [postId]
    );

    if (!post) {
      return NextResponse.json({ error: "Post not found after update" }, { status: 404 });
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
        authorId: post.author_id,
        createdAt: post.created_at,
        updatedAt: post.updated_at,
      },
    });
  } catch (error) {
    console.error("Admin Blog PUT error:", error);
    return NextResponse.json({ error: "Internal server error" }, { status: 500 });
  }
}

export async function DELETE(
  _req: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  const session = await auth();
  if (!session || session.user.userType !== "admin") {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  const { id } = await params;
  const postId = parseInt(id);

  if (isNaN(postId)) {
    return NextResponse.json({ error: "Invalid post ID" }, { status: 400 });
  }

  try {
    const result = await execute(
      "DELETE FROM blog_posts WHERE id = ?",
      [postId]
    );

    if (result.affectedRows === 0) {
      return NextResponse.json({ error: "Post not found" }, { status: 404 });
    }

    return NextResponse.json({ success: true });
  } catch (error) {
    console.error("Admin Blog DELETE error:", error);
    return NextResponse.json({ error: "Internal server error" }, { status: 500 });
  }
}
