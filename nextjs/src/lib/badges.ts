export interface BadgeDefinition {
  id: number;
  emoji: string;
  name: string;
  description: string;
  requirement: string;
  category: "task" | "social" | "streak" | "special";
}

export const BADGE_DEFINITIONS: BadgeDefinition[] = [
  {
    id: 1,
    emoji: "🌟",
    name: "First Task",
    description: "Complete your very first task",
    requirement: "Complete 1 task",
    category: "task",
  },
  {
    id: 2,
    emoji: "🔥",
    name: "Task Master",
    description: "Prove your dedication with 10 completed tasks",
    requirement: "Complete 10 tasks",
    category: "task",
  },
  {
    id: 3,
    emoji: "💎",
    name: "Diamond Worker",
    description: "An elite reviewer with 50 completed tasks",
    requirement: "Complete 50 tasks",
    category: "task",
  },
  {
    id: 4,
    emoji: "📱",
    name: "Social Star",
    description: "Active on the social hub with 10 social tasks done",
    requirement: "Complete 10 social tasks",
    category: "social",
  },
  {
    id: 5,
    emoji: "🎯",
    name: "Streak King",
    description: "Log in 7 days in a row",
    requirement: "7-day login streak",
    category: "streak",
  },
  {
    id: 6,
    emoji: "💰",
    name: "Big Earner",
    description: "Accumulated ₹5000 in total earnings",
    requirement: "Earn ₹5000 total",
    category: "special",
  },
  {
    id: 7,
    emoji: "👥",
    name: "Referral Pro",
    description: "Brought 5 new users to the platform",
    requirement: "Refer 5 users",
    category: "social",
  },
  {
    id: 8,
    emoji: "🏆",
    name: "Top Performer",
    description: "Reached the #1 spot on the leaderboard",
    requirement: "Reach #1 on leaderboard",
    category: "special",
  },
];

export type TierName = "Bronze" | "Silver" | "Gold" | "Platinum";

export interface Tier {
  name: TierName;
  min: number;
  max: number | null;
  color: string;
  gradient: string;
  emoji: string;
}

export const TIERS: Tier[] = [
  {
    name: "Bronze",
    min: 0,
    max: 500,
    color: "text-amber-700",
    gradient: "from-amber-600 to-amber-400",
    emoji: "🥉",
  },
  {
    name: "Silver",
    min: 501,
    max: 2000,
    color: "text-slate-500",
    gradient: "from-slate-500 to-slate-300",
    emoji: "🥈",
  },
  {
    name: "Gold",
    min: 2001,
    max: 5000,
    color: "text-yellow-500",
    gradient: "from-yellow-500 to-yellow-300",
    emoji: "🥇",
  },
  {
    name: "Platinum",
    min: 5001,
    max: null,
    color: "text-purple-500",
    gradient: "from-[#667eea] to-[#764ba2]",
    emoji: "💎",
  },
];

export function getTier(points: number): Tier {
  return (
    TIERS.slice()
      .reverse()
      .find((t) => points >= t.min) ?? TIERS[0]
  );
}

export function getNextTier(points: number): Tier | null {
  const idx = TIERS.findIndex((t) => t.name === getTier(points).name);
  return TIERS[idx + 1] ?? null;
}
