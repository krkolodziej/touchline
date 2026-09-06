import { Link, useForm } from '@inertiajs/react'
import { Plus } from 'lucide-react'
import { useState, type FormEvent } from 'react'

import { CollectionShell } from '@/components/data/CollectionShell'
import { ConfirmDeleteButton } from '@/components/data/ConfirmDeleteButton'
import { DataTable, type Column } from '@/components/data/DataTable'
import { Pagination } from '@/components/data/Pagination'
import { OrganizationLayout } from '@/components/organizations/OrganizationLayout'
import { Button } from '@/components/ui/button'
import { Dialog } from '@/components/ui/dialog'
import { Field } from '@/components/ui/field'
import { useListParams } from '@/hooks/useListParams'
import {
  toRows,
  type ListQueryState,
  type OrganizationTabProps,
  type ResultPage,
  type Team,
} from '@/types'

function CreateClubDialog({
  organizationId,
  open,
  onClose,
}: {
  organizationId: number
  open: boolean
  onClose: () => void
}) {
  const form = useForm({ name: '', short_name: '' })

  const submit = (event: FormEvent) => {
    event.preventDefault()

    form.post(`/organizations/${organizationId}/clubs`, {
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
      title="New club"
      description="Registered once and reused: a club is not tied to a league or a season."
    >
      <form onSubmit={submit} noValidate className="flex flex-col gap-4">
        <Field
          label="Name"
          placeholder="Stal Rzeszów"
          value={form.data.name}
          onChange={(event) => form.setData('name', event.target.value)}
          error={form.errors.name}
        />

        <Field
          label="Short name"
          hint="What a fixture list and a league table have room for. Optional."
          placeholder="Stal"
          value={form.data.short_name}
          onChange={(event) => form.setData('short_name', event.target.value)}
          error={form.errors.short_name}
        />

        <div className="flex justify-end gap-2">
          <Button type="button" variant="ghost" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" disabled={form.processing}>
            {form.processing ? 'Registering…' : 'Register'}
          </Button>
        </div>
      </form>
    </Dialog>
  )
}

export default function Clubs({
  clubs,
  query,
  ...tabs
}: OrganizationTabProps & {
  clubs: Team[] | ResultPage<Team>
  query: ListQueryState
}) {
  const [dialogOpen, setDialogOpen] = useState(false)
  const base = `/organizations/${tabs.organization.id}/clubs`
  const { rows, page } = toRows(clubs)
  const { setSearch, setPage } = useListParams(base, { search: query.search, page: query.page })

  const columns: Column<Team>[] = [
    {
      key: 'name',
      header: 'Club',
      render: (club) => (
        <div className="min-w-0">
          <Link href={`${base}/${club.id}/profile`} className="font-medium hover:text-primary">
            {club.name}
          </Link>
          <p className="truncate text-[12.5px] text-foreground-subtle">{club.slug}</p>
        </div>
      ),
    },
    {
      key: 'short_name',
      header: 'Short',
      secondary: true,
      render: (club) => <span className="text-foreground-muted">{club.short_name}</span>,
    },
    {
      key: 'seasons',
      header: 'Seasons',
      align: 'right',
      render: (club) => <span className="tabular">{club.seasons_played}</span>,
    },
  ]

  return (
    <OrganizationLayout {...tabs}>
      <CollectionShell
        title="Clubs"
        description="Registered once and reused, season after season."
        search={query.search}
        onSearchChange={setSearch}
        searchPlaceholder="Search clubs"
        isEmpty={rows.length === 0}
        emptyTitle="No clubs yet"
        emptyDescription="A club is registered here once, then entered into whichever seasons it plays in."
        emptyAction={
          tabs.can_manage ? (
            <Button onClick={() => setDialogOpen(true)}>Register the first club</Button>
          ) : (
            <p className="text-sm text-foreground-subtle">An administrator can add one.</p>
          )
        }
        action={
          tabs.can_manage ? (
            <Button onClick={() => setDialogOpen(true)}>
              <Plus className="size-4" />
              New club
            </Button>
          ) : undefined
        }
        pagination={
          page ? (
            <Pagination
              count={page.count}
              page={page.page}
              pageSize={page.page_size}
              next={page.next}
              previous={page.previous}
              onChange={setPage}
            />
          ) : undefined
        }
      >
        <DataTable
          caption="Clubs"
          columns={columns}
          rows={rows}
          rowKey={(club) => club.id}
          actions={
            tabs.can_manage
              ? (club) => (
                  <ConfirmDeleteButton
                    href={`${base}/${club.id}`}
                    label={`Delete ${club.name}`}
                    title={`Delete ${club.name}?`}
                    description="The club is removed from every season it was registered for."
                  />
                )
              : undefined
          }
        />
      </CollectionShell>

      <CreateClubDialog
        organizationId={tabs.organization.id}
        open={dialogOpen}
        onClose={() => setDialogOpen(false)}
      />
    </OrganizationLayout>
  )
}
