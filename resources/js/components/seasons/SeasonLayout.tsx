import { Head, Link } from '@inertiajs/react'
import { ChevronRight } from 'lucide-react'
import type { ReactNode } from 'react'

import { PageHeading } from '@/components/data/PageHeading'
import { Tabs } from '@/components/data/Tabs'
import type { SeasonTabProps } from '@/types'

function Breadcrumb({ organization, league }: Pick<SeasonTabProps, 'organization' | 'league'>) {
  return (
    <nav
      aria-label="Breadcrumb"
      className="flex items-center gap-1 text-[13px] text-foreground-muted"
    >
      <Link href={`/organizations/${organization.id}/leagues`} className="hover:text-foreground">
        {organization.name}
      </Link>
      <ChevronRight className="size-3.5 shrink-0 text-foreground-subtle" aria-hidden="true" />
      <Link
        href={`/organizations/${organization.id}/leagues/${league.id}`}
        className="hover:text-foreground"
      >
        {league.name}
      </Link>
    </nav>
  )
}

/**
 * A season is the deepest thing anybody links to, so it carries a way back out.
 *
 * The tab strip grows a section per stage. Only the ones that have something behind them
 * are listed: a tab that opens onto "coming soon" is worse than no tab.
 */
export function SeasonLayout({
  organization,
  league,
  season,
  counts,
  children,
}: SeasonTabProps & { children: ReactNode }) {
  const base = `/organizations/${organization.id}/leagues/${league.id}/seasons/${season.id}`

  return (
    <>
      <Head title={`${league.name} ${season.name}`} />

      <div className="flex flex-col gap-6">
        <Breadcrumb organization={organization} league={league} />

        <PageHeading
          eyebrow={league.name}
          title={season.name}
          subtitle={
            season.end_date === null
              ? `From ${season.start_date}`
              : `${season.start_date} to ${season.end_date}`
          }
        />

        <Tabs
          tabs={[
            { href: `${base}/squads`, label: 'Clubs & squads', count: counts.clubs },
            { href: `${base}/fixtures`, label: 'Calendar', count: counts.fixtures },
          ]}
        />

        <div className="pt-2">{children}</div>
      </div>
    </>
  )
}
