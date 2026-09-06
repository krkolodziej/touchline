import { router, useForm, usePage } from '@inertiajs/react'
import { CalendarPlus, Trash2 } from 'lucide-react'
import { useState, type FormEvent } from 'react'

import { MatchStatusBadge } from '@/components/domain/MatchStatusBadge'
import { EmptyState } from '@/components/data/States'
import { SeasonLayout } from '@/components/seasons/SeasonLayout'
import { Button } from '@/components/ui/button'
import { Dialog } from '@/components/ui/dialog'
import { Field } from '@/components/ui/field'
import { formatKickOffDay, formatTime } from '@/lib/datetime'
import { cn } from '@/lib/cn'
import type { Fixture, NamedRef, SeasonTabProps, SharedProps } from '@/types'

const CONTROL =
  'h-9 rounded-[var(--radius-control)] border border-border-strong bg-surface px-2 text-[13px] focus-visible:border-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/25'

function FixtureRow({ fixture }: { fixture: Fixture }) {
  const played = fixture.status === 'FINISHED' || fixture.status === 'LIVE'

  return (
    <li className="flex items-center gap-3 px-4 py-2.5 text-sm">
      <span className="min-w-0 flex-1 truncate text-right font-medium">
        {fixture.home_team_name}
      </span>

      <span className="tabular w-20 shrink-0 text-center">
        {played ? (
          <span className="font-semibold">
            {fixture.home_score} – {fixture.away_score}
          </span>
        ) : (
          <span className="text-foreground-subtle">{formatTime(fixture.kick_off_at)}</span>
        )}
      </span>

      <span className="min-w-0 flex-1 truncate font-medium">{fixture.away_team_name}</span>

      <span className="w-24 shrink-0 text-right">
        {/* A scheduled match is the default state and says nothing worth a badge. */}
        {fixture.status === 'SCHEDULED' ? null : <MatchStatusBadge status={fixture.status} />}
      </span>
    </li>
  )
}

function GenerateDialog({
  base,
  open,
  onClose,
  clubCount,
  roundCounts,
  startDate,
}: {
  base: string
  open: boolean
  onClose: () => void
  clubCount: number
  roundCounts: { single: number; double: number }
  startDate: string
}) {
  const { errors } = usePage<SharedProps>().props
  const form = useForm({
    double_round: true,
    first_round_on: startDate,
    days_between_rounds: '7',
  })

  const submit = (event: FormEvent) => {
    event.preventDefault()

    form.post(`${base}/fixtures/generate`, {
      preserveScroll: true,
      onSuccess: onClose,
    })
  }

  // Computed here rather than fetched, so the numbers move as the radio does.
  const rounds = form.data.double_round ? roundCounts.double : roundCounts.single
  const perRound = Math.floor(clubCount / 2)
  const matches = (clubCount * (clubCount - 1)) / (form.data.double_round ? 1 : 2)

  return (
    <Dialog
      open={open}
      onClose={onClose}
      title="Generate the calendar"
      description="Every registered club is paired with every other. It can only be done once."
    >
      <form onSubmit={submit} noValidate className="flex flex-col gap-4">
        {errors.conflict ? (
          <p
            role="alert"
            className="rounded-[var(--radius-control)] border border-danger/30 bg-danger-wash px-3 py-2 text-[13px] text-danger"
          >
            {errors.conflict}
          </p>
        ) : null}

        <fieldset className="flex flex-col gap-2">
          <legend className="text-[13px] font-medium text-foreground-muted">Format</legend>

          {[
            { value: true, label: 'Home and away', hint: 'Everybody plays everybody twice.' },
            { value: false, label: 'Single round', hint: 'Everybody plays everybody once.' },
          ].map((option) => (
            <label
              key={String(option.value)}
              className={cn(
                'flex cursor-pointer items-start gap-3 rounded-[var(--radius-control)] border p-3',
                form.data.double_round === option.value
                  ? 'border-primary bg-primary-wash/40'
                  : 'border-border',
              )}
            >
              <input
                type="radio"
                name="double_round"
                checked={form.data.double_round === option.value}
                onChange={() => form.setData('double_round', option.value)}
                className="mt-0.5 accent-[var(--primary)]"
              />
              <span>
                <span className="block text-sm font-medium">{option.label}</span>
                <span className="block text-[12.5px] text-foreground-muted">{option.hint}</span>
              </span>
            </label>
          ))}
        </fieldset>

        <div className="grid gap-4 sm:grid-cols-2">
          <Field
            label="First round on"
            type="date"
            value={form.data.first_round_on}
            onChange={(event) => form.setData('first_round_on', event.target.value)}
            error={form.errors.first_round_on}
          />

          <Field
            label="Days between rounds"
            type="number"
            min={1}
            max={60}
            value={form.data.days_between_rounds}
            onChange={(event) => form.setData('days_between_rounds', event.target.value)}
            error={form.errors.days_between_rounds}
          />
        </div>

        <p className="tabular rounded-[var(--radius-control)] bg-surface-muted px-3 py-2 text-[13px] text-foreground-muted">
          {clubCount} clubs · {matches} matches · {rounds} rounds · {perRound} per round
        </p>

        <div className="flex justify-end gap-2">
          <Button type="button" variant="ghost" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" disabled={form.processing || clubCount < 2}>
            {form.processing ? 'Generating…' : 'Generate'}
          </Button>
        </div>
      </form>
    </Dialog>
  )
}

export default function Fixtures({
  fixtures,
  clubs,
  filters,
  round_counts: roundCounts,
  ...season
}: SeasonTabProps & {
  fixtures: Fixture[]
  clubs: NamedRef[]
  filters: { round: number | null; team: number | null; status: string | null }
  round_counts: { single: number; double: number }
}) {
  const [generating, setGenerating] = useState(false)
  const [clearing, setClearing] = useState(false)
  const base = `/organizations/${season.organization.id}/leagues/${season.league.id}/seasons/${season.season.id}`

  const rounds = [...new Set(fixtures.map((fixture) => fixture.round_number))].sort(
    (a, b) => a - b,
  )

  const filter = (changes: { round?: string; team?: string }) => {
    const params: Record<string, string> = {}
    const round = changes.round ?? (filters.round === null ? '' : String(filters.round))
    const team = changes.team ?? (filters.team === null ? '' : String(filters.team))

    if (round !== '') params.round = round
    if (team !== '') params.team = team

    router.get(`${base}/fixtures`, params, { preserveState: true, preserveScroll: true, replace: true })
  }

  return (
    <SeasonLayout {...season}>
      <section className="flex flex-col gap-4">
        <div className="flex flex-wrap items-end justify-between gap-3">
          <div>
            <h2 className="text-lg">Calendar</h2>
            <p className="mt-0.5 text-[13px] text-foreground-muted">
              Generated in one step, and only once.
            </p>
          </div>

          {season.can_manage ? (
            <div className="flex items-center gap-2">
              {fixtures.length === 0 ? (
                <Button onClick={() => setGenerating(true)} disabled={season.counts.clubs < 2}>
                  <CalendarPlus className="size-4" />
                  Generate
                </Button>
              ) : (
                <Button variant="ghost" onClick={() => setClearing(true)}>
                  <Trash2 className="size-4" />
                  Clear calendar
                </Button>
              )}
            </div>
          ) : null}
        </div>

        {season.counts.fixtures > 0 ? (
          <div className="flex flex-wrap gap-2">
            <select
              aria-label="Round"
              value={filters.round === null ? '' : String(filters.round)}
              onChange={(event) => filter({ round: event.target.value })}
              className={CONTROL}
            >
              <option value="">Every round</option>
              {rounds.map((round) => (
                <option key={round} value={round}>
                  Round {round}
                </option>
              ))}
            </select>

            <select
              aria-label="Club"
              value={filters.team === null ? '' : String(filters.team)}
              onChange={(event) => filter({ team: event.target.value })}
              className={CONTROL}
            >
              <option value="">Every club</option>
              {clubs.map((club) => (
                <option key={club.id} value={club.id}>
                  {club.name}
                </option>
              ))}
            </select>
          </div>
        ) : null}

        {fixtures.length === 0 ? (
          <EmptyState
            title={season.counts.fixtures === 0 ? 'No calendar yet' : 'Nothing matched'}
            description={
              season.counts.fixtures > 0
                ? 'No matches match those filters.'
                : season.counts.clubs < 2
                  ? 'Register at least two clubs for this season first.'
                  : 'Pair every registered club with every other, in one step.'
            }
            action={
              season.counts.fixtures > 0 ? (
                <button
                  type="button"
                  onClick={() => filter({ round: '', team: '' })}
                  className="text-sm font-medium text-primary hover:underline"
                >
                  Clear the filters
                </button>
              ) : season.can_manage && season.counts.clubs >= 2 ? (
                <Button onClick={() => setGenerating(true)}>Generate the calendar</Button>
              ) : (
                <span className="text-sm text-foreground-subtle">Nothing to show yet.</span>
              )
            }
          />
        ) : (
          <div className="flex flex-col gap-5">
            {rounds.map((round) => {
              const inRound = fixtures.filter((fixture) => fixture.round_number === round)

              return (
                <div key={round} className="flex flex-col gap-2">
                  <div className="flex items-baseline justify-between gap-3">
                    <h3 className="text-[15px] font-semibold">Round {round}</h3>
                    <p className="text-[12.5px] text-foreground-subtle">
                      {formatKickOffDay(inRound[0]?.kick_off_at ?? null)}
                    </p>
                  </div>

                  <ul className="surface-panel divide-y divide-border">
                    {inRound.map((fixture) => (
                      <FixtureRow key={fixture.id} fixture={fixture} />
                    ))}
                  </ul>
                </div>
              )
            })}
          </div>
        )}
      </section>

      <GenerateDialog
        base={base}
        open={generating}
        onClose={() => setGenerating(false)}
        clubCount={season.counts.clubs}
        roundCounts={roundCounts}
        startDate={season.season.start_date}
      />

      <Dialog
        open={clearing}
        onClose={() => setClearing(false)}
        title="Clear the calendar?"
        description="Every match goes, along with everything recorded in them. There is no way back."
      >
        <div className="flex justify-end gap-2">
          <Button variant="ghost" onClick={() => setClearing(false)}>
            Keep it
          </Button>
          <Button
            variant="danger"
            onClick={() =>
              router.delete(`${base}/fixtures`, {
                preserveScroll: true,
                onSuccess: () => setClearing(false),
              })
            }
          >
            Clear it
          </Button>
        </div>
      </Dialog>
    </SeasonLayout>
  )
}
