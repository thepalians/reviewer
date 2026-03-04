"use client";

import { useState, useEffect, useCallback } from "react";
import Link from "next/link";
import { formatDate } from "@/lib/utils";

interface BlogPost {
  id: number;
  title: string;
  slug: string;
  excerpt: string | null;
  featuredImage: string | null;
  publishedAt: string | null;
  createdAt: string;
}

export default function BlogListPage() {
  const [posts, setPosts] = useState<BlogPost[]>([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [search, setSearch] = useState("");
  const [searchInput, setSearchInput] = useState("");
  const [isLoading, setIsLoading] = useState(true);

  const fetchPosts = useCallback(async () => {
    setIsLoading(true);
    const params = new URLSearchParams({ page: String(page), limit: "12" });
    if (search) params.set("search", search);
    const res = await fetch(`/api/blog?${params}`);
    const data = await res.json();
    if (data.success) {
      setPosts(data.data);
      setTotal(data.total);
      setTotalPages(data.totalPages);
    }
    setIsLoading(false);
  }, [page, search]);

  useEffect(() => {
    fetchPosts();
  }, [fetchPosts]);

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    setSearch(searchInput);
    setPage(1);
  };

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <div className="bg-gradient-to-r from-[#667eea] to-[#764ba2] py-16">
        <div className="max-w-6xl mx-auto px-4 text-center">
          <h1 className="text-4xl font-bold text-white mb-4">📝 Blog</h1>
          <p className="text-white/80 text-lg mb-8">
            Tips, guides, and news from ReviewFlow
          </p>
          <form onSubmit={handleSearch} className="max-w-md mx-auto flex gap-3">
            <input
              type="text"
              value={searchInput}
              onChange={(e) => setSearchInput(e.target.value)}
              placeholder="Search posts..."
              className="flex-1 px-4 py-2 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-white/30"
            />
            <button
              type="submit"
              className="px-6 py-2 bg-white text-[#667eea] font-medium rounded-xl hover:bg-white/90 transition-colors text-sm"
            >
              Search
            </button>
          </form>
        </div>
      </div>

      <div className="max-w-6xl mx-auto px-4 py-12">
        {isLoading ? (
          <div className="flex justify-center py-12">
            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-[#667eea]" />
          </div>
        ) : !posts.length ? (
          <div className="text-center py-12">
            <p className="text-4xl mb-4">📭</p>
            <p className="text-gray-500 text-lg">No blog posts found.</p>
            {search && (
              <button
                onClick={() => {
                  setSearch("");
                  setSearchInput("");
                }}
                className="mt-4 text-[#667eea] hover:underline"
              >
                Clear search
              </button>
            )}
          </div>
        ) : (
          <>
            <p className="text-sm text-gray-500 mb-6">{total} post{total !== 1 ? "s" : ""} found</p>
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
              {posts.map((post) => (
                <Link
                  key={post.id}
                  href={`/blog/${post.slug}`}
                  className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-shadow group"
                >
                  {post.featuredImage ? (
                    <div className="h-48 bg-gray-200 overflow-hidden">
                      <img
                        src={post.featuredImage}
                        alt={post.title}
                        className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                      />
                    </div>
                  ) : (
                    <div className="h-48 bg-gradient-to-r from-[#667eea]/20 to-[#764ba2]/20 flex items-center justify-center">
                      <span className="text-4xl">📝</span>
                    </div>
                  )}
                  <div className="p-5">
                    <h2 className="font-semibold text-gray-900 group-hover:text-[#667eea] transition-colors line-clamp-2 mb-2">
                      {post.title}
                    </h2>
                    {post.excerpt && (
                      <p className="text-sm text-gray-500 line-clamp-3 mb-3">{post.excerpt}</p>
                    )}
                    <p className="text-xs text-gray-400">
                      {formatDate(post.publishedAt ?? post.createdAt)}
                    </p>
                  </div>
                </Link>
              ))}
            </div>

            {/* Pagination */}
            {totalPages > 1 && (
              <div className="flex justify-center gap-2 mt-8">
                <button
                  onClick={() => setPage((p) => Math.max(1, p - 1))}
                  disabled={page === 1}
                  className="px-4 py-2 rounded-lg text-sm font-medium bg-white border border-gray-200 disabled:opacity-50 hover:bg-gray-50 transition-colors"
                >
                  Previous
                </button>
                <span className="px-4 py-2 text-sm text-gray-600">
                  Page {page} of {totalPages}
                </span>
                <button
                  onClick={() => setPage((p) => Math.min(totalPages, p + 1))}
                  disabled={page === totalPages}
                  className="px-4 py-2 rounded-lg text-sm font-medium bg-white border border-gray-200 disabled:opacity-50 hover:bg-gray-50 transition-colors"
                >
                  Next
                </button>
              </div>
            )}
          </>
        )}
      </div>
    </div>
  );
}
