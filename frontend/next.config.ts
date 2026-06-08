import type { NextConfig } from "next";
import createNextIntlPlugin from "next-intl/plugin";

const withNextIntl = createNextIntlPlugin("./src/i18n/request.ts");

const nextConfig: NextConfig = {
  // Self-contained server bundle for Node hosting (cPanel/Passenger, Docker).
  output: "standalone",
  experimental: {
    serverActions: {
      // Chat attachment uploads go through a Server Action; the default body
      // limit is 1 MB, which 500s files larger than that. Allow headroom over
      // the backend's 10 MB attachment cap.
      bodySizeLimit: "12mb",
    },
  },
};

export default withNextIntl(nextConfig);
