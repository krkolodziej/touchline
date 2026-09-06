import { Link } from '@inertiajs/react'

import { Nothing, SectionCard, Statistic } from '@/components/data/SectionCard'
import { MatchStatusBadge } from '@/components/domain/MatchStatusBadge'
import { SeasonLayout } from '@/components/seasons/SeasonLayout'
import { formatKickOff } from '@/lib/datetime'
import type { Fixture, PlayerStatisticsRow, SeasonTabProps, StandingRow } from '@/types'

function FixtureLine({ fixture, href }: { fixture: Fixture; href: string }) {
  return (
    <Link
      href={href}
      className="flex items-center gap-2 border-t border-border py-1.5 text-sm first:border-t-0 hover:text-primary"
    >
      <span className="min-w-0 flex-1 truncate text-right">{fixture.home_team_short_name}</span>

      <span className="tabular w-14 shrink-0 text-center text-[13px]">
        {fixture.status === 'LIVE' || fixture.status === 'FINISHED' ? (
          <span className="font-semibold">
            {fixture.home_score}–{fixture.away_score}
          </span>
        ) : (
          <span className="text-foreground-subtle">v</span>
        )}
      </span>

      <span className="min-w-0 flex-1 truncate">{fixture.away_team_short_name}</span>
    </Link>
  )
}

export default function Overview({
  summary,
  live,
  upcoming,
  top_of_the_table: topOfTheTable,
  leading_scorers: leadingScorers,
  ...season
}: SeasonTabProps & {
  summary: { clubs: number; played: number; goals: number }
  live: Fixture[]
  upcoming: Fixture[]
  top_of_the_table: StandingRow[]
  leading_scorers: PlayerStatisticsRow[]
}) {
  const base = `/organizations/${season.organization.id}/leagues/${season.league.id}/seasons/${season.season.id}`
  const org = `/organizations/${season.organization.id}`

  return (
    <SeasonLayout {...season}>
      <div className="flex flex-col gap-5">
        <div className="grid gap-3 sm:grid-cols-3">
          <Statistic label="Clubs" value={summary.clubs} />
          <Statistic label="Matches played" value={summary.played} />
          <Statistic label="Goals" value={summary.goals} />
        </div>

        {/* Only drawn when something is actually being played. A permanent section that says
            "nothing live" is a section that says nothing. */}
        {live.length > 0 ? (
          <section className="surface-panel px-4 py-3">
            <header className="mb-1 flex items-baseline gap-3">
              <h2 className="text-[15px] font-semibold">Being played now</h2>
              <MatchStatusBadge status="LIVE" />
            </header>

            {live.map((fixture) => (
              <FixtureLine
                key={fixture.id}
                fixture={fixture}
                href={`${base}/fixtures/${fixture.id}`}
              />
            ))}
          </section>
        ) : null}

        <div className="grid gap-3 lg:grid-cols-2">
          <SectionCard title="Top of the table" href={`${base}/table`} linkLabel="Full table">
            {topOfTheTable.length === 0 ? (
              <Nothing>No clubs registered yet.</Nothing>
            ) : (
              <ol>
                {topOfTheTable.map((row) => (
                  <li
                    key={row.team_id}
                    className="flex items-center gap-3 border-t border-border py-1.5 text-sm first:border-t-0"
                  >
                    <span className="tabular w-4 shrink-0 text-right text-foreground-subtle">
                      {row.position}
                    </span>
                    <Link
                      href={`${org}/clubs/${row.team_id}/profile`}
                      className="min-w-0 flex-1 truncate hover:text-primary"
                    >
                      {row.team_name}
                    </Link>
                    <span className="tabular w-8 shrink-0 text-right text-foreground-subtle">
                      {row.played}
                    </span>
                    <span className="tabular w-8 shrink-0 text-right font-semibold">
                      {row.points}
                    </span>
                  </li>
                ))}
              </ol>
            )}
          </SectionCard>

          <SectionCard
            title="Leading scorers"
            href={`${base}/statistics`}
            linkLabel="All statistics"
          >
            {leadingScorers.length === 0 ? (
              <Nothing>No goals recorded yet.</Nothing>
            ) : (
              <ol>
                {leadingScorers.map((row) => (
                  <li
                    key={row.player_id}
                    className="flex items-center gap-3 border-t border-border py-1.5 text-sm first:border-t-0"
                  >
                    <Link
                      href={`${org}/players/${row.player_id}/profile`}
                      className="min-w-0 flex-1 truncate hover:text-primary"
                    >
                      {row.first_name} {row.last_name}
                    </Link>
                    <span className="min-w-0 shrink truncate text-[12.5px] text-foreground-subtle">
                      {row.team_name}
                    </span>
                    <span className="tabular w-6 shrink-0 text-right font-semibold">
                      {row.goals}
                    </span>
                  </li>
                ))}
              </ol>
            )}
          </SectionCard>
        </div>

        <SectionCard title="Coming up" href={`${base}/fixtures`} linkLabel="Full calendar">
          {upcoming.length === 0 ? (
            <Nothing>Nothing scheduled.</Nothing>
          ) : (
            <>
              {upcoming.map((fixture) => (
                <div key={fixture.id}>
                  <FixtureLine fixture={fixture} href={`${base}/fixtures/${fixture.id}`} />
                  <p className="pb-1 text-center text-[11.5px] text-foreground-subtle">
                    {formatKickOff(fixture.kick_off_at)}
                  </p>
                </div>
              ))}
            </>
          )}
        </SectionCard>
      </div>
    </SeasonLayout>
  )
}
