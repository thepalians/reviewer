import { cn } from "@/lib/utils";
import { ReactNode } from "react";

interface StatsCardProps {
  title: string;
  value: string | number;
  icon: ReactNode;
  gradient?: string;
  subtitle?: string;
  className?: string;
}

export default function StatsCard({
  title,
  value,
  icon,
  gradient = "from-[#667eea] to-[#764ba2]",
  subtitle,
  className,
}: StatsCardProps) {
  return (
    <div
      className={cn(
        "rounded-xl p-6 text-white shadow-lg",
        `bg-gradient-to-br ${gradient}`,
        className
      )}
    >
      <div className="flex items-center justify-between">
        <div>
          <p className="text-sm font-medium text-white/80">{title}</p>
          <p className="mt-1 text-3xl font-bold">{value}</p>
          {subtitle && (
            <p className="mt-1 text-xs text-white/70">{subtitle}</p>
          )}
        </div>
        <div className="text-4xl opacity-80">{icon}</div>
      </div>
    </div>
  );
}
