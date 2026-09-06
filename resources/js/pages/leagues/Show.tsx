import { Head, Link, useForm } from '@inertiajs/react'
import { CalendarDays, ChevronRight, Plus } from 'lucide-react'
import { useState, type FormEvent } from 'react'

import { ConfirmDeleteButton } from '@/components/data/ConfirmDeleteButton'
import { PageHeading } from '@/components/data/PageHeading'
import { EmptyState } from '@/components/data/States'
import { Button } from '@/components/ui/button'
import { Dialog } from '@/components/ui/dialog'
import { Field } from '@/components/ui/field'
import type { League, NamedRef, Season, SharedProps } from '@/types'
import { usePage } from '@inertiajs/react'

function CreateSeasonDialog({
  base,
  open,
  onClose,
}: {
  base: string
  open: boolean
  onClose: () => void
}) {
  const { errors } = usePage<SharedProps>().props
  const form = useForm({ name: '', start_date: '', end_date: '' })

  const submit = (event: FormEvent) => {
    event.preventDefault()

    form.post(`${base}/seasons`, {
      preserveScroll: true,
      onSuccess: () => {
        form.reset()
        onClose()
      },
    })
  }

  return (
    <Dialog
      open={open}
      onClose={onClose}
      title="New season"
      description="One edition of this league: its clubs, its calendar and its results."
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

        <Field
          label="Name"
          placeholder="2026/27"
          hint="One year, or two consecutive ones."
          value={form.data.name}
          onChange={(event) => form.setData('name', event.target.value)}
          error={form.errors.name}
        />

        <div className="grid gap-4 sm:grid-cols-2">
          <Field
            label="Starts"
            type="date"
            value={form.data.start_date}
            onChange={(event) => form.setData('start_date', event.target.value)}
            error={form.errors.start_date}
          />

          <Field
            label="Ends"
            type="date"
            hint="Optional."
            value={form.data.end_date}
            onChange={(event) => form.setData('end_date', event.target.value)}
            error={form.errors.end_date}
          />
        </div>

        <div className="flex justify-end gap-2">
          <Button type="button" variant="ghost" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" disabled={form.processing}>
            {form.processing ? 'Creating…' : 'Create'}
          </Button>
        </div>
      </form>
    </Dialog>
  )
}

export default function LeagueShow({
  organization,
  league,
  seasons,
  can_manage: canManage,
}: {
  organization: NamedRef
  league: League
  seasons: Season[]
  can_manage: boolean
}) {
  const [dialogOpen, setDialogOpen] = useState(false)
  const base = `/organizations/${organization.id}/leagues/${league.id}`

  return (
    <>
      <Head title={league.name} />

      <div className="flex flex-col gap-6">
        <nav aria-label="Breadcrumb" className="text-[13px] text-foreground-muted">
          <Link href={`/organizations/${organization.id}/leagues`} className="hover:text-foreground">
            {organization.name}
          </Link>
        </nav>

        <PageHeading
          title={league.name}
          subtitle={league.description === '' ? league.slug : league.description}
          actions={
            canManage ? (
              <Button onClick={() => setDialogOpen(true)}>
                <Plus className="size-4" />
                New season
              </Button>
            ) : undefined
          }
        />

        {seasons.length === 0 ? (
          <EmptyState
            title="No seasons yet"
            description="A season holds the clubs, their squads, the calendar and everything played against it."
            action={
              canManage ? (
                <Button onClick={() => setDialogOpen(true)}>Create the first season</Button>
              ) : (
                <p className="text-sm text-foreground-subtle">An administrator can add one.</p>
              )
            }
          />
        ) : (
          <ul className="grid gap-3 sm:grid-cols-2">
            {seasons.map((season) => (
              <li key={season.id} className="surface-panel group flex items-center gap-4 p-4">
                <Link
                  href={`${base}/seasons/${season.id}`}
                  className="flex min-w-0 flex-1 items-center gap-4"
                >
                  <span
                    aria-hidden="true"
                    className="grid size-10 shrink-0 place-items-center rounded-[var(--radius-control)] bg-primary-wash text-primary"
                  >
                    <CalendarDays className="size-5" />
                  </span>

                  <span className="min-w-0 flex-1">
                    <span className="block truncate text-[15px] font-semibold">{season.name}</span>
                    <span className="mt-0.5 block truncate text-[13px] text-foreground-subtle tabular">
                      {season.end_date === null
                        ? `from ${season.start_date}`
                        : `${season.start_date} — ${season.end_date}`}
                      {' · '}
                      {season.club_count} {season.club_count === 1 ? 'club' : 'clubs'}
                    </span>
                  </span>

                  <ChevronRight className="size-4 shrink-0 text-foreground-subtle transition-transform duration-150 group-hover:translate-x-0.5" />
                </Link>

                {canManage ? (
                  <ConfirmDeleteButton
                    href={`${base}/seasons/${season.id}`}
                    label={`Delete ${season.name}`}
                    title={`Delete ${season.name}?`}
                    description="Its clubs, squads, calendar and every result in it go too."
                  />
                ) : null}
              </li>
            ))}
          </ul>
        )}

        <CreateSeasonDialog base={base} open={dialogOpen} onClose={() => setDialogOpen(false)} />
      </div>
    </>
  )
}
