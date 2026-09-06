import { Head, Link, useForm, usePage } from '@inertiajs/react'
import type { FormEvent } from 'react'

import { AuthCard, AuthLayout, FormError } from '@/components/layout/AuthLayout'
import { Button } from '@/components/ui/button'
import { Field } from '@/components/ui/field'
import type { SharedProps } from '@/types'

export default function SignUp() {
  const { errors } = usePage<SharedProps>().props
  const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    password: '',
    password_confirmation: '',
  })

  const submit = (event: FormEvent) => {
    event.preventDefault()

    form.post('/sign-up', {
      onFinish: () => form.reset('password', 'password_confirmation'),
    })
  }

  return (
    <>
      <Head title="Create an account" />

      <AuthCard
        title="Create an account"
        subtitle="You will need one to run a competition of your own."
        footer={
          <>
            Already have one?{' '}
            <Link href="/sign-in" className="font-medium text-primary hover:underline">
              Sign in
            </Link>
          </>
        }
      >
        <form onSubmit={submit} noValidate className="flex flex-col gap-4">
          <FormError message={errors.credentials} />

          <div className="grid gap-4 sm:grid-cols-2">
            <Field
              label="First name"
              autoComplete="given-name"
              autoFocus
              value={form.data.first_name}
              onChange={(event) => form.setData('first_name', event.target.value)}
              error={form.errors.first_name}
            />

            <Field
              label="Last name"
              autoComplete="family-name"
              value={form.data.last_name}
              onChange={(event) => form.setData('last_name', event.target.value)}
              error={form.errors.last_name}
            />
          </div>

          <Field
            label="Email"
            type="email"
            autoComplete="email"
            value={form.data.email}
            onChange={(event) => form.setData('email', event.target.value)}
            error={form.errors.email}
          />

          <Field
            label="Password"
            type="password"
            autoComplete="new-password"
            hint="At least eight characters."
            value={form.data.password}
            onChange={(event) => form.setData('password', event.target.value)}
            error={form.errors.password}
          />

          <Field
            label="Repeat password"
            type="password"
            autoComplete="new-password"
            value={form.data.password_confirmation}
            onChange={(event) => form.setData('password_confirmation', event.target.value)}
            error={form.errors.password_confirmation}
          />

          <Button type="submit" size="lg" disabled={form.processing} className="mt-1">
            {form.processing ? 'Creating the account…' : 'Create account'}
          </Button>
        </form>
      </AuthCard>
    </>
  )
}

SignUp.layout = (page: React.ReactNode) => <AuthLayout>{page}</AuthLayout>
