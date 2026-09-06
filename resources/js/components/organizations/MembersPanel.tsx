import { router, useForm } from '@inertiajs/react'
import { Trash2, UserPlus } from 'lucide-react'
import type { FormEvent } from 'react'

import { FormError } from '@/components/layout/AuthLayout'
import { RoleBadge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Field } from '@/components/ui/field'
import { cn } from '@/lib/cn'
import { ASSIGNABLE_ROLES, type Membership } from '@/types'

const SELECT =
  'rounded-[var(--radius-control)] border border-border-strong bg-surface px-2 text-[13px] focus-visible:border-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/25'

function MemberRow({
  membership,
  organizationId,
  canManage,
}: {
  membership: Membership
  organizationId: number
  canManage: boolean
}) {
  // The owner is the one membership the server refuses to change. Locking the controls says
  // so before anybody finds out from a 403 — an affordance, not the rule itself, which lives
  // on the server and would refuse a hand-made request just the same.
  const locked = !canManage || membership.role === 'OWNER'
  const base = `/organizations/${organizationId}/members/${membership.id}`

  return (
    <li className="flex flex-wrap items-center gap-3 px-4 py-3">
      <div className="min-w-0 flex-1">
        <p className="truncate text-sm font-medium">{membership.full_name}</p>
        {membership.full_name === membership.email ? null : (
          <p className="truncate text-[13px] text-foreground-subtle">{membership.email}</p>
        )}
      </div>

      {locked ? (
        <RoleBadge role={membership.role} />
      ) : (
        <select
          aria-label={`Role of ${membership.email}`}
          value={membership.role}
          onChange={(event) =>
            router.patch(base, { role: event.target.value }, { preserveScroll: true })
          }
          className={cn('h-8', SELECT)}
        >
          {ASSIGNABLE_ROLES.map((role) => (
            <option key={role} value={role}>
              {role.toLowerCase()}
            </option>
          ))}
        </select>
      )}

      <Button
        variant="ghost"
        size="icon"
        aria-label={`Remove ${membership.email}`}
        disabled={locked}
        onClick={() => router.delete(base, { preserveScroll: true })}
        className="hover:text-danger"
      >
        <Trash2 className="size-4" />
      </Button>
    </li>
  )
}

export function MembersPanel({
  organizationId,
  members,
  canManage,
  conflict,
}: {
  organizationId: number
  members: Membership[]
  canManage: boolean
  conflict?: string
}) {
  const form = useForm({ email: '', role: 'MEMBER' })

  const submit = (event: FormEvent) => {
    event.preventDefault()

    form.post(`/organizations/${organizationId}/members`, {
      preserveScroll: true,
      onSuccess: () => form.reset('email'),
    })
  }

  return (
    <section className="flex flex-col gap-4">
      <div className="flex items-end justify-between gap-4 border-b border-border pb-3">
        <div>
          <h2 className="text-lg">Members</h2>
          <p className="mt-0.5 text-[13px] text-foreground-muted">
            Owners and admins may edit the competition. Members can read it.
          </p>
        </div>
      </div>

      <ul className="surface-panel divide-y divide-border">
        {members.map((membership) => (
          <MemberRow
            key={membership.id}
            membership={membership}
            organizationId={organizationId}
            canManage={canManage}
          />
        ))}
      </ul>

      {canManage ? (
        <form onSubmit={submit} noValidate className="surface-panel flex flex-col gap-4 p-5">
          <div>
            <h3 className="text-[15px] font-semibold">Add someone</h3>
            <p className="mt-0.5 text-[13px] text-foreground-muted">
              They need an account already — there is no invitation by email yet.
            </p>
          </div>

          <FormError message={conflict} />

          <div className="grid gap-4 sm:grid-cols-[1fr_10rem]">
            <Field
              label="Email"
              type="email"
              autoComplete="off"
              placeholder="colleague@example.com"
              value={form.data.email}
              onChange={(event) => form.setData('email', event.target.value)}
              error={form.errors.email}
            />

            <div className="flex flex-col gap-1.5">
              <label
                htmlFor="new-member-role"
                className="text-[13px] font-medium text-foreground-muted"
              >
                Role
              </label>
              <select
                id="new-member-role"
                value={form.data.role}
                onChange={(event) => form.setData('role', event.target.value)}
                className={cn('h-10 text-sm', SELECT)}
              >
                {ASSIGNABLE_ROLES.map((role) => (
                  <option key={role} value={role}>
                    {role.toLowerCase()}
                  </option>
                ))}
              </select>
            </div>
          </div>

          <div className="flex justify-end">
            <Button type="submit" disabled={form.processing}>
              <UserPlus className="size-4" />
              {form.processing ? 'Adding…' : 'Add member'}
            </Button>
          </div>
        </form>
      ) : null}
    </section>
  )
}
