import { router } from '@inertiajs/react'
import { useCallback } from 'react'

/**
 * List state lives in the URL, not in component state.
 *
 * That is what makes a search result something somebody can send to a colleague, and what
 * makes the back button walk back through a list the way anybody would expect. It also means
 * the server, which is the thing actually doing the filtering, is told by the address rather
 * than by a second channel that has to be kept in step with it.
 *
 * Every navigation replaces rather than pushes, and preserves scroll: typing four letters
 * into a search box should not put four entries in the browser's history.
 */
export function useListParams(path: string, current: { search: string; page: number | null }) {
  const go = useCallback(
    (next: { search?: string; page?: number | null }) => {
      const search = next.search ?? current.search
      // Changing the search resets the page. Page 3 of the old results is not page 3 of the
      // new ones, and is usually not there at all.
      const page = next.search !== undefined ? null : (next.page ?? current.page)

      const params: Record<string, string> = {}
      if (search !== '') params.search = search
      if (page !== null && page > 1) params.page = String(page)

      router.get(path, params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
      })
    },
    [path, current.search, current.page],
  )

  return {
    setSearch: useCallback((search: string) => go({ search }), [go]),
    setPage: useCallback((page: number) => go({ page }), [go]),
  }
}
