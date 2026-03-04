import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/db";

export async function GET(
  _req: NextRequest,
  { params }: { params: Promise<{ slug: string }> }
) {
  const { slug } = await params;

  try {
    const post = await prisma.blogPost.findFirst({
      where: { slug, status: "published" },
    });

    if (!post) {
      return NextResponse.json({ error: "Post not found" }, { status: 404 });
    }

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
    console.error("Blog slug GET error:", error);
    return NextResponse.json({ error: "Internal server error" }, { status: 500 });
  }
}
