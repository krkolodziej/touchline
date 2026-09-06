import { Head, Link, router, useForm, usePage } from '@inertiajs/react'
import { ChevronRight } from 'lucide-react'
import { useEffect, useState, type FormEvent } from 'react'

import { MatchStatusBadge } from '@/components/domain/MatchStatusBadge'
import { MatchTimeline } from '@/components/domain/MatchTimeline'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/cn'
import { formatKickOff } from '@/lib/datetime'
import {
  MATCH_EVENT_TYPES,
  type Fixture,
  type MatchEvent,
  type MatchEventType,
  type NamedRef,
  type SharedProps,
  type SquadForMatch,
} from '@/types'

const CONTROL =
  'h-9 rounded-[var(--radius-control)] border border-border-strong bg-surface px-2 text-[13px] focus-visible:border-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/25'

/** The five verbs, in the order somebody running a match would reach for them. */
const VERBS: { status: MatchEventTarget; verb: string; label: string; danger?: boolean }[] = [
  { status: 'LIVE', verb: 'start', label: 'Kick off' },
  { status: 'FINISHED', verb: 'finish', label: 'Full time' },
  { status: 'POSTPONED', verb: 'postpone', label: 'Postpone' },
  { status: 'SCHEDULED', verb: 'reschedule', label: 'Back to calendar' },
  { status: 'CANCELLED', verb: 'cancel', label: 'Cancel', danger: true },
]

type MatchEventTarget = Fixture['allowed_transitions'][number]

const EVENT_LABEL: Record<MatchEventType, string> = {
  GOAL: 'Goal',
  YELLOW_CARD: 'Yellow card',
  RED_CARD: 'Red card',
  SUBSTITUTION: 'Substitution',
}

/**
 * A live match refreshes itself every three seconds.
 *
 * A partial reload rather than a whole page: only the fixture and its events come back, so
 * the form somebody is halfway through filling in is not thrown away underneath them.
 */
function useLiveRefresh(live: boolean) {
  useEffect(() => {
    if (!live) {
      return
    }

    const timer = setInterval(() => {
      router.reload({ only: ['fixture', 'events'] })
    }, 3000)

    return () => clearInterval(timer)
  }, [live])
}

function Scoreboard({ fixture }: { fixture: Fixture }) {
  const played = fixture.status === 'FINISHED' || fixture.status === 'LIVE'

  return (
    <div className="surface-panel grid grid-cols-[1fr_auto_1fr] items-center gap-4 p-6">
      <p className="truncate text-right text-lg font-semibold">{fixture.home_team_name}</p>

      <p className="tabular text-center text-4xl font-semibold">
        {played ? `${fixture.home_score} – ${fixture.away_score}` : '–'}
      </p>

      <p className="truncate text-lg font-semibold">{fixture.away_team_name}</p>
    </div>
  )
}

function EventForm({ base, squads }: { base: string; squads: SquadForMatch[] }) {
  const { errors } = usePage<SharedProps>().props
  const form = useForm({
    type: 'GOAL' as MatchEventType,
    minute: '',
    team_id: String(squads[0]?.team_id ?? ''),
    player_id: '',
    related_player_id: '',
  })

  const squad = squads.find((entry) => String(entry.team_id) === form.data.team_id) ?? squads[0]
  const players = squad?.players ?? []

  const submit = (event: FormEvent) => {
    event.preventDefault()

    form.post(`${base}/events`, {
      preserveScroll: true,
      onSuccess: () => form.reset('minute', 'player_id', 'related_player_id'),
    })
  }

  const label = (player: { full_name: string; shirt_number: number | null }) =>
    player.shirt_number === null ? player.full_name : `${player.shirt_number}. ${player.full_name}`

  return (
    <form onSubmit={submit} noValidate className="surface-panel flex flex-col gap-3 p-4">
      <h2 className="text-[15px] font-semibold">Record something</h2>

      <div className="flex flex-wrap items-end gap-3">
        <div className="flex flex-col gap-1.5">
          <label htmlFor="event-club" className="text-[13px] font-medium text-foreground-muted">
            Club
          </label>
          <select
            id="event-club"
            value={form.data.team_id}
            onChange={(event) => {
              form.setData('team_id', event.target.value)
              // The player list is about to change under it, and a player from the other
              // club would be refused by the server anyway.
              form.setData('player_id', '')
              form.setData('related_player_id', '')
            }}
            className={CONTROL}
          >
            {squads.map((entry) => (
              <option key={entry.team_id} value={entry.team_id}>
                {entry.team_name}
              </option>
            ))}
          </select>
        </div>

        <div className="flex flex-col gap-1.5">
          <label htmlFor="event-type" className="text-[13px] font-medium text-foreground-muted">
            What happened
          </label>
          <select
            id="event-type"
            value={form.data.type}
            onChange={(event) => form.setData('type', event.target.value as MatchEventType)}
            className={CONTROL}
          >
            {MATCH_EVENT_TYPES.map((type) => (
              <option key={type} value={type}>
                {EVENT_LABEL[type]}
              </option>
            ))}
          </select>
        </div>

        <div className="flex w-20 flex-col gap-1.5">
          <label htmlFor="event-minute" className="text-[13px] font-medium text-foreground-muted">
            Minute
          </label>
          <input
            id="event-minute"
            type="number"
            min={1}
            max={180}
            value={form.data.minute}
            onChange={(event) => form.setData('minute', event.target.value)}
            className={cn(CONTROL, 'tabular')}
          />
        </div>

        <div className="flex min-w-48 flex-1 flex-col gap-1.5">
          <label htmlFor="event-player" className="text-[13px] font-medium text-foreground-muted">
            {form.data.type === 'SUBSTITUTION' ? 'Coming off' : 'Player'}
          </label>
          <select
            id="event-player"
            value={form.data.player_id}
            onChange={(event) => form.setData('player_id', event.target.value)}
            className={CONTROL}
          >
            <option value="">Choose a player</option>
            {players.map((player) => (
              <option key={player.id} value={player.id}>
                {label(player)}
              </option>
            ))}
          </select>
        </div>

        {form.data.type === 'SUBSTITUTION' ? (
          <div className="flex min-w-48 flex-1 flex-col gap-1.5">
            <label htmlFor="event-on" className="text-[13px] font-medium text-foreground-muted">
              Coming on
            </label>
            <select
              id="event-on"
              value={form.data.related_player_id}
              onChange={(event) => form.setData('related_player_id', event.target.value)}
              className={CONTROL}
            >
              <option value="">Choose a player</option>
              {players
                .filter((player) => String(player.id) !== form.data.player_id)
                .map((player) => (
                  <option key={player.id} value={player.id}>
                    {label(player)}
                  </option>
                ))}
            </select>
          </div>
        ) : null}

        <Button type="submit" disabled={form.processing}>
          Record
        </Button>
      </div>

      {form.errors.minute ??
      form.errors.player_id ??
      form.errors.related_player_id ??
      form.errors.team_id ??
      errors.conflict ? (
        <p role="alert" className="text-[12.5px] text-danger">
          {form.errors.minute ??
            form.errors.player_id ??
            form.errors.related_player_id ??
            form.errors.team_id ??
            errors.conflict}
        </p>
      ) : null}
    </form>
  )
}

export default function MatchShow({
  organization,
  league,
  season,
  fixture,
  events,
  squads,
  can_manage: canManage,
}: {
  organization: NamedRef
  league: NamedRef
  season: NamedRef
  fixture: Fixture
  events: MatchEvent[]
  squads: SquadForMatch[]
  can_manage: boolean
}) {
  const [pending, setPending] = useState<string | null>(null)
  const base = `/organizations/${organization.id}/leagues/${league.id}/seasons/${season.id}/fixtures/${fixture.id}`
  const calendar = `/organizations/${organization.id}/leagues/${league.id}/seasons/${season.id}/fixtures`

  useLiveRefresh(fixture.status === 'LIVE')

  return (
    <>
      <Head title={`${fixture.home_team_name} v ${fixture.away_team_name}`} />

      <div className="flex flex-col gap-5">
        <nav
          aria-label="Breadcrumb"
          className="flex items-center gap-1 text-[13px] text-foreground-muted"
        >
          <Link href={`/organizations/${organization.id}/leagues`} className="hover:text-foreground">
            {organization.name}
          </Link>
          <ChevronRight className="size-3.5 shrink-0 text-foreground-subtle" aria-hidden="true" />
          <Link href={calendar} className="hover:text-foreground">
            {league.name} {season.name}
          </Link>
        </nav>

        <div className="flex flex-wrap items-center justify-between gap-3">
          <div className="flex items-center gap-3">
            <MatchStatusBadge status={fixture.status} />
            <p className="text-[13px] text-foreground-muted">
              Round {fixture.round_number} · {formatKickOff(fixture.kick_off_at)}
            </p>
          </div>

          {/* Every button here comes from `allowed_transitions`, which the server computed.
              The client keeps no copy of the rules, so the two cannot drift apart — and a
              button that would be refused is never drawn. */}
          {canManage && fixture.allowed_transitions.length > 0 ? (
            <div className="flex flex-wrap items-center gap-2">
              {VERBS.filter((entry) => fixture.allowed_transitions.includes(entry.status)).map(
                (entry) => (
                  <Button
                    key={entry.verb}
                    variant={entry.danger ? 'ghost' : 'outline'}
                    size="sm"
                    disabled={pending !== null}
                    onClick={() => {
                      setPending(entry.verb)
                      router.post(
                        `${base}/${entry.verb}`,
                        {},
                        { preserveScroll: true, onFinish: () => setPending(null) },
                      )
                    }}
                    className={entry.danger ? 'hover:text-danger' : undefined}
                  >
                    {entry.label}
                  </Button>
                ),
              )}
            </div>
          ) : null}
        </div>

        <Scoreboard fixture={fixture} />

        {canManage && fixture.status === 'LIVE' ? (
          <EventForm base={base} squads={squads} />
        ) : null}

        <section className="surface-panel px-4 py-2">
          <h2 className="sr-only">Timeline</h2>
          <MatchTimeline events={events} />
        </section>

        {fixture.status === 'LIVE' ? (
          <p className="text-center text-[12.5px] text-foreground-subtle">
            Refreshing every few seconds.
          </p>
        ) : null}
      </div>
    </>
  )
}
