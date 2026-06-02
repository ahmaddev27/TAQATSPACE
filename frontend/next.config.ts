import type { NextConfig } from "next";
import createNextIntlPlugin from "next-intl/plugin";

const withNextIntl = createNextIntlPlugin("./src/i18n/request.ts");

const nextConfig: NextConfig = {
  // Self-contained server bundle for Node hosting (cPanel/Passenger, Docker).
  output: "standalone",
};

export default withNextIntl(nextConfig);
