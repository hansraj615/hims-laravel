import {
  Alert,
  Box,
  Button,
  Link as MuiLink,
  Paper,
  Stack,
  TextField,
  Typography,
} from '@mui/material'
import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import { Controller, useForm } from 'react-hook-form'
import { Link as RouterLink } from 'react-router-dom'
import { z } from 'zod'
import { forgotPassword } from '../../api/auth'
import { getApiErrorMessage } from '../../api/errors'

const schema = z.object({
  email: z.string().email('Enter a valid email address'),
})

type FormValues = z.infer<typeof schema>

export function ForgotPasswordPage() {
  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { email: '' },
  })

  const mutation = useMutation({
    mutationFn: forgotPassword,
  })

  return (
    <AuthPaper
      subtitle="Enter your account email and we will send reset instructions if it is registered."
      title="Forgot password"
    >
      <Stack
        component="form"
        onSubmit={form.handleSubmit((values) => mutation.mutate(values))}
        spacing={2}
      >
        {mutation.isError ? (
          <Alert severity="error">{getApiErrorMessage(mutation.error, 'Unable to request a password reset.')}</Alert>
        ) : null}
        {mutation.isSuccess ? (
          <Alert severity="success">
            If that email is registered, password reset instructions have been sent.
          </Alert>
        ) : null}

        <Controller
          control={form.control}
          name="email"
          render={({ field, fieldState }) => (
            <TextField
              {...field}
              autoComplete="email"
              error={Boolean(fieldState.error)}
              helperText={fieldState.error?.message}
              label="Email"
              type="email"
            />
          )}
        />

        <Button loading={mutation.isPending} size="large" type="submit" variant="contained">
          Send reset link
        </Button>
        <MuiLink component={RouterLink} to="/login" underline="hover">
          Back to sign in
        </MuiLink>
      </Stack>
    </AuthPaper>
  )
}

function AuthPaper({
  title,
  subtitle,
  children,
}: {
  title: string
  subtitle: string
  children: ReactNode
}) {
  return (
    <Box
      sx={{
        alignItems: 'center',
        bgcolor: 'background.default',
        display: 'flex',
        minHeight: '100vh',
        p: 2,
      }}
    >
      <Paper
        elevation={0}
        sx={{
          border: '1px solid',
          borderColor: 'divider',
          mx: 'auto',
          p: { xs: 3, sm: 4 },
          width: 'min(100%, 440px)',
        }}
      >
        <Stack spacing={3}>
          <Box>
            <Typography color="primary" sx={{ fontWeight: 800 }} variant="h5">
              HIMS
            </Typography>
            <Typography sx={{ fontWeight: 700 }} variant="h6">
              {title}
            </Typography>
            <Typography color="text.secondary" variant="body2">
              {subtitle}
            </Typography>
          </Box>
          {children}
        </Stack>
      </Paper>
    </Box>
  )
}
