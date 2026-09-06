import { usePage } from '@inertiajs/react'

import { MembersPanel } from '@/components/organizations/MembersPanel'
import { OrganizationLayout } from '@/components/organizations/OrganizationLayout'
import type { Membership, OrganizationTabProps, SharedProps } from '@/types'

export default function Members({
  members,
  ...tabs
}: OrganizationTabProps & { members: Membership[] }) {
  const { errors } = usePage<SharedProps>().props

  return (
    <OrganizationLayout {...tabs}>
      <MembersPanel
        organizationId={tabs.organization.id}
        members={members}
        canManage={tabs.can_manage}
        conflict={errors.conflict}
      />
    </OrganizationLayout>
  )
}
