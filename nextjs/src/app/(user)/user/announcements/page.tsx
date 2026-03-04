"use client";

import { useState, useEffect, useCallback } from "react";
import { formatDate } from "@/lib/utils";

interface Announcement {
  id: number;
  title: string;
  content: string;
  targetAudience: string;
  createdAt: string;
  isRead: boolean;
}

export default function UserAnnouncementsPage() {
  const [announcements, setAnnouncements] = useState<Announcement[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  const fetchAnnouncements = useCallback(async () => {
    setIsLoading(true);
    const res = await fetch("/api/user/announcements");
    const data = await res.json();
    if (data.success) setAnnouncements(data.data);
    setIsLoading(false);
  }, []);

  useEffect(() => {
    fetchAnnouncements();
  }, [fetchAnnouncements]);

  const markAsRead = async (id: number) => {
    await fetch(`/api/user/announcements/${id}/view`, { method: "POST" });
    setAnnouncements((prev) =>
      prev.map((a) => (a.id === id ? { ...a, isRead: true } : a))
    );
  };

  const unreadCount = announcements.filter((a) => !a.isRead).length;

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="bg-gradient-to-r from-[#667eea] to-[#764ba2] rounded-2xl p-6 text-white">
        <div className="flex items-center gap-3">
          <h1 className="text-2xl font-bold">📢 Announcements</h1>
          {unreadCount > 0 && (
            <span className="bg-red-500 text-white text-sm font-bold px-2.5 py-0.5 rounded-full">
              {unreadCount} new
            </span>
          )}
        </div>
        <p className="text-white/80 mt-1">Stay updated with the latest news</p>
      </div>

      {isLoading ? (
        <div className="flex justify-center py-12">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-[#667eea]" />
        </div>
      ) : !announcements.length ? (
        <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-12 text-center">
          <p className="text-4xl mb-2">📭</p>
          <p className="text-gray-500">No announcements yet.</p>
        </div>
      ) : (
        <div className="space-y-4">
          {announcements.map((a) => (
            <div
              key={a.id}
              className={`bg-white rounded-2xl shadow-sm border p-6 transition-all ${
                a.isRead ? "border-gray-100" : "border-[#667eea]/30 bg-[#667eea]/5"
              }`}
            >
              <div className="flex items-start justify-between gap-4">
                <div className="flex-1">
                  <div className="flex items-center gap-2 mb-1">
                    <h3 className="font-semibold text-gray-900">{a.title}</h3>
                    {!a.isRead && (
                      <span className="text-xs bg-[#667eea] text-white px-2 py-0.5 rounded-full">
                        New
                      </span>
                    )}
                  </div>
                  <p className="text-gray-600 text-sm leading-relaxed">{a.content}</p>
                  <p className="text-xs text-gray-400 mt-2">{formatDate(a.createdAt)}</p>
                </div>
                {!a.isRead && (
                  <button
                    onClick={() => markAsRead(a.id)}
                    className="text-xs text-[#667eea] hover:underline shrink-0"
                  >
                    Mark as read
                  </button>
                )}
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
