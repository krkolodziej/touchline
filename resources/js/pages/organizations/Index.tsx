import { Head, Link } from '@inertiajs/react'
import { ChevronRight, Plus, Users } from 'lucide-react'
import { useState } from 'react'

import { PageHeading } from '@/components/data/PageHeading'
import { EmptyState } from '@/components/data/States'
import { CreateOrganizationDialog } from '@/components/organizations/CreateOrganizationDialog'
import { OrganizationMark } from '@/components/organizations/OrganizationMark'
import { Badge, RoleBadge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import type { Organization } from '@/types'

function OrganizationCard({ organization }: { organization: Organization }) {
  return (
    <Link
      href={`/organizations/${organization.id}`}
      className="surface-panel group flex items-center gap-4 p-4 transition-[border-color,box-shadow] duration-150 hover:border-border-strong hover:shadow-[var(--shadow-lift)]"
    >
      <OrganizationMark name={organization.name} />

      <div className="min-w-0 flex-1">
        <p className="truncate text-[15px] font-semibold">{organization.name}</p>
        <p className="mt-0.5 truncate text-[13px] text-foreground-subtle">{organization.slug}</p>
      </div>

      <div className="flex shrink-0 items-center gap-3">
        <Badge tone="outline">
          <Users className="mr-1 size-3" />
          {organization.member_count}
        </Badge>
        <RoleBadge role={organization.my_role} />
        <ChevronRight className="size-4 text-foreground-subtle transition-transform duration-150 group-hover:translate-x-0.5" />
      </div>
    </Link>
  )
}

export default function OrganizationsIndex({ organizations }: { organizations: Organization[] }) {
  const [dialogOpen, setDialogOpen] = useState(false)

  return (
    <>
      <Head title="Your organizations" />

      <div className="flex flex-col gap-8">
        <PageHeading
          title="Your organizations"
          subtitle="Every association and club office you belong to."
          actions={
            <Button onClick={() => setDialogOpen(true)}>
              <Plus className="size-4" />
              New organization
            </Button>
          }
        />

        {organizations.length === 0 ? (
          <EmptyState
            title="No organizations yet"
            description="An organization holds your leagues, clubs and players, and decides who may edit them."
            action={
              <Button onClick={() => setDialogOpen(true)}>Create your first organization</Button>
            }
          />
        ) : (
          <div className="grid gap-3 sm:grid-cols-2">
            {organizations.map((organization) => (
              <OrganizationCard key={organization.id} organization={organization} />
            ))}
          </div>
        )}

        <CreateOrganizationDialog open={dialogOpen} onClose={() => setDialogOpen(false)} />
      </div>
    </>
  )
}
