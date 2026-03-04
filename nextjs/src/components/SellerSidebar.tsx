"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { signOut } from "next-auth/react";
import { cn } from "@/lib/utils";

interface NavItem {
  href: string;
  label: string;
  emoji: string;
  section?: string;
}

const navItems: NavItem[] = [
  { href: "/seller/dashboard", label: "Dashboard", emoji: "📊" },
  { href: "/seller/campaigns", label: "My Campaigns", emoji: "📢", section: "📱 Campaigns" },
  { href: "/seller/campaigns/create", label: "Create Campaign", emoji: "➕" },
  { href: "/seller/wallet", label: "Wallet", emoji: "💰", section: "💰 Finance" },
  { href: "/seller/settings", label: "Settings", emoji: "⚙️", section: "⚙️ Settings" },
];

interface SellerSidebarProps {
  appName?: string;
}

export default function SellerSidebar({ appName = "ReviewFlow" }: SellerSidebarProps) {
  const pathname = usePathname();

  return (
    <div className="flex flex-col h-full overflow-y-auto bg-white border-r border-gray-100">
      {/* Header */}
      <div className="px-6 py-5 bg-gradient-to-r from-[#11998e] to-[#38ef7d]">
        <h2 className="text-lg font-bold text-white">🏪 {appName}</h2>
        <p className="text-xs text-white/70 mt-0.5">Seller Panel</p>
      </div>

      {/* Navigation */}
      <nav className="flex-1 px-3 py-4 space-y-0.5">
        {navItems.map((item) => {
          const isActive = pathname === item.href || pathname.startsWith(item.href + "/");

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
                  "flex items-center px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-150",
                  isActive
                    ? "bg-gradient-to-r from-[#11998e]/10 to-[#38ef7d]/10 text-[#11998e] font-semibold"
                    : "text-gray-600 hover:bg-gray-50 hover:text-gray-900"
                )}
              >
                {item.emoji} <span className="ml-2">{item.label}</span>
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
