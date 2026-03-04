import { z } from "zod";

export const loginSchema = z.object({
  login: z
    .string()
    .min(1, "Email or mobile is required")
    .max(100, "Too long"),
  password: z
    .string()
    .min(1, "Password is required")
    .max(255, "Too long"),
  userType: z.enum(["user", "admin", "seller"]).default("user"),
});

export const registerSchema = z.object({
  name: z
    .string()
    .min(2, "Name must be at least 2 characters")
    .max(100, "Name is too long"),
  email: z
    .string()
    .email("Invalid email address")
    .max(100, "Email is too long"),
  mobile: z
    .string()
    .regex(/^\d{10}$/, "Mobile must be 10 digits")
    .max(15, "Mobile number too long"),
  password: z
    .string()
    .min(6, "Password must be at least 6 characters")
    .max(255, "Password is too long"),
  referralCode: z
    .string()
    .max(20, "Referral code too long")
    .optional()
    .or(z.literal("")),
});

export const taskCreateSchema = z.object({
  userId: z.number().positive("Invalid user"),
  productName: z.string().min(1, "Product name is required").max(255),
  productLink: z.string().url("Invalid product URL").optional().or(z.literal("")),
  platform: z.string().max(50).optional(),
  instructions: z.string().max(5000).optional(),
  commission: z
    .number()
    .positive("Commission must be positive")
    .optional(),
  deadline: z.string().datetime().optional().or(z.literal("")),
});

export type LoginInput = z.infer<typeof loginSchema>;
export type RegisterInput = z.infer<typeof registerSchema>;
export type TaskCreateInput = z.infer<typeof taskCreateSchema>;
