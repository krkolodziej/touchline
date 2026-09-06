/**
 * A deterministic mark, so the same organization always looks the same without anybody
 * having to upload a crest. The hue comes from the name; lightness and chroma stay fixed,
 * which keeps every mark equally readable in both themes.
 */
export function OrganizationMark({ name, className }: { name: string; className?: string }) {
  let hash = 0

  for (let index = 0; index < name.length; index += 1) {
    hash = (hash * 31 + name.charCodeAt(index)) % 360
  }

  const initials = name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((word) => word[0])
    .join('')
    .toUpperCase()

  return (
    <span
      aria-hidden="true"
      className={
        className ??
        'grid size-10 shrink-0 place-items-center rounded-[var(--radius-control)] text-[13px] font-bold text-white'
      }
      style={{ backgroundColor: `oklch(0.55 0.13 ${hash})` }}
    >
      {initials}
    </span>
  )
}
