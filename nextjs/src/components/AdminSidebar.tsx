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
  { href: "/admin/users", label: "All Reviewers", emoji: "👥", section: "👥 Users" },
  { href: "/admin/kyc-management", label: "KYC Management", emoji: "🔐" },
  // Tasks
  { href: "/admin/assign-task", label: "Assign Task", emoji: "➕", section: "📋 Tasks" },
  { href: "/admin/auto-assign", label: "Auto Assign Tasks", emoji: "⚡" },
  { href: "/admin/bulk-upload", label: "Bulk Upload", emoji: "📤" },
  { href: "/admin/tasks/pending", label: "Pending Tasks", emoji: "⏳" },
  { href: "/admin/tasks/completed", label: "Completed Tasks", emoji: "✅" },
  { href: "/admin/tasks/rejected", label: "Rejected Tasks", emoji: "❌" },
  { href: "/admin/verify-proofs", label: "Verify Proofs", emoji: "✅" },
  // Finance
  { href: "/admin/withdrawals", label: "Withdrawals", emoji: "💸", section: "💰 Finance" },
  { href: "/admin/wallet-requests", label: "Wallet Recharges", emoji: "💳" },
  { href: "/admin/seller-wallet", label: "Manage Seller Wallet", emoji: "🏦" },
  { href: "/admin/add-bonus", label: "Add User Bonus", emoji: "🎁" },
  // Sellers
  { href: "/admin/sellers", label: "All Sellers", emoji: "🏪", section: "🏪 Sellers" },
  { href: "/admin/review-requests", label: "Seller Requests", emoji: "📝" },
  // Referrals
  { href: "/admin/referral-settings", label: "Referral Settings", emoji: "⚙️", section: "🔗 Referrals" },
  // Gamification
  { href: "/admin/gamification", label: "Gamification Settings", emoji: "⚙️", section: "🎮 Gamification" },
  { href: "/admin/leaderboard", label: "Leaderboard", emoji: "🏆" },
  { href: "/admin/monthly-bonus", label: "Monthly Bonus", emoji: "🏅" },
  // Support
  { href: "/admin/support-chat", label: "Support Chat", emoji: "💬", section: "💬 Support" },
  { href: "/admin/faq-manager", label: "Chatbot FAQ", emoji: "❓" },
  { href: "/admin/chatbot-unanswered", label: "Unanswered Questions", emoji: "📝" },
  // Communication
  { href: "/admin/announcements", label: "Announcements", emoji: "📢", section: "📢 Communication" },
  { href: "/admin/broadcast", label: "Broadcast Messages", emoji: "📡" },
  // Task Management
  { href: "/admin/task-categories", label: "Task Categories", emoji: "🏷️", section: "🏷️ Task Management" },
  // Analytics
  { href: "/admin/analytics", label: "Analytics Dashboard", emoji: "📈", section: "📊 Analytics" },
  // Reports
  { href: "/admin/reports", label: "Reports", emoji: "📈", section: "📊 Reports & Export" },
  { href: "/admin/export-data", label: "Export Review Data", emoji: "📥" },
  { href: "/admin/export-reports", label: "Export Reports", emoji: "📊" },
  // Notifications
  { href: "/admin/notification-templates", label: "Notification Templates", emoji: "📧", section: "📧 Notifications" },
  // Security
  { href: "/admin/security-logs", label: "Security Logs", emoji: "🔒", section: "🔒 Security" },
  { href: "/admin/suspicious-users", label: "Suspicious Users", emoji: "🚨" },
  // Content
  { href: "/admin/blog-manage", label: "Blog Manager", emoji: "📝", section: "📝 Content" },
  // Social Media
  { href: "/admin/social-campaigns", label: "Social Campaigns", emoji: "📢", section: "📱 Social Media" },
  // Settings
  { href: "/admin/settings", label: "General Settings", emoji: "⚙️", section: "⚙️ Settings" },
  { href: "/admin/gst-settings", label: "GST Settings", emoji: "💰" },
  { href: "/admin/features", label: "Features", emoji: "✨" },
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
    if (href === "/admin/tasks/pending" && pendingTasks > 0) return pendingTasks;
    if (href === "/admin/withdrawals" && pendingWithdrawals > 0) return pendingWithdrawals;
    if (href === "/admin/kyc-management" && pendingKyc > 0) return pendingKyc;
    if (href === "/admin/verify-proofs" && pendingProofs > 0) return pendingProofs;
    if (href === "/admin/social-campaigns" && pendingSocialCampaigns > 0) return pendingSocialCampaigns;
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
