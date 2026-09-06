import { Head, Link, useForm, usePage } from '@inertiajs/react'
import type { FormEvent } from 'react'

import { AuthCard, AuthLayout, FormError } from '@/components/layout/AuthLayout'
import { Button } from '@/components/ui/button'
import { Field } from '@/components/ui/field'
import type { SharedProps } from '@/types'

export default function SignIn() {
  const { errors } = usePage<SharedProps>().props
  const form = useForm({ email: '', password: '' })

  const submit = (event: FormEvent) => {
    event.preventDefault()

    // Password out of the form state whether it worked or not: a failed sign-in leaves the
    // page mounted, and a remembered password is one React DevTools panel from being read.
    form.post('/sign-in', { onFinish: () => form.reset('password') })
  }

  return (
    <>
      <Head title="Sign in" />

      <AuthCard
        title="Sign in"
        subtitle="Run your leagues, fixtures and results."
        footer={
          <>
            No account yet?{' '}
            <Link href="/sign-up" className="font-medium text-primary hover:underline">
              Create one
            </Link>
          </>
        }
      >
        <form onSubmit={submit} noValidate className="flex flex-col gap-4">
          <FormError message={errors.credentials} />

          <Field
            label="Email"
            type="email"
            autoComplete="email"
            autoFocus
            value={form.data.email}
            onChange={(event) => form.setData('email', event.target.value)}
            error={form.errors.email}
          />

          <Field
            label="Password"
            type="password"
            autoComplete="current-password"
            value={form.data.password}
            onChange={(event) => form.setData('password', event.target.value)}
            error={form.errors.password}
          />

          <Button type="submit" size="lg" disabled={form.processing} className="mt-1">
            {form.processing ? 'Signing in…' : 'Sign in'}
          </Button>
        </form>
      </AuthCard>
    </>
  )
}

SignIn.layout = (page: React.ReactNode) => <AuthLayout>{page}</AuthLayout>
