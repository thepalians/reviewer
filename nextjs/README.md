# ReviewFlow — Next.js Application

This is the Next.js 15 rewrite of the ReviewFlow PHP application, built with React 19, TypeScript, Tailwind CSS, Prisma ORM, and NextAuth v5.

## Tech Stack

- **Framework**: Next.js 15 (App Router)
- **UI**: React 19 + Tailwind CSS
- **Language**: TypeScript (strict mode)
- **Database ORM**: Prisma (MySQL)
- **Authentication**: NextAuth v5 (Credentials provider)
- **Data Fetching**: TanStack React Query
- **Icons**: Lucide React
- **Charts**: Recharts
- **Validation**: Zod

## Project Structure

```
nextjs/
├── prisma/
│   └── schema.prisma         # Database schema (maps to existing MySQL tables)
├── src/
│   ├── app/
│   │   ├── layout.tsx         # Root layout
│   │   ├── page.tsx           # Home (redirects based on role)
│   │   ├── globals.css        # Global styles
│   │   ├── providers.tsx      # SessionProvider + QueryClientProvider
│   │   ├── (auth)/
│   │   │   ├── login/page.tsx
│   │   │   └── register/page.tsx
│   │   ├── (user)/
│   │   │   ├── layout.tsx     # User layout with sidebar
│   │   │   └── dashboard/page.tsx
│   │   ├── (admin)/
│   │   │   ├── layout.tsx     # Admin layout with sidebar
│   │   │   └── dashboard/page.tsx
│   │   ├── (seller)/
│   │   │   ├── layout.tsx     # Seller layout with sidebar
│   │   │   └── dashboard/page.tsx
│   │   └── api/
│   │       ├── auth/
│   │       │   ├── [...nextauth]/route.ts
│   │       │   └── register/route.ts
│   │       ├── user/
│   │       │   ├── dashboard/route.ts
│   │       │   └── tasks/route.ts
│   │       └── admin/
│   │           └── dashboard/route.ts
│   ├── components/
│   │   ├── ui/
│   │   │   ├── Button.tsx
│   │   │   ├── Card.tsx
│   │   │   ├── Badge.tsx
│   │   │   ├── Input.tsx
│   │   │   ├── Sidebar.tsx
│   │   │   ├── Modal.tsx
│   │   │   └── DataTable.tsx
│   │   ├── UserSidebar.tsx
│   │   ├── AdminSidebar.tsx
│   │   ├── SellerSidebar.tsx
│   │   ├── StatsCard.tsx
│   │   └── TaskCard.tsx
│   ├── lib/
│   │   ├── auth.ts            # NextAuth configuration
│   │   ├── db.ts              # Prisma client singleton
│   │   ├── utils.ts           # Utility functions
│   │   └── validators.ts      # Zod schemas
│   ├── middleware.ts           # Route protection
│   └── types/
│       ├── index.ts           # TypeScript interfaces
│       └── next-auth.d.ts     # NextAuth type augmentation
├── .env.example
├── next.config.ts
├── package.json
├── postcss.config.mjs
├── tailwind.config.ts
└── tsconfig.json
```

## Getting Started

### 1. Environment Setup

```bash
cd nextjs
cp .env.example .env.local
```

Edit `.env.local` and fill in your values:

```env
DATABASE_URL="mysql://user:password@localhost:3306/reviewflow"
NEXTAUTH_SECRET="your-secure-random-secret"
NEXTAUTH_URL="http://localhost:3000"
APP_NAME="ReviewFlow"
```

Generate a secure `NEXTAUTH_SECRET`:
```bash
openssl rand -base64 32
```

### 2. Install Dependencies

```bash
npm install
```

### 3. Database Setup

The Prisma schema is designed to connect to the **existing MySQL database** used by the PHP app. It uses `@map()` decorators to match exact column names.

Generate the Prisma client:
```bash
npm run db:generate
```

If starting fresh (no existing DB), run migrations:
```bash
npm run db:migrate
```

To push schema changes without migrations:
```bash
npm run db:push
```

To browse the database visually:
```bash
npm run db:studio
```

### 4. Run Development Server

```bash
npm run dev
```

Open [http://localhost:3000](http://localhost:3000).

### 5. Build for Production

```bash
npm run build
npm run start
```

## Authentication

Three user types are supported:

| Type    | Login with          | Dashboard URL       |
|---------|---------------------|---------------------|
| User    | Email or Mobile     | `/user/dashboard`   |
| Admin   | Email               | `/admin/dashboard`  |
| Seller  | Email               | `/seller/dashboard` |

### Route Protection

The middleware (`src/middleware.ts`) protects routes based on user type:
- `/user/*` → requires `userType === 'user'`
- `/admin/*` → requires `userType === 'admin'`
- `/seller/*` → requires `userType === 'seller'`

Unauthenticated users are redirected to `/login`.

## Coexistence with PHP App

The Next.js app lives in the `nextjs/` subdirectory. The existing PHP app at the repo root remains **completely untouched** and continues to work. Both apps connect to the same MySQL database.

- PHP app: served via Apache/Nginx on port 80 (or your existing setup)
- Next.js app: served via `npm run dev` on port 3000

## Color Theme

The app uses the same gradient colors as the PHP app:

| Variable         | Color     | Usage                    |
|------------------|-----------|--------------------------|
| `#667eea`        | Blue-purple | Primary brand color     |
| `#764ba2`        | Dark purple | Gradient end            |
| `#11998e`        | Teal        | Success / Seller panel  |
| `#38ef7d`        | Light green | Gradient end            |
