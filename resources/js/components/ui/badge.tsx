import { cva, type VariantProps } from 'class-variance-authority'
import type { ReactNode } from 'react'

import { cn } from '@/lib/cn'
import type { OrganizationRole } from '@/types'

const badge = cva(
  'inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide',
  {
    variants: {
      tone: {
        primary: 'bg-primary-wash text-primary',
        neutral: 'bg-surface-muted text-foreground-muted',
        outline: 'border border-border-strong text-foreground-muted',
      },
    },
    defaultVariants: { tone: 'neutral' },
  },
)

export function Badge({
  tone,
  className,
  children,
}: VariantProps<typeof badge> & { className?: string; children: ReactNode }) {
  return <span className={cn(badge({ tone }), className)}>{children}</span>
}

/**
 * The owner is the only role worth picking out visually — it is the one that cannot be
 * reassigned through the members list, so it is the one worth being able to spot in one.
 */
export function RoleBadge({ role }: { role: OrganizationRole }) {
  return <Badge tone={role === 'OWNER' ? 'primary' : 'neutral'}>{role.toLowerCase()}</Badge>
}
