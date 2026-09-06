import { useForm } from '@inertiajs/react'
import type { FormEvent } from 'react'

import { Button } from '@/components/ui/button'
import { Dialog } from '@/components/ui/dialog'
import { Field } from '@/components/ui/field'

export function CreateOrganizationDialog({
  open,
  onClose,
}: {
  open: boolean
  onClose: () => void
}) {
  const form = useForm({ name: '' })

  const submit = (event: FormEvent) => {
    event.preventDefault()

    // No slug field. One is derived from the name, and a name already taken gets a numeric
    // suffix rather than an error — asking somebody to invent a URL segment before they
    // have created anything is a question they cannot yet answer.
    form.post('/organizations', {
      onSuccess: () => {
        form.reset()
        onClose()
      },
    })
  }

  return (
    <Dialog
      open={open}
      onClose={onClose}
      title="New organization"
      description="It holds your leagues, clubs and players, and decides who may edit them."
    >
      <form onSubmit={submit} noValidate className="flex flex-col gap-4">
        <Field
          label="Name"
          autoFocus
          placeholder="Podkarpacki Związek Piłki Nożnej"
          value={form.data.name}
          onChange={(event) => form.setData('name', event.target.value)}
          error={form.errors.name}
        />

        <div className="flex justify-end gap-2">
          <Button type="button" variant="ghost" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" disabled={form.processing}>
            {form.processing ? 'Creating…' : 'Create'}
          </Button>
        </div>
      </form>
    </Dialog>
  )
}
