import EventAvailableIcon from '@mui/icons-material/EventAvailable'
import {
  Alert,
  Box,
  Button,
  Chip,
  MenuItem,
  Paper,
  Stack,
  Tab,
  Tabs,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableRow,
  TextField,
  Typography,
} from '@mui/material'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useMemo, useState } from 'react'
import {
  type DoctorFeePayload,
  type DoctorLeavePayload,
  type DoctorSchedulePayload,
  createDoctorLeave,
  createDoctorSchedule,
  getDoctorFees,
  getDoctorLeaves,
  getDoctorSchedules,
  getUsers,
  upsertDoctorFee,
} from '../../api/admin'
import { getApiErrorMessage } from '../../api/errors'
import { formatCurrency, todayIso } from '../../utils/format'

const DAY_LABELS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']

export function DoctorOpsPage() {
  const queryClient = useQueryClient()
  const [doctorId, setDoctorId] = useState<number | ''>('')
  const [tab, setTab] = useState(0)

  const usersQuery = useQuery({ queryKey: ['admin-users'], queryFn: getUsers })
  const doctors = useMemo(
    () => (usersQuery.data?.data ?? []).filter((user) => user.roles.includes('doctor') && user.status === 'active'),
    [usersQuery.data?.data],
  )

  const selectedDoctorId = typeof doctorId === 'number' ? doctorId : doctors[0]?.id
  const effectiveDoctorId = selectedDoctorId ?? 0

  const schedulesQuery = useQuery({
    queryKey: ['doctor-schedules', effectiveDoctorId],
    queryFn: () => getDoctorSchedules(effectiveDoctorId),
    enabled: effectiveDoctorId > 0,
  })
  const leavesQuery = useQuery({
    queryKey: ['doctor-leaves', effectiveDoctorId],
    queryFn: () => getDoctorLeaves(effectiveDoctorId),
    enabled: effectiveDoctorId > 0,
  })
  const feesQuery = useQuery({
    queryKey: ['doctor-fees', effectiveDoctorId],
    queryFn: () => getDoctorFees(effectiveDoctorId),
    enabled: effectiveDoctorId > 0,
  })

  const [scheduleForm, setScheduleForm] = useState<DoctorSchedulePayload>({
    day_of_week: 1,
    start_time: '09:00',
    end_time: '13:00',
    slot_duration_minutes: 30,
    status: 'active',
  })
  const [leaveForm, setLeaveForm] = useState<DoctorLeavePayload>({
    start_date: todayIso(),
    end_date: todayIso(),
    reason: '',
    status: 'active',
  })
  const [feeForm, setFeeForm] = useState<DoctorFeePayload>({
    visit_type: 'first_visit',
    fee_amount: 500,
    status: 'active',
  })

  const scheduleMutation = useMutation({
    mutationFn: () => createDoctorSchedule(effectiveDoctorId, scheduleForm),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['doctor-schedules', effectiveDoctorId] })
    },
  })
  const leaveMutation = useMutation({
    mutationFn: () => createDoctorLeave(effectiveDoctorId, leaveForm),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['doctor-leaves', effectiveDoctorId] })
    },
  })
  const feeMutation = useMutation({
    mutationFn: () => upsertDoctorFee(effectiveDoctorId, feeForm),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['doctor-fees', effectiveDoctorId] })
    },
  })

  return (
    <Stack spacing={3}>
      <Box>
        <Typography component="h1" sx={{ fontWeight: 700 }} variant="h4">
          Doctor Schedule & Fees
        </Typography>
        <Typography color="text.secondary">
          Define weekly slots, leave days and consultation pricing used during OPD booking.
        </Typography>
      </Box>

      <TextField
        label="Doctor"
        onChange={(event) => setDoctorId(event.target.value ? Number(event.target.value) : '')}
        select
        sx={{ maxWidth: 360 }}
        value={effectiveDoctorId || ''}
      >
        {doctors.map((doctor) => (
          <MenuItem key={doctor.id} value={doctor.id}>
            {doctor.name}
          </MenuItem>
        ))}
      </TextField>

      {!effectiveDoctorId ? <Alert severity="info">No active doctor users found.</Alert> : null}

      {effectiveDoctorId ? (
        <>
          <Tabs onChange={(_, value: number) => setTab(value)} value={tab}>
            <Tab label="Weekly Schedule" />
            <Tab label="Leaves" />
            <Tab label="Fees" />
          </Tabs>

          {tab === 0 ? (
            <Stack spacing={2}>
              <Paper sx={{ p: 2 }} variant="outlined">
                <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
                  <TextField
                    label="Day"
                    onChange={(event) =>
                      setScheduleForm((current) => ({ ...current, day_of_week: Number(event.target.value) }))
                    }
                    select
                    sx={{ minWidth: 160 }}
                    value={scheduleForm.day_of_week}
                  >
                    {DAY_LABELS.map((label, index) => (
                      <MenuItem key={label} value={index}>
                        {label}
                      </MenuItem>
                    ))}
                  </TextField>
                  <TextField
                    label="Start"
                    onChange={(event) => setScheduleForm((current) => ({ ...current, start_time: event.target.value }))}
                    slotProps={{ inputLabel: { shrink: true } }}
                    type="time"
                    value={scheduleForm.start_time}
                  />
                  <TextField
                    label="End"
                    onChange={(event) => setScheduleForm((current) => ({ ...current, end_time: event.target.value }))}
                    slotProps={{ inputLabel: { shrink: true } }}
                    type="time"
                    value={scheduleForm.end_time}
                  />
                  <TextField
                    label="Slot mins"
                    onChange={(event) =>
                      setScheduleForm((current) => ({
                        ...current,
                        slot_duration_minutes: Number(event.target.value) || 15,
                      }))
                    }
                    type="number"
                    value={scheduleForm.slot_duration_minutes ?? 15}
                  />
                  <Button
                    disabled={scheduleMutation.isPending}
                    onClick={() => scheduleMutation.mutate()}
                    startIcon={<EventAvailableIcon />}
                    variant="contained"
                  >
                    Add
                  </Button>
                </Stack>
                {scheduleMutation.isError ? (
                  <Alert severity="error" sx={{ mt: 2 }}>
                    {getApiErrorMessage(scheduleMutation.error, 'Unable to save schedule.')}
                  </Alert>
                ) : null}
              </Paper>

              <Paper variant="outlined">
                <Table size="small">
                  <TableHead>
                    <TableRow>
                      <TableCell>Day</TableCell>
                      <TableCell>Window</TableCell>
                      <TableCell>Slot</TableCell>
                      <TableCell>Status</TableCell>
                    </TableRow>
                  </TableHead>
                  <TableBody>
                    {(schedulesQuery.data?.data ?? []).map((schedule) => (
                      <TableRow key={schedule.id}>
                        <TableCell>{DAY_LABELS[schedule.day_of_week] ?? schedule.day_of_week}</TableCell>
                        <TableCell>
                          {schedule.start_time} – {schedule.end_time}
                        </TableCell>
                        <TableCell>{schedule.slot_duration_minutes} min</TableCell>
                        <TableCell>
                          <Chip label={schedule.status} size="small" />
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </Paper>
            </Stack>
          ) : null}

          {tab === 1 ? (
            <Stack spacing={2}>
              <Paper sx={{ p: 2 }} variant="outlined">
                <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
                  <TextField
                    label="From"
                    onChange={(event) => setLeaveForm((current) => ({ ...current, start_date: event.target.value }))}
                    slotProps={{ inputLabel: { shrink: true } }}
                    type="date"
                    value={leaveForm.start_date}
                  />
                  <TextField
                    label="To"
                    onChange={(event) => setLeaveForm((current) => ({ ...current, end_date: event.target.value }))}
                    slotProps={{ inputLabel: { shrink: true } }}
                    type="date"
                    value={leaveForm.end_date}
                  />
                  <TextField
                    fullWidth
                    label="Reason"
                    onChange={(event) => setLeaveForm((current) => ({ ...current, reason: event.target.value }))}
                    value={leaveForm.reason ?? ''}
                  />
                  <Button disabled={leaveMutation.isPending} onClick={() => leaveMutation.mutate()} variant="contained">
                    Add leave
                  </Button>
                </Stack>
                {leaveMutation.isError ? (
                  <Alert severity="error" sx={{ mt: 2 }}>
                    {getApiErrorMessage(leaveMutation.error, 'Unable to save leave.')}
                  </Alert>
                ) : null}
              </Paper>
              <Paper variant="outlined">
                <Table size="small">
                  <TableHead>
                    <TableRow>
                      <TableCell>From</TableCell>
                      <TableCell>To</TableCell>
                      <TableCell>Reason</TableCell>
                      <TableCell>Status</TableCell>
                    </TableRow>
                  </TableHead>
                  <TableBody>
                    {(leavesQuery.data?.data ?? []).map((leave) => (
                      <TableRow key={leave.id}>
                        <TableCell>{leave.start_date}</TableCell>
                        <TableCell>{leave.end_date}</TableCell>
                        <TableCell>{leave.reason || '-'}</TableCell>
                        <TableCell>
                          <Chip label={leave.status} size="small" />
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </Paper>
            </Stack>
          ) : null}

          {tab === 2 ? (
            <Stack spacing={2}>
              <Paper sx={{ p: 2 }} variant="outlined">
                <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
                  <TextField
                    label="Visit type"
                    onChange={(event) =>
                      setFeeForm((current) => ({
                        ...current,
                        visit_type: event.target.value as DoctorFeePayload['visit_type'],
                      }))
                    }
                    select
                    value={feeForm.visit_type}
                  >
                    <MenuItem value="first_visit">First Visit</MenuItem>
                    <MenuItem value="follow_up">Follow-up</MenuItem>
                    <MenuItem value="emergency">Emergency</MenuItem>
                  </TextField>
                  <TextField
                    label="Fee (₹)"
                    onChange={(event) =>
                      setFeeForm((current) => ({ ...current, fee_amount: Number(event.target.value) || 0 }))
                    }
                    type="number"
                    value={feeForm.fee_amount}
                  />
                  <Button disabled={feeMutation.isPending} onClick={() => feeMutation.mutate()} variant="contained">
                    Save fee
                  </Button>
                </Stack>
                {feeMutation.isError ? (
                  <Alert severity="error" sx={{ mt: 2 }}>
                    {getApiErrorMessage(feeMutation.error, 'Unable to save fee.')}
                  </Alert>
                ) : null}
              </Paper>
              <Paper variant="outlined">
                <Table size="small">
                  <TableHead>
                    <TableRow>
                      <TableCell>Visit type</TableCell>
                      <TableCell>Fee</TableCell>
                      <TableCell>Status</TableCell>
                    </TableRow>
                  </TableHead>
                  <TableBody>
                    {(feesQuery.data?.data ?? []).map((fee) => (
                      <TableRow key={fee.id}>
                        <TableCell>{fee.visit_type}</TableCell>
                        <TableCell>{formatCurrency(fee.fee_amount)}</TableCell>
                        <TableCell>
                          <Chip label={fee.status} size="small" />
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </Paper>
            </Stack>
          ) : null}
        </>
      ) : null}
    </Stack>
  )
}
