import type { ReactNode } from 'react'

import { SearchInput } from '@/components/data/SearchInput'
import { EmptyState } from '@/components/data/States'

/**
 * The chrome every collection screen has: a heading, a search box, one of three states, and
 * pagination underneath. Written once so that "empty", "empty because you searched" and
 * "here they are" behave the same everywhere.
 *
 * No loading or error state: a page arrives with its rows already in it, so there is no
 * moment where the list exists but its contents do not.
 */
export function CollectionShell({
  title,
  description,
  action,
  search,
  onSearchChange,
  searchPlaceholder,
  isEmpty,
  emptyTitle,
  emptyDescription,
  emptyAction,
  pagination,
  children,
}: {
  title: string
  description: string
  action?: ReactNode
  search: string
  onSearchChange: (value: string) => void
  searchPlaceholder: string
  isEmpty: boolean
  emptyTitle: string
  emptyDescription: string
  emptyAction: ReactNode
  pagination?: ReactNode
  children: ReactNode
}) {
  const searching = search !== ''

  return (
    <section className="flex flex-col gap-4">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h2 className="text-lg">{title}</h2>
          <p className="mt-0.5 text-[13px] text-foreground-muted">{description}</p>
        </div>
        {action}
      </div>

      <SearchInput value={search} onChange={onSearchChange} placeholder={searchPlaceholder} />

      {isEmpty ? (
        // Two different nothings. "No results for that search" is a dead end you got to on
        // purpose; "nothing here yet" is an invitation.
        searching ? (
          <EmptyState
            title="Nothing matched"
            description={`No ${title.toLowerCase()} match "${search}".`}
            action={
              <button
                type="button"
                onClick={() => onSearchChange('')}
                className="text-sm font-medium text-primary hover:underline"
              >
                Clear the search
              </button>
            }
          />
        ) : (
          <EmptyState title={emptyTitle} description={emptyDescription} action={emptyAction} />
        )
      ) : (
        children
      )}

      {pagination}
    </section>
  )
}
