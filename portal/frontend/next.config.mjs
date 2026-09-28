// Officer screens, the admin placeholder and the sign-in page moved into the
// Filament staff panel on the backend; send their old URLs there. Only the
// civilian flow at / is still served from here.
const API_URL = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000';

/** @type {import('next').NextConfig} */
const nextConfig = {
  async redirects() {
    return [
      { source: '/police', destination: `${API_URL}/staff`, permanent: false },
      { source: '/police/:path*', destination: `${API_URL}/staff`, permanent: false },
      { source: '/admin', destination: `${API_URL}/staff`, permanent: false },
      { source: '/login', destination: `${API_URL}/staff/login`, permanent: false },
    ];
  },
};

export default nextConfig;
