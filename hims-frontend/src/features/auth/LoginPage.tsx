import LockOutlinedIcon from '@mui/icons-material/LockOutlined'
import PhoneAndroidIcon from '@mui/icons-material/PhoneAndroid'
import {
  Alert,
  Box,
  Button,
  Divider,
  Link as MuiLink,
  Paper,
  Stack,
  Tab,
  Tabs,
  TextField,
  Typography,
} from '@mui/material'
import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Controller, useForm } from 'react-hook-form'
import { Link as RouterLink, useLocation, useNavigate } from 'react-router-dom'
import { z } from 'zod'
import { loginWithEmail, requestOtp, verifyOtp } from '../../api/auth'
import { getApiErrorMessage } from '../../api/errors'

const emailLoginSchema = z.object({
  email: z.string().email('Enter a valid email address'),
  password: z.string().min(1, 'Password is required'),
})

const otpSchema = z.object({
  mobile: z.string().min(10, 'Enter a valid mobile number').max(20),
  otp: z.string().min(3, 'Enter the OTP').max(10),
})

type EmailLoginValues = z.infer<typeof emailLoginSchema>
type OtpValues = z.infer<typeof otpSchema>

export function LoginPage() {
  const [mode, setMode] = useState<'email' | 'otp'>('email')
  const [otpRequested, setOtpRequested] = useState(false)
  const queryClient = useQueryClient()
  const navigate = useNavigate()
  const location = useLocation()
  const from = (location.state as { from?: { pathname?: string } } | null)?.from?.pathname ?? '/dashboard'

  const emailForm = useForm<EmailLoginValues>({
    resolver: zodResolver(emailLoginSchema),
    defaultValues: {
      email: '',
      password: '',
    },
  })

  const otpForm = useForm<OtpValues>({
    resolver: zodResolver(otpSchema),
    defaultValues: {
      mobile: '+91',
      otp: '',
    },
  })

  const afterLogin = async () => {
    await queryClient.invalidateQueries({ queryKey: ['auth-user'] })
    await queryClient.invalidateQueries({ queryKey: ['current-context'] })
    navigate(from, { replace: true })
  }

  const emailLogin = useMutation({
    mutationFn: loginWithEmail,
    onSuccess: afterLogin,
  })

  const otpRequest = useMutation({
    mutationFn: requestOtp,
    onSuccess: () => setOtpRequested(true),
  })

  const otpVerify = useMutation({
    mutationFn: verifyOtp,
    onSuccess: afterLogin,
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
            <Typography color="text.secondary" variant="body2">
              Sign in to continue
            </Typography>
          </Box>

          <Tabs onChange={(_, value: 'email' | 'otp') => setMode(value)} value={mode}>
            <Tab icon={<LockOutlinedIcon />} iconPosition="start" label="Email" value="email" />
            <Tab icon={<PhoneAndroidIcon />} iconPosition="start" label="OTP" value="otp" />
          </Tabs>

          {mode === 'email' ? (
            <Stack
              component="form"
              onSubmit={emailForm.handleSubmit((values) => emailLogin.mutate(values))}
              spacing={2}
            >
              {emailLogin.isError ? (
                <Alert severity="error">
                  {getApiErrorMessage(emailLogin.error, 'Unable to sign in with those credentials.')}
                </Alert>
              ) : null}

              <Controller
                control={emailForm.control}
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
                control={emailForm.control}
                name="password"
                render={({ field, fieldState }) => (
                  <TextField
                    {...field}
                    autoComplete="current-password"
                    error={Boolean(fieldState.error)}
                    helperText={fieldState.error?.message}
                    label="Password"
                    type="password"
                  />
                )}
              />

              <MuiLink component={RouterLink} to="/forgot-password" underline="hover" variant="body2">
                Forgot password?
              </MuiLink>

              <Button loading={emailLogin.isPending} size="large" type="submit" variant="contained">
                Sign in
              </Button>
            </Stack>
          ) : (
            <Stack
              component="form"
              onSubmit={otpForm.handleSubmit((values) => otpVerify.mutate(values))}
              spacing={2}
            >
              {otpRequest.isError || otpVerify.isError ? (
                <Alert severity="error">
                  {getApiErrorMessage(
                    otpVerify.error ?? otpRequest.error,
                    'Unable to complete OTP sign in.',
                  )}
                </Alert>
              ) : null}

              {otpRequested ? <Alert severity="success">OTP request accepted.</Alert> : null}

              <Controller
                control={otpForm.control}
                name="mobile"
                render={({ field, fieldState }) => (
                  <TextField
                    {...field}
                    error={Boolean(fieldState.error)}
                    helperText={fieldState.error?.message}
                    label="Mobile"
                  />
                )}
              />

              <Button
                disabled={otpRequest.isPending}
                onClick={() => {
                  const mobile = otpForm.getValues('mobile')
                  void otpForm.trigger('mobile').then((valid) => {
                    if (valid) {
                      otpRequest.mutate({ mobile })
                    }
                  })
                }}
                variant="outlined"
              >
                Request OTP
              </Button>

              <Divider />

              <Controller
                control={otpForm.control}
                name="otp"
                render={({ field, fieldState }) => (
                  <TextField
                    {...field}
                    error={Boolean(fieldState.error)}
                    helperText={fieldState.error?.message}
                    label="OTP"
                  />
                )}
              />

              <Button loading={otpVerify.isPending} size="large" type="submit" variant="contained">
                Verify and sign in
              </Button>
            </Stack>
          )}
        </Stack>
      </Paper>
    </Box>
  )
}
