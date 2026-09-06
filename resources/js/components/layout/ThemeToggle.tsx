import { Monitor, Moon, Sun } from 'lucide-react'

import { useTheme, type ThemePreference } from '@/app/theme-context'
import { cn } from '@/lib/cn'

const OPTIONS: { value: ThemePreference; label: string; Icon: typeof Sun }[] = [
  { value: 'light', label: 'Light', Icon: Sun },
  { value: 'system', label: 'System', Icon: Monitor },
  { value: 'dark', label: 'Dark', Icon: Moon },
]

/* Three states, not two. A two-way toggle cannot express "follow the system", so the
   choice to follow it becomes unreachable once anyone touches the switch. */
export function ThemeToggle() {
  const { preference, setPreference } = useTheme()

  return (
    <div
      role="radiogroup"
      aria-label="Colour theme"
      className="inline-flex items-center gap-0.5 rounded-control border border-border bg-surface-muted p-0.5"
    >
      {OPTIONS.map(({ value, label, Icon }) => (
        <button
          key={value}
          type="button"
          role="radio"
          aria-checked={preference === value}
          aria-label={label}
          title={label}
          onClick={() => setPreference(value)}
          className={cn(
            'rounded-[0.375rem] p-1.5 text-foreground-subtle transition-colors',
            'hover:text-foreground',
            preference === value && 'bg-surface text-foreground shadow-panel',
          )}
        >
          <Icon className="size-4" />
        </button>
      ))}
    </div>
  )
}
