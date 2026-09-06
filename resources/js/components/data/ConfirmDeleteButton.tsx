import { useForm } from '@inertiajs/react'
import { Trash2 } from 'lucide-react'
import { useState } from 'react'

import { Button } from '@/components/ui/button'
import { Dialog } from '@/components/ui/dialog'

/**
 * A row action that asks first.
 *
 * Deleting a league takes its seasons, and deleting a season takes the results — so the
 * consequence is spelled out in the dialog rather than left to be discovered. `description`
 * is required for that reason: "Are you sure?" is not a description of anything.
 */
export function ConfirmDeleteButton({
  href,
  label,
  title,
  description,
}: {
  href: string
  label: string
  title: string
  description: string
}) {
  const [confirming, setConfirming] = useState(false)
  const form = useForm({})

  return (
    <>
      <Button
        variant="ghost"
        size="icon"
        aria-label={label}
        onClick={() => setConfirming(true)}
        className="hover:text-danger"
      >
        <Trash2 className="size-4" />
      </Button>

      <Dialog
        open={confirming}
        onClose={() => setConfirming(false)}
        title={title}
        description={description}
      >
        <div className="flex justify-end gap-2">
          <Button variant="ghost" onClick={() => setConfirming(false)}>
            Keep it
          </Button>
          <Button
            variant="danger"
            disabled={form.processing}
            onClick={() =>
              form.delete(href, { preserveScroll: true, onSuccess: () => setConfirming(false) })
            }
          >
            Delete
          </Button>
        </div>
      </Dialog>
    </>
  )
}
