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
  { href: "/user/dashboard", label: "Dashboard", emoji: "🏠" },
  // Tasks
  { href: "/user/tasks", label: "My Tasks", emoji: "📋", section: "📋 Tasks" },
  // Social Hub
  { href: "/user/social-hub", label: "Social Hub", emoji: "📱", section: "📱 Social" },
  // Finance
  { href: "/user/wallet", label: "Wallet", emoji: "💰", section: "💰 Finance" },
  // Referrals
  { href: "/user/referrals", label: "My Referrals", emoji: "🔗", section: "🔗 Referrals" },
  // Gamification
  { href: "/user/rewards", label: "Rewards & Points", emoji: "🎮", section: "🎮 Gamification" },
  { href: "/user/badges", label: "Badges", emoji: "🏅" },
  { href: "/user/spin-wheel", label: "Daily Spin", emoji: "🎰" },
  { href: "/user/leaderboard", label: "Leaderboard", emoji: "🏆" },
  // Support
  { href: "/user/chat", label: "Support Chat", emoji: "💬", section: "💬 Support" },
  { href: "/user/announcements", label: "Announcements", emoji: "📢" },
  // Account
  { href: "/user/kyc", label: "KYC Verification", emoji: "🆔", section: "🔐 Account" },
  { href: "/user/profile", label: "Profile", emoji: "👤" },
  { href: "/user/notifications", label: "Notifications", emoji: "🔔" },
];

interface UserSidebarProps {
  appName?: string;
  pendingTasksCount?: number;
  unreadMessages?: number;
  unreadAnnouncements?: number;
}

export default function UserSidebar({
  appName = "ReviewFlow",
  pendingTasksCount = 0,
  unreadMessages = 0,
  unreadAnnouncements = 0,
}: UserSidebarProps) {
  const pathname = usePathname();

  const getBadge = (href: string) => {
    if (href === "/user/tasks" && pendingTasksCount > 0) return pendingTasksCount;
    if (href === "/user/chat" && unreadMessages > 0) return unreadMessages;
    if (href === "/user/announcements" && unreadAnnouncements > 0) return unreadAnnouncements;
    return 0;
  };

  return (
    <div className="flex flex-col h-full overflow-y-auto bg-white border-r border-gray-100">
      {/* Header */}
      <div className="px-6 py-5 bg-gradient-to-r from-[#667eea] to-[#764ba2]">
        <h2 className="text-lg font-bold text-white">🏠 {appName}</h2>
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
