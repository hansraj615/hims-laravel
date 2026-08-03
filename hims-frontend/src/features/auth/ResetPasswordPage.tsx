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
import { Controller, useForm } from 'react-hook-form'
import { Link as RouterLink, useNavigate, useSearchParams } from 'react-router-dom'
import { z } from 'zod'
import { resetPassword } from '../../api/auth'
import { getApiErrorMessage } from '../../api/errors'

const schema = z
  .object({
    email: z.string().email('Enter a valid email address'),
    password: z.string().min(8, 'Password must be at least 8 characters'),
    password_confirmation: z.string().min(8, 'Confirm your password'),
  })
  .refine((values) => values.password === values.password_confirmation, {
    message: 'Passwords do not match',
    path: ['password_confirmation'],
  })

type FormValues = z.infer<typeof schema>

export function ResetPasswordPage() {
  const [searchParams] = useSearchParams()
  const navigate = useNavigate()
  const token = searchParams.get('token') ?? ''

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      email: searchParams.get('email') ?? '',
      password: '',
      password_confirmation: '',
    },
  })

  const mutation = useMutation({
    mutationFn: (values: FormValues) =>
      resetPassword({
        ...values,
        token,
      }),
    onSuccess: () => {
      navigate('/login', { replace: true })
    },
  })

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
              Reset password
            </Typography>
            <Typography color="text.secondary" variant="body2">
              Choose a new password for your account.
            </Typography>
          </Box>

          {!token ? <Alert severity="error">Reset token is missing from the link.</Alert> : null}

          <Stack
            component="form"
            onSubmit={form.handleSubmit((values) => mutation.mutate(values))}
            spacing={2}
          >
            {mutation.isError ? (
              <Alert severity="error">{getApiErrorMessage(mutation.error, 'Unable to reset password.')}</Alert>
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
            <Controller
              control={form.control}
              name="password"
              render={({ field, fieldState }) => (
                <TextField
                  {...field}
                  autoComplete="new-password"
                  error={Boolean(fieldState.error)}
                  helperText={fieldState.error?.message}
                  label="New password"
                  type="password"
                />
              )}
            />
            <Controller
              control={form.control}
              name="password_confirmation"
              render={({ field, fieldState }) => (
                <TextField
                  {...field}
                  autoComplete="new-password"
                  error={Boolean(fieldState.error)}
                  helperText={fieldState.error?.message}
                  label="Confirm password"
                  type="password"
                />
              )}
            />

            <Button
              disabled={!token}
              loading={mutation.isPending}
              size="large"
              type="submit"
              variant="contained"
            >
              Update password
            </Button>
            <MuiLink component={RouterLink} to="/login" underline="hover">
              Back to sign in
            </MuiLink>
          </Stack>
        </Stack>
      </Paper>
    </Box>
  )
}
