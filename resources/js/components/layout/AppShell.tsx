import { Link, router, usePage } from '@inertiajs/react'

import { BrandMark } from '@/components/layout/BrandMark'
import { ThemeToggle } from '@/components/layout/ThemeToggle'
import { Button } from '@/components/ui/button'
import { initialsOf } from '@/lib/initials'
import type { SharedProps } from '@/types'

export function AppShell({ children }: { children: React.ReactNode }) {
  const { auth } = usePage<SharedProps>().props

  return (
    <div className="flex min-h-screen flex-col">
      <a
        href="#content"
        className="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-control focus:bg-surface focus:px-3 focus:py-2 focus:shadow-lift"
      >
        Skip to content
      </a>

      <header className="sticky top-0 z-40 border-b border-border bg-background/85 backdrop-blur">
        <div className="mx-auto flex h-14 w-full max-w-6xl items-center gap-4 px-4 sm:px-6">
          <Link
            href="/dashboard"
            className="flex items-center gap-2 font-semibold tracking-tight text-foreground"
          >
            <BrandMark className="text-primary" />
            Touchline
          </Link>

          <div className="ml-auto flex items-center gap-3">
            <ThemeToggle />

            {auth.user ? (
              <>
                <span
                  title={auth.user.email}
                  className="grid size-8 place-items-center rounded-full bg-primary-wash text-[12px] font-semibold text-primary"
                >
                  {initialsOf(auth.user.first_name, auth.user.last_name, auth.user.email)}
                </span>

                <Button
                  variant="ghost"
                  size="sm"
                  onClick={() => router.post('/sign-out')}
                >
                  Sign out
                </Button>
              </>
            ) : null}
          </div>
        </div>
      </header>

      <main id="content" className="mx-auto w-full max-w-6xl flex-1 px-4 py-8 sm:px-6">
        {children}
      </main>
    </div>
  )
}
