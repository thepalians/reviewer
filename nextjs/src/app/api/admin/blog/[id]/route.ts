import { NextRequest, NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { prisma } from "@/lib/db";

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
    const { title, slug, content, excerpt, featuredImage, status } = body;

    const existing = await prisma.blogPost.findUnique({ where: { id: postId } });
    if (!existing) {
      return NextResponse.json({ error: "Post not found" }, { status: 404 });
    }

    const wasPublished = existing.status === "published";
    const nowPublished = status === "published";

    const post = await prisma.blogPost.update({
      where: { id: postId },
      data: {
        ...(title !== undefined && { title }),
        ...(slug !== undefined && { slug }),
        ...(content !== undefined && { content }),
        ...(excerpt !== undefined && { excerpt }),
        ...(featuredImage !== undefined && { featuredImage }),
        ...(status !== undefined && { status }),
        publishedAt: nowPublished && !wasPublished ? new Date() : existing.publishedAt,
      },
    });

    return NextResponse.json({
      success: true,
      data: {
        ...post,
        createdAt: post.createdAt.toISOString(),
        updatedAt: post.updatedAt.toISOString(),
        publishedAt: post.publishedAt ? post.publishedAt.toISOString() : null,
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
    await prisma.blogPost.delete({ where: { id: postId } });
    return NextResponse.json({ success: true });
  } catch (error) {
    console.error("Admin Blog DELETE error:", error);
    return NextResponse.json({ error: "Internal server error" }, { status: 500 });
  }
}
