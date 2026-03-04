"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { signOut } from "next-auth/react";
import { cn } from "@/lib/utils";

interface NavItem {
  href: string;
  label: string;
  emoji: string;
  badge?: number;
  section?: string;
}

const navItems: NavItem[] = [
  { href: "/admin/dashboard", label: "Dashboard", emoji: "📊" },
  // Users
  { href: "/admin/users", label: "All Users", emoji: "👥", section: "👥 Users" },
  { href: "/admin/kyc", label: "KYC Management", emoji: "🔐" },
  // Tasks
  { href: "/admin/tasks", label: "All Tasks", emoji: "📋", section: "📋 Tasks" },
  // Campaigns
  { href: "/admin/campaigns", label: "Campaigns", emoji: "📱", section: "📱 Campaigns" },
  // Finance
  { href: "/admin/withdrawals", label: "Withdrawals", emoji: "💸", section: "💰 Finance" },
  // Content
  { href: "/admin/blog", label: "Blog", emoji: "📝", section: "📝 Content" },
  { href: "/admin/announcements", label: "Announcements", emoji: "📢" },
  // Support
  { href: "/admin/chat", label: "Support Chat", emoji: "💬", section: "💬 Support" },
  // Settings
  { href: "/admin/settings", label: "Settings", emoji: "⚙️", section: "⚙️ Settings" },
];

interface AdminSidebarProps {
  appName?: string;
  pendingTasks?: number;
  pendingWithdrawals?: number;
  pendingKyc?: number;
  pendingProofs?: number;
  pendingSocialCampaigns?: number;
}

export default function AdminSidebar({
  appName = "ReviewFlow",
  pendingTasks = 0,
  pendingWithdrawals = 0,
  pendingKyc = 0,
  pendingProofs = 0,
  pendingSocialCampaigns = 0,
}: AdminSidebarProps) {
  const pathname = usePathname();

  const getBadge = (href: string) => {
    if (href === "/admin/tasks" && pendingTasks > 0) return pendingTasks;
    if (href === "/admin/withdrawals" && pendingWithdrawals > 0) return pendingWithdrawals;
    if (href === "/admin/kyc" && pendingKyc > 0) return pendingKyc;
    if (href === "/admin/campaigns" && pendingSocialCampaigns > 0) return pendingSocialCampaigns;
    return 0;
  };

  return (
    <div className="flex flex-col h-full overflow-y-auto bg-white border-r border-gray-100">
      {/* Header */}
      <div className="px-6 py-5 bg-gradient-to-r from-[#667eea] to-[#764ba2]">
        <h2 className="text-lg font-bold text-white">⚙️ {appName}</h2>
        <p className="text-xs text-white/70 mt-0.5">Admin Panel</p>
      </div>

      {/* Navigation */}
      <nav className="flex-1 px-3 py-4 space-y-0.5">
        {navItems.map((item) => {
          const isActive = pathname === item.href || pathname.startsWith(item.href + "/");
          const badge = getBadge(item.href);

          return (
            <div key={item.href}>
              {item.section && (
                <div className="pt-4 pb-1 px-3">
                  <p className="text-xs font-semibold text-gray-400 uppercase tracking-wider">
                    {item.section}
                  </p>
                </div>
              )}
              <Link
                href={item.href}
                className={cn(
                  "flex items-center justify-between px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-150",
                  isActive
                    ? "bg-gradient-to-r from-[#667eea]/10 to-[#764ba2]/10 text-[#667eea] font-semibold"
                    : "text-gray-600 hover:bg-gray-50 hover:text-gray-900"
                )}
              >
                <span>
                  {item.emoji} {item.label}
                </span>
                {badge > 0 && (
                  <span className="ml-2 bg-red-500 text-white text-xs rounded-full px-1.5 py-0.5 min-w-[18px] text-center">
                    {badge}
                  </span>
                )}
              </Link>
            </div>
          );
        })}
      </nav>

      {/* Logout */}
      <div className="px-3 py-4 border-t border-gray-100">
        <button
          onClick={() => signOut({ callbackUrl: "/login" })}
          className="flex items-center w-full px-3 py-2 rounded-lg text-sm font-medium text-red-600 hover:bg-red-50 transition-colors duration-150"
        >
          🚪 Logout
        </button>
      </div>
    </div>
  );
}
