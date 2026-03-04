import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  basePath: "/reviewer/nextjs",
  images: {
    remotePatterns: [
      {
        protocol: "https",
        hostname: "**",
      },
    ],
  },
};

export default nextConfig;
