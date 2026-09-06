import { cn } from '@/lib/cn'

/* A pitch seen from above: the halfway line, the centre circle, the two goals. Drawn
   rather than imported so it inherits currentColor and needs no second asset in dark. */
export function BrandMark({ className }: { className?: string }) {
  return (
    <svg
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth={1.6}
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
      className={cn('size-6', className)}
    >
      <rect x="2" y="4.5" width="20" height="15" rx="2" />
      <path d="M12 4.5v15" />
      <circle cx="12" cy="12" r="3" />
      <path d="M2 9h2.5v6H2M22 9h-2.5v6H22" />
    </svg>
  )
}
