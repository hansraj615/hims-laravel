import CampaignIcon from '@mui/icons-material/Campaign'
import CheckCircleIcon from '@mui/icons-material/CheckCircle'
import FavoriteIcon from '@mui/icons-material/Favorite'
import PlayCircleIcon from '@mui/icons-material/PlayCircle'
import ReplayIcon from '@mui/icons-material/Replay'
import RestartAltIcon from '@mui/icons-material/RestartAlt'
import SkipNextIcon from '@mui/icons-material/SkipNext'
import {
  Alert,
  Box,
  Button,
  Chip,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  IconButton,
  MenuItem,
  Paper,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableRow,
  TextField,
  Tooltip,
  Typography,
} from '@mui/material'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Link as RouterLink } from 'react-router-dom'
import type { ConsultationVitals } from '../../api/consultations'
import { getApiErrorMessage } from '../../api/errors'
import {
  type OpdQueueEntry,
  callOpdToken,
  completeOpdConsultation,
  getOpdQueue,
  requeueOpdToken,
  skipOpdToken,
  startOpdConsultation,
  updateOpdQueueVitals,
} from '../../api/opd'
import { usePermissions } from '../auth/usePermissions'
import { formatDate, todayIso } from '../../utils/format'

const STATUS_OPTIONS: Array<{ value: OpdQueueEntry['status']; label: string }> = [
  { value: 'waiting', label: 'Waiting' },
  { value: 'called', label: 'Called' },
  { value: 'skipped', label: 'Skipped' },
  { value: 'in_consultation', label: 'In Consultation' },
  { value: 'completed', label: 'Completed' },
]

const EMPTY_VITALS: ConsultationVitals = {
  temperature_c: null,
  pulse_bpm: null,
  respiratory_rate: null,
  bp_systolic: null,
  bp_diastolic: null,
  spo2_percent: null,
  height_cm: null,
  weight_kg: null,
}

export function OpdQueuePage() {
  const queryClient = useQueryClient()
  const { can } = usePermissions()
  const canManageQueue = can(['appointments.manage', 'opd.consult'])
  const canWriteVitals = can(['opd.vitals', 'opd.consult'])
  const [dateFilter, setDateFilter] = useState(todayIso())
  const [statusFilter, setStatusFilter] = useState<'all' | OpdQueueEntry['status']>('all')
  const [vitalsTarget, setVitalsTarget] = useState<OpdQueueEntry | null>(null)
  const [vitalsForm, setVitalsForm] = useState<ConsultationVitals>(EMPTY_VITALS)

  const queueQuery = useQuery({
    queryKey: ['opd-queue', dateFilter, statusFilter],
    queryFn: () =>
      getOpdQueue({
        date: dateFilter || undefined,
        status: statusFilter === 'all' ? undefined : statusFilter,
      }),
    refetchInterval: 15000,
  })

  const invalidate = async () => {
    await queryClient.invalidateQueries({ queryKey: ['opd-queue'] })
  }

  const callMutation = useMutation({ mutationFn: callOpdToken, onSuccess: invalidate })
  const startMutation = useMutation({ mutationFn: startOpdConsultation, onSuccess: invalidate })
  const completeMutation = useMutation({ mutationFn: completeOpdConsultation, onSuccess: invalidate })
  const skipMutation = useMutation({ mutationFn: skipOpdToken, onSuccess: invalidate })
  const requeueMutation = useMutation({ mutationFn: requeueOpdToken, onSuccess: invalidate })
  const vitalsMutation = useMutation({
    mutationFn: () => {
      if (!vitalsTarget) {
        throw new Error('No queue entry selected')
      }

      return updateOpdQueueVitals(vitalsTarget.id, vitalsForm)
    },
    onSuccess: async () => {
      await invalidate()
      setVitalsTarget(null)
    },
  })

  const openVitals = (entry: OpdQueueEntry) => {
    setVitalsForm({ ...EMPTY_VITALS, ...(entry.vitals ?? {}) })
    setVitalsTarget(entry)
  }

  const resetFilters = () => {
    setDateFilter(todayIso())
    setStatusFilter('all')
  }

  const entries = queueQuery.data?.data ?? []
  const vitalsEditable =
    vitalsTarget !== null && ['waiting', 'called', 'in_consultation'].includes(vitalsTarget.status)

  return (
    <Stack spacing={3}>
      <Box>
        <Typography component="h1" sx={{ fontWeight: 700 }} variant="h4">
          OPD Queue
        </Typography>
        <Typography color="text.secondary">
          Live token board — nurse/compounder vitals handoff before the doctor starts consultation.
        </Typography>
      </Box>

      {queueQuery.isError ? <Alert severity="error">Unable to load the OPD queue.</Alert> : null}

      <Box
        sx={{
          alignItems: { xs: 'stretch', md: 'center' },
          display: 'grid',
          gap: 2,
          gridTemplateColumns: { xs: '1fr', md: '200px 220px auto' },
        }}
      >
        <TextField
          label="Date"
          onChange={(event) => setDateFilter(event.target.value)}
          size="small"
          slotProps={{ inputLabel: { shrink: true } }}
          type="date"
          value={dateFilter}
        />
        <TextField
          label="Status"
          onChange={(event) => setStatusFilter(event.target.value as 'all' | OpdQueueEntry['status'])}
          select
          size="small"
          value={statusFilter}
        >
          <MenuItem value="all">All statuses</MenuItem>
          {STATUS_OPTIONS.map((status) => (
            <MenuItem key={status.value} value={status.value}>
              {status.label}
            </MenuItem>
          ))}
        </TextField>
        <Button onClick={resetFilters} startIcon={<RestartAltIcon />} variant="outlined">
          Reset
        </Button>
      </Box>

      <Paper variant="outlined">
        <Table>
          <TableHead>
            <TableRow>
              <TableCell>Token</TableCell>
              <TableCell>Date</TableCell>
              <TableCell>Patient</TableCell>
              <TableCell>Doctor</TableCell>
              <TableCell>Department</TableCell>
              <TableCell>Vitals</TableCell>
              <TableCell>Status</TableCell>
              <TableCell align="right">Actions</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {entries.map((entry) => (
              <TableRow key={entry.id}>
                <TableCell>
                  <Typography sx={{ fontWeight: 700 }}>{entry.token_code}</Typography>
                </TableCell>
                <TableCell>{formatDate(entry.queue_date)}</TableCell>
                <TableCell>{entry.patient?.name ?? '-'}</TableCell>
                <TableCell>{entry.doctor?.name ?? '-'}</TableCell>
                <TableCell>{entry.department?.name ?? '-'}</TableCell>
                <TableCell>
                  <Chip
                    color={entry.has_vitals ? 'success' : 'warning'}
                    label={entry.has_vitals ? 'Vitals recorded' : 'Vitals pending'}
                    size="small"
                    variant={entry.has_vitals ? 'filled' : 'outlined'}
                  />
                </TableCell>
                <TableCell>
                  <Chip color={queueStatusColor(entry.status)} label={entry.status} size="small" />
                </TableCell>
                <TableCell align="right">
                  {canWriteVitals ? (
                    <Tooltip title="Record vitals">
                      <span>
                        <IconButton
                          aria-label={`Record vitals for ${entry.token_code}`}
                          disabled={entry.status === 'completed'}
                          onClick={() => openVitals(entry)}
                        >
                          <FavoriteIcon />
                        </IconButton>
                      </span>
                    </Tooltip>
                  ) : null}
                  {canManageQueue ? (
                    <>
                      <Tooltip title="Call">
                        <span>
                          <IconButton
                            aria-label={`Call ${entry.token_code}`}
                            disabled={entry.status !== 'waiting' || callMutation.isPending}
                            onClick={() => callMutation.mutate(entry.id)}
                          >
                            <CampaignIcon />
                          </IconButton>
                        </span>
                      </Tooltip>
                      <Tooltip title="Skip">
                        <span>
                          <IconButton
                            aria-label={`Skip ${entry.token_code}`}
                            disabled={
                              !['waiting', 'called'].includes(entry.status) || skipMutation.isPending
                            }
                            onClick={() => skipMutation.mutate(entry.id)}
                          >
                            <SkipNextIcon />
                          </IconButton>
                        </span>
                      </Tooltip>
                      <Tooltip title="Requeue">
                        <span>
                          <IconButton
                            aria-label={`Requeue ${entry.token_code}`}
                            disabled={
                              !['skipped', 'called'].includes(entry.status) || requeueMutation.isPending
                            }
                            onClick={() => requeueMutation.mutate(entry.id)}
                          >
                            <ReplayIcon />
                          </IconButton>
                        </span>
                      </Tooltip>
                      <Tooltip title="Start consultation">
                        <span>
                          <IconButton
                            aria-label={`Start consultation for ${entry.token_code}`}
                            disabled={entry.status !== 'called' || startMutation.isPending}
                            onClick={() => startMutation.mutate(entry.id)}
                          >
                            <PlayCircleIcon />
                          </IconButton>
                        </span>
                      </Tooltip>
                      <Tooltip title="Complete">
                        <span>
                          <IconButton
                            aria-label={`Complete ${entry.token_code}`}
                            disabled={entry.status !== 'in_consultation' || completeMutation.isPending}
                            onClick={() => completeMutation.mutate(entry.id)}
                          >
                            <CheckCircleIcon />
                          </IconButton>
                        </span>
                      </Tooltip>
                      {entry.status === 'in_consultation' && can('opd.consult') ? (
                        <Button
                          component={RouterLink}
                          size="small"
                          to={`/opd/consultations/new?opd_queue_id=${entry.id}&patient_id=${entry.patient?.id ?? ''}`}
                          variant="outlined"
                        >
                          Start Note
                        </Button>
                      ) : null}
                    </>
                  ) : null}
                </TableCell>
              </TableRow>
            ))}
            {entries.length === 0 ? (
              <TableRow>
                <TableCell colSpan={8}>
                  <Typography color="text.secondary" sx={{ py: 2, textAlign: 'center' }}>
                    No tokens for the selected filters.
                  </Typography>
                </TableCell>
              </TableRow>
            ) : null}
          </TableBody>
        </Table>
      </Paper>

      <Dialog fullWidth maxWidth="sm" onClose={() => setVitalsTarget(null)} open={Boolean(vitalsTarget)}>
        <DialogTitle>Record vitals — {vitalsTarget?.token_code}</DialogTitle>
        <DialogContent>
          <Stack spacing={2} sx={{ pt: 1 }}>
            {vitalsMutation.isError ? (
              <Alert severity="error">{getApiErrorMessage(vitalsMutation.error, 'Unable to save vitals.')}</Alert>
            ) : null}
            <Typography color="text.secondary" variant="body2">
              {vitalsTarget?.patient?.name} ({vitalsTarget?.patient?.uhid})
            </Typography>
            <Box
              sx={{
                display: 'grid',
                gap: 2,
                gridTemplateColumns: { xs: '1fr', sm: '1fr 1fr' },
              }}
            >
              {(
                [
                  ['temperature_c', 'Temp (°C)'],
                  ['pulse_bpm', 'Pulse (bpm)'],
                  ['respiratory_rate', 'Resp. rate'],
                  ['bp_systolic', 'BP systolic'],
                  ['bp_diastolic', 'BP diastolic'],
                  ['spo2_percent', 'SpO2 (%)'],
                  ['height_cm', 'Height (cm)'],
                  ['weight_kg', 'Weight (kg)'],
                ] as const
              ).map(([key, label]) => (
                <TextField
                  key={key}
                  disabled={!vitalsEditable}
                  label={label}
                  onChange={(event) =>
                    setVitalsForm((current) => ({
                      ...current,
                      [key]: event.target.value === '' ? null : Number(event.target.value),
                    }))
                  }
                  type="number"
                  value={vitalsForm[key] ?? ''}
                />
              ))}
            </Box>
          </Stack>
        </DialogContent>
        <DialogActions sx={{ px: 3, pb: 3 }}>
          <Button onClick={() => setVitalsTarget(null)}>Close</Button>
          <Button
            disabled={!vitalsEditable || vitalsMutation.isPending}
            onClick={() => vitalsMutation.mutate()}
            variant="contained"
          >
            Save vitals
          </Button>
        </DialogActions>
      </Dialog>
    </Stack>
  )
}

function queueStatusColor(
  status: OpdQueueEntry['status'],
): 'default' | 'success' | 'warning' | 'info' | 'error' {
  switch (status) {
    case 'completed':
      return 'success'
    case 'in_consultation':
      return 'info'
    case 'called':
      return 'warning'
    case 'skipped':
      return 'error'
    default:
      return 'default'
  }
}
