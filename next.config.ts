import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  experimental: {
    serverActions: {
      bodySizeLimit: "100mb",
      // Dev tunnels / remote port-forwarding (e.g. VS Code) rewrite
      // `x-forwarded-host` to the tunnel host while the browser's `Origin`
      // stays `http://localhost:3000`. Next.js compares the two and rejects
      // Server Action POSTs with E80 ("Invalid Server Actions request.").
      // Allowlisting the browser-visible dev origins keeps the CSRF check
      // intact for every other origin. Dev-only: production never receives
      // these loopback origins.
      ...(process.env.NODE_ENV === "development"
        ? { allowedOrigins: ["localhost:3000", "127.0.0.1:3000"] }
        : {}),
    },
  },
};

export default nextConfig;