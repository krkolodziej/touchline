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
  type League,
  type ListQueryState,
  type OrganizationTabProps,
  type ResultPage,
} from '@/types'

function CreateLeagueDialog({
  organizationId,
  open,
  onClose,
}: {
  organizationId: number
  open: boolean
  onClose: () => void
}) {
  const form = useForm({ name: '', description: '' })

  const submit = (event: FormEvent) => {
    event.preventDefault()

    form.post(`/organizations/${organizationId}/leagues`, {
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
      title="New league"
      description="A competition this organization runs. Its editions are seasons."
    >
      <form onSubmit={submit} noValidate className="flex flex-col gap-4">
        <Field
          label="Name"
          placeholder="District League"
          value={form.data.name}
          onChange={(event) => form.setData('name', event.target.value)}
          error={form.errors.name}
        />

        <Field
          label="Description"
          hint="Optional."
          value={form.data.description}
          onChange={(event) => form.setData('description', event.target.value)}
          error={form.errors.description}
        />

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

export default function Leagues({
  leagues,
  query,
  ...tabs
}: OrganizationTabProps & {
  leagues: League[] | ResultPage<League>
  query: ListQueryState
}) {
  const [dialogOpen, setDialogOpen] = useState(false)
  const base = `/organizations/${tabs.organization.id}/leagues`
  const { rows, page } = toRows(leagues)
  const { setSearch, setPage } = useListParams(base, { search: query.search, page: query.page })

  const columns: Column<League>[] = [
    {
      key: 'name',
      header: 'League',
      render: (league) => (
        <div className="min-w-0">
          <Link href={`${base}/${league.id}`} className="font-medium hover:text-primary">
            {league.name}
          </Link>
          <p className="truncate text-[12.5px] text-foreground-subtle">{league.slug}</p>
        </div>
      ),
    },
    {
      key: 'description',
      header: 'Description',
      secondary: true,
      render: (league) => (
        <span className="text-foreground-muted">
          {league.description === '' ? '—' : league.description}
        </span>
      ),
    },
    {
      key: 'seasons',
      header: 'Seasons',
      align: 'right',
      render: (league) => <span className="tabular">{league.season_count}</span>,
    },
  ]

  return (
    <OrganizationLayout {...tabs}>
      <CollectionShell
        title="Leagues"
        description="Competitions this organization runs. Each one has seasons."
        search={query.search}
        onSearchChange={setSearch}
        searchPlaceholder="Search leagues"
        isEmpty={rows.length === 0}
        emptyTitle="No leagues yet"
        emptyDescription="A league holds seasons, and a season holds the clubs, the calendar and the results."
        emptyAction={
          tabs.can_manage ? (
            <Button onClick={() => setDialogOpen(true)}>Create the first league</Button>
          ) : (
            <p className="text-sm text-foreground-subtle">An administrator can add one.</p>
          )
        }
        action={
          tabs.can_manage ? (
            <Button onClick={() => setDialogOpen(true)}>
              <Plus className="size-4" />
              New league
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
          caption="Leagues"
          columns={columns}
          rows={rows}
          rowKey={(league) => league.id}
          actions={
            tabs.can_manage
              ? (league) => (
                  <ConfirmDeleteButton
                    href={`${base}/${league.id}`}
                    label={`Delete ${league.name}`}
                    title={`Delete ${league.name}?`}
                    description={
                      league.season_count > 0
                        ? `Its ${league.season_count} season${league.season_count === 1 ? '' : 's'} go with it, along with every result in them.`
                        : 'It has no seasons yet, so nothing else goes with it.'
                    }
                  />
                )
              : undefined
          }
        />
      </CollectionShell>

      <CreateLeagueDialog
        organizationId={tabs.organization.id}
        open={dialogOpen}
        onClose={() => setDialogOpen(false)}
      />
    </OrganizationLayout>
  )
}
