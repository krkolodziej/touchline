import { Head, Link } from '@inertiajs/react'

import { DataTable, type Column } from '@/components/data/DataTable'
import { PageHeading } from '@/components/data/PageHeading'
import { Statistic } from '@/components/data/SectionCard'
import { CaptainBadge, PositionBadge } from '@/components/domain/PositionBadge'
import type { CurrentSquad, NamedRef, Player, PlayerSeasonRow } from '@/types'

export default function PlayerShow({
  organization,
  player,
  seasons,
  totals,
  current,
}: {
  organization: NamedRef
  player: Player
  seasons: PlayerSeasonRow[]
  totals: { goals: number; yellow_cards: number; red_cards: number }
  current: CurrentSquad | null
}) {
  const base = `/organizations/${organization.id}`

  const columns: Column<PlayerSeasonRow>[] = [
    {
      key: 'season',
      header: 'Season',
      render: (row) => (
        <Link
          href={`${base}/leagues/${row.league_id}/seasons/${row.season_id}/statistics`}
          className="font-medium hover:text-primary"
        >
          {row.season_name}
        </Link>
      ),
    },
    {
      key: 'club',
      header: 'Club',
      render: (row) => (
        <Link
          href={`${base}/clubs/${row.team_id}/profile`}
          className="text-foreground-muted hover:text-primary"
        >
          {row.team_name}
        </Link>
      ),
    },
    {
      key: 'shirt',
      header: 'No.',
      align: 'right',
      secondary: true,
      render: (row) => <span className="tabular">{row.shirt_number ?? '—'}</span>,
    },
    {
      key: 'position',
      header: 'Pos',
      secondary: true,
      render: (row) => <PositionBadge position={row.position} />,
    },
    {
      key: 'goals',
      header: 'Goals',
      align: 'right',
      render: (row) => <span className="tabular font-semibold">{row.goals}</span>,
    },
    {
      key: 'yellow',
      header: 'Yellow',
      align: 'right',
      secondary: true,
      render: (row) => <span className="tabular">{row.yellow_cards}</span>,
    },
    {
      key: 'red',
      header: 'Red',
      align: 'right',
      secondary: true,
      render: (row) => <span className="tabular">{row.red_cards}</span>,
    },
  ]

  return (
    <>
      <Head title={player.full_name} />

      <div className="flex flex-col gap-6">
        <nav aria-label="Breadcrumb" className="text-[13px] text-foreground-muted">
          <Link href={`${base}/players`} className="hover:text-foreground">
            {organization.name}
          </Link>
        </nav>

        <PageHeading
          title={player.full_name}
          subtitle={
            player.date_of_birth === null
              ? 'Date of birth not recorded'
              : `Born ${player.date_of_birth}${player.age === null ? '' : ` · ${player.age}`}`
          }
          actions={
            current === null ? (
              <span className="text-[13px] text-foreground-subtle">Unattached</span>
            ) : (
              <div className="flex items-center gap-2">
                {current.shirt_number === null ? null : (
                  <span className="tabular text-[13px] text-foreground-subtle">
                    #{current.shirt_number}
                  </span>
                )}
                <PositionBadge position={current.position} />
                {current.captain ? <CaptainBadge /> : null}
                <Link
                  href={`${base}/clubs/${current.team_id}/profile`}
                  className="text-sm font-medium text-primary hover:underline"
                >
                  {current.team_name}
                </Link>
              </div>
            )
          }
        />

        <div className="grid gap-3 sm:grid-cols-3">
          <Statistic label="Goals" value={totals.goals} />
          <Statistic label="Yellow cards" value={totals.yellow_cards} />
          <Statistic label="Red cards" value={totals.red_cards} />
        </div>

        <section className="flex flex-col gap-3">
          <h2 className="text-lg">Career</h2>

          {seasons.length === 0 ? (
            <p className="text-sm text-foreground-muted">
              This player has not been put in a squad yet.
            </p>
          ) : (
            <DataTable
              caption="Career"
              columns={columns}
              rows={seasons}
              // Keyed on the pair, because a player can appear twice in one season under two
              // clubs — a transfer mid-season is two rows, not one.
              rowKey={(row) => `${row.season_id}:${row.team_id}`}
            />
          )}
        </section>
      </div>
    </>
  )
}
