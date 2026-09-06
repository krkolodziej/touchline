import { cva, type VariantProps } from 'class-variance-authority'
import type { ReactNode } from 'react'

import { cn } from '@/lib/cn'

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
