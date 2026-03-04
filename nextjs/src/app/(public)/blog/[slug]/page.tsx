"use client";

import { useState, useEffect } from "react";
import Link from "next/link";
import { useParams } from "next/navigation";
import { formatDate } from "@/lib/utils";

interface BlogPost {
  id: number;
  title: string;
  slug: string;
  content: string;
  excerpt: string | null;
  featuredImage: string | null;
  publishedAt: string | null;
  createdAt: string;
}

function estimateReadingTime(content: string): number {
  const wordsPerMinute = 200;
  const wordCount = content.trim().split(/\s+/).length;
  return Math.max(1, Math.ceil(wordCount / wordsPerMinute));
}

export default function BlogDetailPage() {
  const params = useParams();
  const slug = params?.slug as string;
  const [post, setPost] = useState<BlogPost | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [notFound, setNotFound] = useState(false);

  useEffect(() => {
    if (!slug) return;
    setIsLoading(true);
    fetch(`/api/blog/${slug}`)
      .then((res) => {
        if (res.status === 404) {
          setNotFound(true);
          return null;
        }
        return res.json();
      })
      .then((data) => {
        if (data?.success) setPost(data.data);
        setIsLoading(false);
      })
      .catch(() => {
        setIsLoading(false);
        setNotFound(true);
      });
  }, [slug]);

  if (isLoading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-[#667eea]" />
      </div>
    );
  }

  if (notFound || !post) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <p className="text-4xl mb-4">📭</p>
          <h1 className="text-2xl font-bold text-gray-900 mb-2">Post Not Found</h1>
          <p className="text-gray-500 mb-6">
            The blog post you&apos;re looking for doesn&apos;t exist or has been removed.
          </p>
          <Link
            href="/blog"
            className="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-[#667eea] to-[#764ba2] text-white rounded-xl font-medium hover:opacity-90 transition-opacity"
          >
            ← Back to Blog
          </Link>
        </div>
      </div>
    );
  }

  const readingTime = estimateReadingTime(post.content);

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Featured Image */}
      {post.featuredImage && (
        <div className="w-full h-64 md:h-96 overflow-hidden">
          <img
            src={post.featuredImage}
            alt={post.title}
            className="w-full h-full object-cover"
          />
        </div>
      )}

      <div className="max-w-3xl mx-auto px-4 py-12">
        {/* Back Link */}
        <Link
          href="/blog"
          className="inline-flex items-center gap-1 text-sm text-[#667eea] hover:underline mb-6"
        >
          ← Back to Blog
        </Link>

        {/* Post Header */}
        <h1 className="text-3xl md:text-4xl font-bold text-gray-900 leading-tight mb-4">
          {post.title}
        </h1>

        <div className="flex items-center gap-4 text-sm text-gray-400 mb-8 pb-8 border-b border-gray-200">
          <span>📅 {formatDate(post.publishedAt ?? post.createdAt)}</span>
          <span>⏱ {readingTime} min read</span>
        </div>

        {/* Post Content */}
        <div className="prose prose-gray max-w-none">
          {post.content.split("\n").map((paragraph, i) =>
            paragraph.trim() ? (
              <p key={i} className="text-gray-700 leading-relaxed mb-4">
                {paragraph}
              </p>
            ) : (
              <br key={i} />
            )
          )}
        </div>
      </div>
    </div>
  );
}
