import { router, useForm, usePage } from '@inertiajs/react'
import { Trash2, UserPlus } from 'lucide-react'
import type { FormEvent } from 'react'

import { EmptyState } from '@/components/data/States'
import { SeasonLayout } from '@/components/seasons/SeasonLayout'
import { CaptainBadge, PositionBadge } from '@/components/domain/PositionBadge'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/cn'
import { positionLabel } from '@/lib/positions'
import {
  PLAYER_POSITIONS,
  type NamedRef,
  type PlayerPosition,
  type RosterEntry,
  type SeasonTabProps,
  type SeasonTeam,
  type SharedProps,
} from '@/types'

const CONTROL =
  'h-9 rounded-[var(--radius-control)] border border-border-strong bg-surface px-2 text-[13px] focus-visible:border-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/25'

interface PlayerRef {
  id: number
  full_name: string
}

function SquadRow({
  entry,
  base,
  canManage,
}: {
  entry: RosterEntry
  base: string
  canManage: boolean
}) {
  const save = (changes: Partial<RosterEntry>) => {
    router.patch(
      `${base}/roster/${entry.id}`,
      {
        player_id: entry.player_id,
        shirt_number: changes.shirt_number === undefined ? entry.shirt_number : changes.shirt_number,
        position: changes.position === undefined ? entry.position : changes.position,
        captain: changes.captain ?? entry.captain,
      },
      { preserveScroll: true, preserveState: false },
    )
  }

  return (
    <li className="flex flex-wrap items-center gap-3 px-4 py-2.5">
      <span className="tabular w-8 shrink-0 text-right text-sm font-semibold text-foreground-muted">
        {entry.shirt_number ?? '–'}
      </span>

      <div className="min-w-0 flex-1">
        <p className="flex items-center gap-2 truncate text-sm font-medium">
          {entry.player_name}
          {entry.captain ? <CaptainBadge /> : null}
        </p>
      </div>

      {canManage ? (
        <>
          <input
            type="number"
            min={1}
            max={99}
            defaultValue={entry.shirt_number ?? ''}
            aria-label={`Shirt number of ${entry.player_name}`}
            // On blur, not on change: typing "1" on the way to "12" would otherwise send a
            // request for a number somebody else may already wear.
            onBlur={(event) => {
              const raw = event.target.value
              const next = raw === '' ? null : Number(raw)

              if (next !== entry.shirt_number) {
                save({ shirt_number: next })
              }
            }}
            className={cn(CONTROL, 'tabular w-16')}
          />

          <select
            value={entry.position ?? ''}
            aria-label={`Position of ${entry.player_name}`}
            onChange={(event) =>
              save({ position: (event.target.value || null) as PlayerPosition | null })
            }
            className={CONTROL}
          >
            <option value="">No position</option>
            {PLAYER_POSITIONS.map((position) => (
              <option key={position} value={position}>
                {positionLabel(position)}
              </option>
            ))}
          </select>

          {/* Naming a captain demotes whoever held it. The button is never disabled
              because the server never refuses this — it just moves the armband. */}
          <Button
            variant={entry.captain ? 'primary' : 'outline'}
            size="sm"
            title="Captain"
            aria-pressed={entry.captain}
            onClick={() => save({ captain: !entry.captain })}
          >
            C
          </Button>

          <Button
            variant="ghost"
            size="icon"
            aria-label={`Remove ${entry.player_name} from the squad`}
            onClick={() =>
              router.delete(`${base}/roster/${entry.id}`, {
                preserveScroll: true,
                preserveState: false,
              })
            }
            className="hover:text-danger"
          >
            <Trash2 className="size-4" />
          </Button>
        </>
      ) : (
        <PositionBadge position={entry.position} />
      )}
    </li>
  )
}

function AddPlayerForm({ base, players }: { base: string; players: PlayerRef[] }) {
  const { errors } = usePage<SharedProps>().props
  const form = useForm({ player_id: '', shirt_number: '', position: '', captain: false })

  const submit = (event: FormEvent) => {
    event.preventDefault()

    form.post(`${base}/roster`, {
      preserveScroll: true,
      preserveState: false,
      onSuccess: () => form.reset(),
    })
  }

  if (players.length === 0) {
    return (
      <p className="px-4 py-3 text-[13px] text-foreground-subtle">
        Everybody in this organization is already in this squad.
      </p>
    )
  }

  return (
    <form
      onSubmit={submit}
      noValidate
      className="flex flex-wrap items-end gap-3 border-t border-border px-4 py-3"
    >
      <div className="flex min-w-48 flex-1 flex-col gap-1.5">
        <label htmlFor="add-player" className="text-[13px] font-medium text-foreground-muted">
          Add a player
        </label>
        <select
          id="add-player"
          value={form.data.player_id}
          onChange={(event) => form.setData('player_id', event.target.value)}
          className={cn(CONTROL, 'h-10 text-sm')}
        >
          <option value="">Choose somebody</option>
          {players.map((player) => (
            <option key={player.id} value={player.id}>
              {player.full_name}
            </option>
          ))}
        </select>
      </div>

      <div className="flex w-20 flex-col gap-1.5">
        <label htmlFor="add-number" className="text-[13px] font-medium text-foreground-muted">
          Number
        </label>
        <input
          id="add-number"
          type="number"
          min={1}
          max={99}
          value={form.data.shirt_number}
          onChange={(event) => form.setData('shirt_number', event.target.value)}
          className={cn(CONTROL, 'tabular h-10')}
        />
      </div>

      <div className="flex flex-col gap-1.5">
        <label htmlFor="add-position" className="text-[13px] font-medium text-foreground-muted">
          Position
        </label>
        <select
          id="add-position"
          value={form.data.position}
          onChange={(event) => form.setData('position', event.target.value)}
          className={cn(CONTROL, 'h-10 text-sm')}
        >
          <option value="">No position</option>
          {PLAYER_POSITIONS.map((position) => (
            <option key={position} value={position}>
              {positionLabel(position)}
            </option>
          ))}
        </select>
      </div>

      <Button type="submit" disabled={form.processing || form.data.player_id === ''}>
        <UserPlus className="size-4" />
        Add
      </Button>

      {form.errors.player_id ?? form.errors.shirt_number ?? errors.conflict ? (
        <p role="alert" className="w-full text-[12.5px] text-danger">
          {form.errors.player_id ?? form.errors.shirt_number ?? errors.conflict}
        </p>
      ) : null}
    </form>
  )
}

export default function Squads({
  registrations,
  selected_id: selectedId,
  roster,
  available_clubs: availableClubs,
  available_players: availablePlayers,
  ...season
}: SeasonTabProps & {
  registrations: SeasonTeam[]
  selected_id: number | null
  roster: RosterEntry[]
  available_clubs: NamedRef[]
  available_players: PlayerRef[]
}) {
  const { errors } = usePage<SharedProps>().props
  const seasonBase = `/organizations/${season.organization.id}/leagues/${season.league.id}/seasons/${season.season.id}`
  const registerForm = useForm({ team_id: '' })
  const selected = registrations.find((registration) => registration.id === selectedId) ?? null

  const register = (event: FormEvent) => {
    event.preventDefault()

    registerForm.post(`${seasonBase}/teams`, {
      preserveScroll: true,
      onSuccess: () => registerForm.reset(),
    })
  }

  return (
    <SeasonLayout {...season}>
      <div className="grid gap-6 lg:grid-cols-[20rem_1fr]">
        <section className="flex flex-col gap-3">
          <h2 className="text-lg">Registered clubs</h2>

          {registrations.length === 0 ? (
            <p className="text-sm text-foreground-muted">
              None yet. A season needs at least two before it can have a calendar.
            </p>
          ) : (
            <ul className="surface-panel divide-y divide-border">
              {registrations.map((registration) => (
                <li key={registration.id} className="flex items-center gap-2 px-3 py-2">
                  {/* A link, not a button: which club is open lives in the URL, so a
                      squad is a page somebody can send rather than a state to describe. */}
                  <a
                    href={`${seasonBase}/squads?club=${registration.id}`}
                    className={cn(
                      'min-w-0 flex-1 truncate text-sm',
                      registration.id === selectedId
                        ? 'font-semibold text-foreground'
                        : 'text-foreground-muted hover:text-foreground',
                    )}
                  >
                    {registration.team_name}
                  </a>

                  <span className="tabular shrink-0 text-[12.5px] text-foreground-subtle">
                    {registration.squad_size}
                  </span>

                  {season.can_manage ? (
                    <Button
                      variant="ghost"
                      size="icon"
                      aria-label={`Withdraw ${registration.team_name}`}
                      onClick={() =>
                        router.delete(`${seasonBase}/teams/${registration.id}`, {
                          preserveScroll: true,
                        })
                      }
                      className="hover:text-danger"
                    >
                      <Trash2 className="size-4" />
                    </Button>
                  ) : null}
                </li>
              ))}
            </ul>
          )}

          {season.can_manage && availableClubs.length > 0 ? (
            <form onSubmit={register} noValidate className="surface-panel flex flex-col gap-2 p-3">
              <label
                htmlFor="register-club"
                className="text-[13px] font-medium text-foreground-muted"
              >
                Register a club
              </label>

              <div className="flex gap-2">
                <select
                  id="register-club"
                  value={registerForm.data.team_id}
                  onChange={(event) => registerForm.setData('team_id', event.target.value)}
                  className={cn(CONTROL, 'h-10 min-w-0 flex-1 text-sm')}
                >
                  <option value="">Choose a club</option>
                  {availableClubs.map((club) => (
                    <option key={club.id} value={club.id}>
                      {club.name}
                    </option>
                  ))}
                </select>

                <Button
                  type="submit"
                  disabled={registerForm.processing || registerForm.data.team_id === ''}
                >
                  Register
                </Button>
              </div>

              {registerForm.errors.team_id ?? errors.conflict ? (
                <p role="alert" className="text-[12.5px] text-danger">
                  {registerForm.errors.team_id ?? errors.conflict}
                </p>
              ) : null}
            </form>
          ) : null}
        </section>

        <section className="flex flex-col gap-3">
          <h2 className="text-lg">{selected === null ? 'Squad' : `${selected.team_name} squad`}</h2>

          {selected === null ? (
            <EmptyState
              title="No club selected"
              description="Register a club for this season, then pick it to build its squad."
              action={<span className="text-sm text-foreground-subtle">Nothing to show yet.</span>}
            />
          ) : (
            <div className="surface-panel">
              {roster.length === 0 ? (
                <p className="px-4 py-6 text-center text-sm text-foreground-muted">
                  Nobody in this squad yet.
                </p>
              ) : (
                <ul className="divide-y divide-border">
                  {roster.map((entry) => (
                    <SquadRow
                      key={entry.id}
                      entry={entry}
                      base={`${seasonBase}/teams/${selected.id}`}
                      canManage={season.can_manage}
                    />
                  ))}
                </ul>
              )}

              {season.can_manage ? (
                <AddPlayerForm
                  base={`${seasonBase}/teams/${selected.id}`}
                  players={availablePlayers}
                />
              ) : null}
            </div>
          )}
        </section>
      </div>
    </SeasonLayout>
  )
}
