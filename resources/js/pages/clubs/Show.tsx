import { Head, Link } from '@inertiajs/react'

import { DataTable, type Column } from '@/components/data/DataTable'
import { PageHeading } from '@/components/data/PageHeading'
import { Nothing, SectionCard, Statistic } from '@/components/data/SectionCard'
import { CaptainBadge, PositionBadge } from '@/components/domain/PositionBadge'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/cn'
import type { ClubSeasonRow, NamedRef, PlayerPosition, RosterEntry, Team } from '@/types'

/**
 * A club's own page, outside any season.
 *
 * A club is registered once and reused, so its identity outlives every edition of every
 * competition it has played in — which is exactly why this page cannot live inside one.
 */
export default function ClubShow({
  organization,
  club,
  seasons,
  latest_season_id: latestSeasonId,
  squad,
}: {
  organization: NamedRef
  club: Team
  seasons: ClubSeasonRow[]
  latest_season_id: number | null
  squad: RosterEntry[]
}) {
  const base = `/organizations/${organization.id}`
  const latest = seasons.find((season) => season.season_id === latestSeasonId) ?? null

  const columns: Column<ClubSeasonRow>[] = [
    {
      key: 'season',
      header: 'Season',
      render: (row) => (
        <Link
          href={`${base}/leagues/${row.league_id}/seasons/${row.season_id}/table`}
          className="font-medium hover:text-primary"
        >
          {row.season_name}
        </Link>
      ),
    },
    {
      key: 'league',
      header: 'League',
      secondary: true,
      render: (row) => <span className="text-foreground-muted">{row.league_name}</span>,
    },
    {
      key: 'position',
      header: 'Pos',
      align: 'right',
      render: (row) => <span className="tabular">{row.position ?? '—'}</span>,
    },
    {
      key: 'played',
      header: 'P',
      align: 'right',
      render: (row) => <span className="tabular">{row.played}</span>,
    },
    {
      key: 'record',
      header: 'W–D–L',
      align: 'right',
      secondary: true,
      render: (row) => (
        <span className="tabular">
          {row.won}–{row.drawn}–{row.lost}
        </span>
      ),
    },
    {
      key: 'goal_difference',
      header: 'GD',
      align: 'right',
      render: (row) => (
        <span
          className={cn(
            'tabular',
            row.goal_difference > 0 && 'text-primary',
            row.goal_difference < 0 && 'text-foreground-subtle',
          )}
        >
          {row.goal_difference > 0 ? '+' : ''}
          {row.goal_difference}
        </span>
      ),
    },
    {
      key: 'points',
      header: 'Pts',
      align: 'right',
      render: (row) => <span className="tabular font-semibold">{row.points}</span>,
    },
  ]

  return (
    <>
      <Head title={club.name} />

      <div className="flex flex-col gap-6">
        <nav aria-label="Breadcrumb" className="text-[13px] text-foreground-muted">
          <Link href={`${base}/clubs`} className="hover:text-foreground">
            {organization.name}
          </Link>
        </nav>

        <PageHeading
          title={club.name}
          subtitle={club.slug}
          actions={
            latest !== null && latest.position !== null ? (
              <Badge tone="primary">
                {ordinal(latest.position)} in {latest.season_name}
              </Badge>
            ) : undefined
          }
        />

        <div className="grid gap-3 sm:grid-cols-3">
          <Statistic label="Squad" value={squad.length} />
          <Statistic label="Seasons" value={seasons.length} />
          <Statistic label={`Points in ${latest?.season_name ?? 'the latest season'}`} value={latest?.points ?? 0} />
        </div>

        <SectionCard
          title={latest === null ? 'Squad' : `Squad, ${latest.season_name}`}
          href={
            latest === null
              ? undefined
              : `${base}/leagues/${latest.league_id}/seasons/${latest.season_id}/squads`
          }
          linkLabel={latest === null ? undefined : 'Edit squad'}
        >
          {squad.length === 0 ? (
            <Nothing>This club has no squad registered.</Nothing>
          ) : (
            <ul className="grid gap-x-6 sm:grid-cols-2">
              {squad.map((entry) => (
                <li
                  key={entry.id}
                  className="flex items-center gap-3 border-t border-border py-1.5 text-sm first:border-t-0 sm:[&:nth-child(2)]:border-t-0"
                >
                  <span className="tabular w-6 shrink-0 text-right text-foreground-subtle">
                    {entry.shirt_number ?? '–'}
                  </span>
                  <Link
                    href={`${base}/players/${entry.player_id}/profile`}
                    className="min-w-0 flex-1 truncate hover:text-primary"
                  >
                    {entry.player_name}
                  </Link>
                  {entry.captain ? <CaptainBadge /> : null}
                  <PositionBadge position={entry.position as PlayerPosition | null} />
                </li>
              ))}
            </ul>
          )}
        </SectionCard>

        <section className="flex flex-col gap-3">
          <h2 className="text-lg">Season by season</h2>

          {seasons.length === 0 ? (
            <p className="text-sm text-foreground-muted">
              This club has not been entered in a season yet.
            </p>
          ) : (
            <DataTable
              caption="Season by season"
              columns={columns}
              rows={seasons}
              rowKey={(row) => `${row.season_id}`}
            />
          )}
        </section>
      </div>
    </>
  )
}

function ordinal(position: number): string {
  const remainder = position % 100

  if (remainder >= 11 && remainder <= 13) {
    return `${position}th`
  }

  return `${position}${['th', 'st', 'nd', 'rd'][position % 10] ?? 'th'}`
}
