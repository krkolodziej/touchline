import { useForm } from '@inertiajs/react'
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
  type Player,
  type ResultPage,
} from '@/types'

function CreatePlayerDialog({
  organizationId,
  open,
  onClose,
}: {
  organizationId: number
  open: boolean
  onClose: () => void
}) {
  const form = useForm({ first_name: '', last_name: '', date_of_birth: '' })

  const submit = (event: FormEvent) => {
    event.preventDefault()

    form.post(`/organizations/${organizationId}/players`, {
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
      title="New player"
      description="A person, not a squad place. Which club and which number is a season fact."
    >
      <form onSubmit={submit} noValidate className="flex flex-col gap-4">
        <div className="grid gap-4 sm:grid-cols-2">
          <Field
            label="First name"
            value={form.data.first_name}
            onChange={(event) => form.setData('first_name', event.target.value)}
            error={form.errors.first_name}
          />

          <Field
            label="Last name"
            value={form.data.last_name}
            onChange={(event) => form.setData('last_name', event.target.value)}
            error={form.errors.last_name}
          />
        </div>

        <Field
          label="Date of birth"
          type="date"
          hint="Optional — players are often registered before anybody has it."
          value={form.data.date_of_birth}
          onChange={(event) => form.setData('date_of_birth', event.target.value)}
          error={form.errors.date_of_birth}
        />

        <div className="flex justify-end gap-2">
          <Button type="button" variant="ghost" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" disabled={form.processing}>
            {form.processing ? 'Adding…' : 'Add player'}
          </Button>
        </div>
      </form>
    </Dialog>
  )
}

export default function Players({
  players,
  query,
  ...tabs
}: OrganizationTabProps & {
  players: Player[] | ResultPage<Player>
  query: ListQueryState
}) {
  const [dialogOpen, setDialogOpen] = useState(false)
  const base = `/organizations/${tabs.organization.id}/players`
  const { rows, page } = toRows(players)
  const { setSearch, setPage } = useListParams(base, { search: query.search, page: query.page })

  const columns: Column<Player>[] = [
    {
      key: 'name',
      header: 'Player',
      render: (player) => <span className="font-medium">{player.full_name}</span>,
    },
    {
      key: 'date_of_birth',
      header: 'Born',
      secondary: true,
      render: (player) => (
        <span className="tabular text-foreground-muted">{player.date_of_birth ?? '—'}</span>
      ),
    },
    {
      key: 'age',
      header: 'Age',
      align: 'right',
      render: (player) => <span className="tabular">{player.age ?? '—'}</span>,
    },
  ]

  return (
    <OrganizationLayout {...tabs}>
      <CollectionShell
        title="Players"
        description="People, held by the organization rather than by any one club."
        search={query.search}
        onSearchChange={setSearch}
        searchPlaceholder="Search players"
        isEmpty={rows.length === 0}
        emptyTitle="No players yet"
        emptyDescription="Add people here once; putting them in a squad for a season comes next."
        emptyAction={
          tabs.can_manage ? (
            <Button onClick={() => setDialogOpen(true)}>Add the first player</Button>
          ) : (
            <p className="text-sm text-foreground-subtle">An administrator can add one.</p>
          )
        }
        action={
          tabs.can_manage ? (
            <Button onClick={() => setDialogOpen(true)}>
              <Plus className="size-4" />
              New player
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
          caption="Players"
          columns={columns}
          rows={rows}
          rowKey={(player) => player.id}
          actions={
            tabs.can_manage
              ? (player) => (
                  <ConfirmDeleteButton
                    href={`${base}/${player.id}`}
                    label={`Delete ${player.full_name}`}
                    title={`Delete ${player.full_name}?`}
                    description="They are removed from every squad they were in."
                  />
                )
              : undefined
          }
        />
      </CollectionShell>

      <CreatePlayerDialog
        organizationId={tabs.organization.id}
        open={dialogOpen}
        onClose={() => setDialogOpen(false)}
      />
    </OrganizationLayout>
  )
}
