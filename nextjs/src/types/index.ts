// User types
export interface User {
  id: number;
  name: string;
  email: string;
  mobile: string;
  userType: "user" | "admin" | "seller";
  status: string;
  walletBalance: number;
  referralCode?: string;
  referredBy?: number;
  accountName?: string;
  accountNumber?: string;
  bankName?: string;
  ifscCode?: string;
  upiId?: string;
  lastLogin?: string;
  createdAt: string;
  updatedAt: string;
}

export interface Seller {
  id: number;
  name: string;
  email: string;
  status: string;
  createdAt: string;
}

// Task types
export type TaskStatus =
  | "assigned"
  | "in_progress"
  | "pending"
  | "completed"
  | "rejected"
  | "cancelled";

export interface Task {
  id: number;
  userId: number;
  orderId?: string;
  productName?: string;
  productLink?: string;
  platform?: string;
  instructions?: string;
  status: TaskStatus;
  commission?: number;
  deadline?: string;
  refundRequested: boolean;
  refundDate?: string;
  reviewText?: string;
  reviewRating?: number;
  createdAt: string;
  updatedAt: string;
  steps?: TaskStep[];
  user?: Pick<User, "id" | "name" | "email">;
}

export interface TaskStep {
  id: number;
  taskId: number;
  stepNumber: number;
  stepName?: string;
  stepStatus: "pending" | "submitted" | "approved" | "rejected" | "completed";
  submittedByUser: boolean;
  orderScreenshot?: string;
  deliveryScreenshot?: string;
  reviewScreenshot?: string;
  reviewSubmittedScreenshot?: string;
  reviewLiveScreenshot?: string;
  paymentQrCode?: string;
  refundAmount?: number;
  adminPaymentScreenshot?: string;
  refundProcessedAt?: string;
  refundProcessedBy?: string;
  completedAt?: string;
  createdAt: string;
  updatedAt: string;
}

// Wallet types
export interface WalletTransaction {
  id: number;
  userId: number;
  type: string;
  amount: number;
  description?: string;
  referenceId?: number;
  referenceType?: string;
  balanceBefore?: number;
  balanceAfter?: number;
  createdAt: string;
}

// Social types
export interface SocialPlatform {
  id: number;
  name: string;
  slug: string;
  icon?: string;
  isActive: boolean;
  createdAt: string;
}

export interface SocialCampaign {
  id: number;
  sellerId: number;
  platformId: number;
  title: string;
  description?: string;
  url?: string;
  rewardAmount: number;
  requiredTime?: number;
  status: string;
  adminApproved: boolean;
  createdAt: string;
  updatedAt: string;
  platform?: SocialPlatform;
  seller?: Pick<Seller, "id" | "name">;
}

// Announcement types
export interface Announcement {
  id: number;
  title: string;
  content: string;
  targetAudience: "all" | "users" | "sellers" | "admins";
  isActive: boolean;
  startDate?: string;
  endDate?: string;
  createdAt: string;
  updatedAt: string;
}

// KYC types
export interface KycDocument {
  id: number;
  userId: number;
  documentType: string;
  documentPath: string;
  status: "pending" | "approved" | "rejected";
  reviewedBy?: number;
  reviewedAt?: string;
  notes?: string;
  createdAt: string;
  updatedAt: string;
}

// Gamification types
export interface UserPoint {
  id: number;
  userId: number;
  points: number;
  type: string;
  description?: string;
  createdAt: string;
}

export interface Competition {
  id: number;
  title: string;
  description?: string;
  startDate: string;
  endDate: string;
  prizePool?: number;
  status: string;
  createdAt: string;
}

// Dashboard stats
export interface UserDashboardStats {
  totalTasks: number;
  completedTasks: number;
  pendingTasks: number;
  walletBalance: number;
  activeCampaigns: number;
  recentTasks: Task[];
}

export interface AdminDashboardStats {
  totalUsers: number;
  totalSellers: number;
  totalTasks: number;
  pendingTasks: number;
  completedTasks: number;
  totalRevenue: number;
  pendingWithdrawals: number;
  pendingKyc: number;
}

// API response types
export interface ApiResponse<T = unknown> {
  success: boolean;
  data?: T;
  error?: string;
  message?: string;
}

// Pagination
export interface PaginationParams {
  page?: number;
  limit?: number;
}

export interface PaginatedResponse<T> {
  items: T[];
  total: number;
  page: number;
  limit: number;
  totalPages: number;
}
