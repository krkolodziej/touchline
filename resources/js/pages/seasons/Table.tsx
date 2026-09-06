import { Link } from '@inertiajs/react'

import { DataTable, type Column } from '@/components/data/DataTable'
import { EmptyState } from '@/components/data/States'
import { SeasonLayout } from '@/components/seasons/SeasonLayout'
import { cn } from '@/lib/cn'
import type { SeasonTabProps, StandingRow } from '@/types'

export default function Table({
  standings,
  ...season
}: SeasonTabProps & { standings: StandingRow[] }) {
  const base = `/organizations/${season.organization.id}`

  const columns: Column<StandingRow>[] = [
    {
      key: 'position',
      header: '#',
      render: (row) => <span className="tabular text-foreground-subtle">{row.position}</span>,
    },
    {
      key: 'club',
      header: 'Club',
      render: (row) => (
        <Link href={`${base}/clubs/${row.team_id}/profile`} className="font-medium hover:text-primary">
          {row.team_name}
        </Link>
      ),
    },
    {
      key: 'played',
      header: 'P',
      align: 'right',
      render: (row) => <span className="tabular">{row.played}</span>,
    },
    {
      key: 'won',
      header: 'W',
      align: 'right',
      secondary: true,
      render: (row) => <span className="tabular">{row.won}</span>,
    },
    {
      key: 'drawn',
      header: 'D',
      align: 'right',
      secondary: true,
      render: (row) => <span className="tabular">{row.drawn}</span>,
    },
    {
      key: 'lost',
      header: 'L',
      align: 'right',
      secondary: true,
      render: (row) => <span className="tabular">{row.lost}</span>,
    },
    {
      key: 'goals_for',
      header: 'GF',
      align: 'right',
      secondary: true,
      render: (row) => <span className="tabular">{row.goals_for}</span>,
    },
    {
      key: 'goals_against',
      header: 'GA',
      align: 'right',
      secondary: true,
      render: (row) => <span className="tabular">{row.goals_against}</span>,
    },
    {
      key: 'goal_difference',
      header: 'GD',
      align: 'right',
      render: (row) => (
        // Signed, and coloured only by sign — the number carries the meaning either way.
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
    <SeasonLayout {...season}>
      <section className="flex flex-col gap-4">
        <div>
          <h2 className="text-lg">Table</h2>
          <p className="mt-0.5 text-[13px] text-foreground-muted">
            Three points for a win, one for a draw. Finished matches only — a match at the hour
            is not a result yet.
          </p>
        </div>

        {standings.length === 0 ? (
          <EmptyState
            title="Nothing to rank yet"
            description="Register the clubs playing this season and the table builds itself from the results."
            action={
              <Link
                href={`${base}/leagues/${season.league.id}/seasons/${season.season.id}/squads`}
                className="text-sm font-medium text-primary hover:underline"
              >
                Register clubs
              </Link>
            }
          />
        ) : (
          <DataTable
            caption="League table"
            columns={columns}
            rows={standings}
            rowKey={(row) => row.team_id}
          />
        )}
      </section>
    </SeasonLayout>
  )
}
