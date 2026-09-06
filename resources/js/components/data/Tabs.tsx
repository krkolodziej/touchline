import { Link, usePage } from '@inertiajs/react'

import { cn } from '@/lib/cn'

export interface TabDefinition {
  href: string
  label: string
  count?: number
}

/**
 * Real links, not buttons with state.
 *
 * The tab is part of the address, so it can be bookmarked, opened in a new window and
 * reached with the back button — and `aria-current` comes from the URL rather than from a
 * hand-managed flag that something else has to remember to update.
 */
export function Tabs({ tabs }: { tabs: TabDefinition[] }) {
  const { url } = usePage()
  const path = url.split('?')[0]

  return (
    <nav aria-label="Sections" className="-mb-px flex gap-1 overflow-x-auto border-b border-border">
      {tabs.map((tab) => {
        const active = path === tab.href

        return (
          <Link
            key={tab.href}
            href={tab.href}
            aria-current={active ? 'page' : undefined}
            className={cn(
              'flex items-center gap-2 whitespace-nowrap border-b-2 px-3 py-2.5 text-sm transition-colors',
              active
                ? 'border-primary font-medium text-foreground'
                : 'border-transparent text-foreground-muted hover:text-foreground',
            )}
          >
            {tab.label}
            {tab.count === undefined ? null : (
              <span className="tabular rounded-full bg-surface-muted px-1.5 py-0.5 text-[11px] text-foreground-subtle">
                {tab.count}
              </span>
            )}
          </Link>
        )
      })}
    </nav>
  )
}
