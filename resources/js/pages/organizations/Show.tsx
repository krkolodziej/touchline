import { Head, router, usePage } from '@inertiajs/react'
import { Trash2 } from 'lucide-react'
import { useState } from 'react'

import { PageHeading } from '@/components/data/PageHeading'
import { MembersPanel } from '@/components/organizations/MembersPanel'
import { RoleBadge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Dialog } from '@/components/ui/dialog'
import type { Membership, Organization, SharedProps } from '@/types'

export default function OrganizationShow({
  organization,
  members,
  can_manage: canManage,
  can_delete: canDelete,
}: {
  organization: Organization
  members: Membership[]
  can_manage: boolean
  can_delete: boolean
}) {
  const { errors } = usePage<SharedProps>().props
  const [confirming, setConfirming] = useState(false)

  return (
    <>
      <Head title={organization.name} />

      <div className="flex flex-col gap-8">
        <PageHeading
          title={organization.name}
          subtitle={organization.slug}
          actions={
            <>
              <RoleBadge role={organization.my_role} />

              {/* Deleting takes the whole competition with it, so it needs the one role
                  that cannot be handed out through the members list. */}
              {canDelete ? (
                <Button variant="ghost" onClick={() => setConfirming(true)}>
                  <Trash2 className="size-4" />
                  Delete
                </Button>
              ) : null}
            </>
          }
        />

        <MembersPanel
          organizationId={organization.id}
          members={members}
          canManage={canManage}
          conflict={errors.conflict}
        />
      </div>

      <Dialog
        open={confirming}
        onClose={() => setConfirming(false)}
        title={`Delete ${organization.name}?`}
        description="Every league, club, player and result inside it goes too. This cannot be undone."
      >
        <div className="flex justify-end gap-2">
          <Button variant="ghost" onClick={() => setConfirming(false)}>
            Keep it
          </Button>
          <Button
            variant="danger"
            onClick={() => router.delete(`/organizations/${organization.id}`)}
          >
            Delete for good
          </Button>
        </div>
      </Dialog>
    </>
  )
}
