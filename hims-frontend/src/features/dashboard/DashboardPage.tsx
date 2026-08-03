import RefreshIcon from '@mui/icons-material/Refresh'
import {
  Alert,
  Box,
  Button,
  Chip,
  CircularProgress,
  Paper,
  Stack,
  Typography,
} from '@mui/material'
import { useQuery } from '@tanstack/react-query'
import { getHealth } from '../../api/health'

export function DashboardPage() {
  const healthQuery = useQuery({
    queryKey: ['health'],
    queryFn: getHealth,
  })

  const health = healthQuery.data?.data

  return (
    <Stack spacing={3}>
      <Box>
        <Typography component="h1" sx={{ fontWeight: 700 }} variant="h4">
          Operations Dashboard
        </Typography>
        <Typography color="text.secondary" variant="body1">
          Release 1 foundation for OPD workflows.
        </Typography>
      </Box>

      <Paper variant="outlined" sx={{ p: 3 }}>
        <Stack
          direction={{ xs: 'column', sm: 'row' }}
          spacing={2}
          sx={{
            alignItems: { xs: 'flex-start', sm: 'center' },
            justifyContent: 'space-between',
          }}
        >
          <Box>
            <Typography sx={{ fontWeight: 700 }} variant="h6">
              Backend API
            </Typography>
            <Typography color="text.secondary" variant="body2">
              Connected through the configured Laravel `/api/v1` endpoint.
            </Typography>
          </Box>

          {healthQuery.isLoading ? (
            <CircularProgress aria-label="Checking API health" size={28} />
          ) : (
            <Chip
              color={health?.status === 'ok' ? 'success' : 'error'}
              label={health?.status === 'ok' ? 'Healthy' : 'Unavailable'}
              variant="outlined"
            />
          )}
        </Stack>

        {healthQuery.isError ? (
          <Alert
            action={
              <Button
                color="inherit"
                onClick={() => void healthQuery.refetch()}
                size="small"
                startIcon={<RefreshIcon />}
              >
                Retry
              </Button>
            }
            severity="warning"
            sx={{ mt: 3 }}
          >
            The frontend is ready, but the Laravel API is not reachable from this browser session.
          </Alert>
        ) : null}

        {health ? (
          <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2} sx={{ mt: 3 }}>
            <InfoItem label="Service" value={health.service} />
            <InfoItem label="Version" value={health.version} />
          </Stack>
        ) : null}
      </Paper>
    </Stack>
  )
}

function InfoItem({ label, value }: { label: string; value: string }) {
  return (
    <Box sx={{ minWidth: 160 }}>
      <Typography color="text.secondary" variant="caption">
        {label}
      </Typography>
      <Typography sx={{ fontWeight: 700 }}>{value}</Typography>
    </Box>
  )
}
