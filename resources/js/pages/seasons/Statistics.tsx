import { Link } from '@inertiajs/react'
import { useState } from 'react'

import { DataTable, type Column } from '@/components/data/DataTable'
import { EmptyState } from '@/components/data/States'
import { SeasonLayout } from '@/components/seasons/SeasonLayout'
import { Button } from '@/components/ui/button'
import type { PlayerStatisticsRow, SeasonTabProps } from '@/types'

type Sort = 'goals' | 'cards'

export default function Statistics({
  players,
  ...season
}: SeasonTabProps & { players: PlayerStatisticsRow[] }) {
  const [sort, setSort] = useState<Sort>('goals')
  const base = `/organizations/${season.organization.id}`

  // Sorted here rather than by another request: the whole list is already on the page, and a
  // round trip to reorder forty rows is a round trip nobody needs.
  const rows =
    sort === 'goals'
      ? players
      : [...players].sort(
          (a, b) =>
            b.red_cards * 2 + b.yellow_cards - (a.red_cards * 2 + a.yellow_cards) ||
            a.last_name.localeCompare(b.last_name),
        )

  const columns: Column<PlayerStatisticsRow>[] = [
    {
      key: 'player',
      header: 'Player',
      render: (row) => (
        <Link
          href={`${base}/players/${row.player_id}/profile`}
          className="font-medium hover:text-primary"
        >
          {row.first_name} {row.last_name}
        </Link>
      ),
    },
    {
      key: 'club',
      header: 'Club',
      secondary: true,
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
      key: 'goals',
      header: 'Goals',
      align: 'right',
      render: (row) => <span className="tabular font-semibold">{row.goals}</span>,
    },
    {
      key: 'yellow',
      header: 'Yellow',
      align: 'right',
      render: (row) => (
        <span className="tabular inline-flex items-center gap-1.5">
          {/* The two reserved colours, used where they mean what they look like. */}
          <span aria-hidden="true" className="h-3.5 w-2.5 rounded-[2px] bg-booking" />
          {row.yellow_cards}
        </span>
      ),
    },
    {
      key: 'red',
      header: 'Red',
      align: 'right',
      render: (row) => (
        <span className="tabular inline-flex items-center gap-1.5">
          <span aria-hidden="true" className="h-3.5 w-2.5 rounded-[2px] bg-sending-off" />
          {row.red_cards}
        </span>
      ),
    },
  ]

  return (
    <SeasonLayout {...season}>
      <section className="flex flex-col gap-4">
        <div className="flex flex-wrap items-end justify-between gap-3">
          <div>
            <h2 className="text-lg">Statistics</h2>
            <p className="mt-0.5 text-[13px] text-foreground-muted">
              Counted from match events, including matches still being played.
            </p>
          </div>

          <div className="flex items-center gap-1.5">
            <Button
              variant={sort === 'goals' ? 'primary' : 'outline'}
              size="sm"
              onClick={() => setSort('goals')}
            >
              Goals
            </Button>
            <Button
              variant={sort === 'cards' ? 'primary' : 'outline'}
              size="sm"
              onClick={() => setSort('cards')}
            >
              Cards
            </Button>
          </div>
        </div>

        {rows.length === 0 ? (
          <EmptyState
            title="Nothing recorded yet"
            description="Goals and cards appear here as soon as they are recorded in a match."
            action={<span className="text-sm text-foreground-subtle">Nothing to show yet.</span>}
          />
        ) : (
          <DataTable
            caption="Player statistics"
            columns={columns}
            rows={rows}
            rowKey={(row) => row.player_id}
          />
        )}
      </section>
    </SeasonLayout>
  )
}
