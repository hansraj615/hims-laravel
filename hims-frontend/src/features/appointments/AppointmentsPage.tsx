import AddIcon from '@mui/icons-material/Add'
import CancelIcon from '@mui/icons-material/Cancel'
import HowToRegIcon from '@mui/icons-material/HowToReg'
import RestartAltIcon from '@mui/icons-material/RestartAlt'
import {
  Alert,
  Autocomplete,
  Box,
  Button,
  Chip,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  Drawer,
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
import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { Controller, useForm } from 'react-hook-form'
import { useSearchParams } from 'react-router-dom'
import { z } from 'zod'
import {
  type Appointment,
  type AppointmentPayload,
  bookAppointment,
  cancelAppointment,
  checkInAppointment,
  getAppointmentOptions,
  getAppointmentSlots,
  getAppointments,
} from '../../api/appointments'
import { getApiErrorMessage } from '../../api/errors'
import { getPatients, type Patient } from '../../api/patients'
import { formatCurrency, formatDate, todayIso } from '../../utils/format'

const STATUS_OPTIONS: Array<{ value: Appointment['status']; label: string }> = [
  { value: 'booked', label: 'Booked' },
  { value: 'confirmed', label: 'Confirmed' },
  { value: 'checked_in', label: 'Checked-in' },
  { value: 'completed', label: 'Completed' },
  { value: 'cancelled', label: 'Cancelled' },
  { value: 'no_show', label: 'No Show' },
]

const CANCELLABLE_STATUSES: Appointment['status'][] = ['booked', 'confirmed']
const CHECK_IN_BLOCKED_STATUSES: Appointment['status'][] = ['cancelled', 'no_show', 'checked_in', 'completed']

const bookingSchema = z.object({
  patient_id: z.number().min(1, 'Select a patient'),
  department_id: z.number().nullable().optional(),
  doctor_user_id: z.number().nullable().optional(),
  appointment_date: z.string().min(1, 'Date is required'),
  slot_start: z.string().nullable().optional(),
  slot_end: z.string().nullable().optional(),
  visit_type: z.enum(['first_visit', 'follow_up', 'emergency']),
  source: z.enum(['walk_in', 'phone', 'online', 'referral']),
  priority: z.enum(['normal', 'urgent', 'vip']),
  fee_amount: z.number().min(0).nullable().optional(),
  reason: z.string().nullable().optional(),
})

type BookingFormValues = z.infer<typeof bookingSchema>

export function AppointmentsPage() {
  const queryClient = useQueryClient()
  const [searchParams, setSearchParams] = useSearchParams()
  const [open, setOpen] = useState(false)
  const [dateFilter, setDateFilter] = useState(todayIso())
  const [statusFilter, setStatusFilter] = useState<'all' | Appointment['status']>('all')
  const [cancelTarget, setCancelTarget] = useState<Appointment | null>(null)
  const [cancellationReason, setCancellationReason] = useState('')

  const appointmentsQuery = useQuery({
    queryKey: ['appointments', dateFilter, statusFilter],
    queryFn: () =>
      getAppointments({
        date: dateFilter || undefined,
        status: statusFilter === 'all' ? undefined : statusFilter,
      }),
  })
  const patientsQuery = useQuery({ queryKey: ['patients'], queryFn: getPatients })
  const optionsQuery = useQuery({
    queryKey: ['appointment-options'],
    queryFn: getAppointmentOptions,
  })
  const departments = optionsQuery.data?.data.departments ?? []
  const doctors = optionsQuery.data?.data.doctors ?? []

  const form = useForm<BookingFormValues>({
    resolver: zodResolver(bookingSchema),
    defaultValues: defaultBookingValues(),
  })

  const watchedDoctorId = form.watch('doctor_user_id')
  const watchedDate = form.watch('appointment_date')
  const watchedVisitType = form.watch('visit_type')

  const slotsQuery = useQuery({
    queryKey: ['appointment-slots', watchedDoctorId, watchedDate, watchedVisitType],
    queryFn: () =>
      getAppointmentSlots({
        doctor_user_id: Number(watchedDoctorId),
        date: watchedDate,
        visit_type: watchedVisitType,
      }),
    enabled: Boolean(watchedDoctorId && watchedDate),
  })

  useEffect(() => {
    const fee = slotsQuery.data?.data.fee_amount
    if (fee !== null && fee !== undefined && fee !== '') {
      form.setValue('fee_amount', Number(fee))
    }
  }, [slotsQuery.data?.data.fee_amount, form])

  useEffect(() => {
    form.setValue('slot_start', null)
    form.setValue('slot_end', null)
  }, [watchedDoctorId, watchedDate, form])

  useEffect(() => {
    if (searchParams.get('book') !== '1') {
      return
    }

    const patientId = searchParams.get('patient_id')

    form.reset({
      ...defaultBookingValues(),
      patient_id: patientId ? Number(patientId) : 0,
    })
    setOpen(true)

    const next = new URLSearchParams(searchParams)
    next.delete('book')
    next.delete('walkin')
    setSearchParams(next, { replace: true })
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [searchParams])

  const bookMutation = useMutation({
    mutationFn: (values: BookingFormValues) => {
      const payload: AppointmentPayload = {
        patient_id: values.patient_id,
        department_id: values.department_id ?? null,
        doctor_user_id: values.doctor_user_id ?? null,
        appointment_date: values.appointment_date,
        slot_start: values.slot_start || null,
        slot_end: values.slot_end || null,
        visit_type: values.visit_type,
        source: values.source,
        priority: values.priority,
        fee_amount: values.fee_amount ?? 0,
        reason: values.reason || null,
      }

      return bookAppointment(payload)
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['appointments'] })
      setOpen(false)
      form.reset(defaultBookingValues())
    },
  })

  const cancelMutation = useMutation({
    mutationFn: () => {
      if (!cancelTarget) {
        throw new Error('No appointment selected')
      }

      return cancelAppointment(cancelTarget.id, cancellationReason)
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['appointments'] })
      setCancelTarget(null)
      setCancellationReason('')
    },
  })

  const checkInMutation = useMutation({
    mutationFn: (id: number) => checkInAppointment(id),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['appointments'] })
      await queryClient.invalidateQueries({ queryKey: ['opd-queue'] })
    },
  })

  const startCreate = () => {
    form.reset(defaultBookingValues())
    setOpen(true)
  }

  const resetFilters = () => {
    setDateFilter(todayIso())
    setStatusFilter('all')
  }

  const patients = patientsQuery.data?.data ?? []
  const appointments = appointmentsQuery.data?.data ?? []

  return (
    <Stack spacing={3}>
      <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2} sx={{ justifyContent: 'space-between' }}>
        <Box>
          <Typography component="h1" sx={{ fontWeight: 700 }} variant="h4">
            Appointments
          </Typography>
          <Typography color="text.secondary">Book, review and manage OPD appointments.</Typography>
        </Box>
        <Button onClick={startCreate} startIcon={<AddIcon />} variant="contained">
          Book Appointment
        </Button>
      </Stack>

      {appointmentsQuery.isError ? <Alert severity="error">Unable to load appointments.</Alert> : null}

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
          onChange={(event) => setStatusFilter(event.target.value as 'all' | Appointment['status'])}
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
              <TableCell>Appointment #</TableCell>
              <TableCell>Date / Time</TableCell>
              <TableCell>Patient</TableCell>
              <TableCell>Doctor</TableCell>
              <TableCell>Department</TableCell>
              <TableCell>Fee</TableCell>
              <TableCell>Status</TableCell>
              <TableCell align="right">Actions</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {appointments.map((appointment) => (
              <TableRow key={appointment.id}>
                <TableCell>{appointment.appointment_number}</TableCell>
                <TableCell>
                  {formatDate(appointment.appointment_date)}
                  {appointment.slot_start ? ` · ${appointment.slot_start}` : ''}
                </TableCell>
                <TableCell>{appointment.patient?.name ?? '-'}</TableCell>
                <TableCell>{appointment.doctor?.name ?? '-'}</TableCell>
                <TableCell>{appointment.department?.name ?? '-'}</TableCell>
                <TableCell>{formatCurrency(appointment.fee_amount)}</TableCell>
                <TableCell>
                  <Chip label={appointment.status} size="small" color={statusColor(appointment.status)} />
                </TableCell>
                <TableCell align="right">
                  <Tooltip title="Check in">
                    <span>
                      <IconButton
                        aria-label={`Check in ${appointment.appointment_number}`}
                        disabled={
                          CHECK_IN_BLOCKED_STATUSES.includes(appointment.status) || checkInMutation.isPending
                        }
                        onClick={() => checkInMutation.mutate(appointment.id)}
                      >
                        <HowToRegIcon />
                      </IconButton>
                    </span>
                  </Tooltip>
                  <Tooltip title="Cancel">
                    <span>
                      <IconButton
                        aria-label={`Cancel ${appointment.appointment_number}`}
                        disabled={!CANCELLABLE_STATUSES.includes(appointment.status)}
                        onClick={() => setCancelTarget(appointment)}
                      >
                        <CancelIcon />
                      </IconButton>
                    </span>
                  </Tooltip>
                </TableCell>
              </TableRow>
            ))}
            {appointments.length === 0 ? (
              <TableRow>
                <TableCell colSpan={8}>
                  <Typography color="text.secondary" sx={{ py: 2, textAlign: 'center' }}>
                    No appointments for the selected filters.
                  </Typography>
                </TableCell>
              </TableRow>
            ) : null}
          </TableBody>
        </Table>
      </Paper>

      <Drawer anchor="right" onClose={() => setOpen(false)} open={open}>
        <Stack
          component="form"
          onSubmit={form.handleSubmit((values) => bookMutation.mutate(values))}
          spacing={2}
          sx={{ p: 3, width: { xs: 340, sm: 440 } }}
        >
          <Typography sx={{ fontWeight: 700 }} variant="h6">
            Book Appointment
          </Typography>
          {bookMutation.isError ? (
            <Alert severity="error">{getApiErrorMessage(bookMutation.error, 'Unable to book appointment.')}</Alert>
          ) : null}

          <Controller
            control={form.control}
            name="patient_id"
            render={({ field, fieldState }) => (
              <Autocomplete<Patient>
                getOptionLabel={(option) => `${option.uhid} — ${option.full_name}`}
                isOptionEqualToValue={(option, value) => option.id === value.id}
                onChange={(_, value) => field.onChange(value?.id ?? 0)}
                options={patients}
                renderInput={(params) => (
                  <TextField
                    {...params}
                    error={Boolean(fieldState.error)}
                    helperText={fieldState.error?.message}
                    label="Patient"
                  />
                )}
                value={patients.find((patient) => patient.id === field.value) ?? null}
              />
            )}
          />

          <Controller
            control={form.control}
            name="department_id"
            render={({ field }) => (
              <TextField
                label="Department"
                onChange={(event) => field.onChange(event.target.value ? Number(event.target.value) : null)}
                select
                value={field.value ?? ''}
              >
                <MenuItem value="">Unassigned</MenuItem>
                {departments.map((department) => (
                  <MenuItem key={department.id} value={department.id}>
                    {department.name}
                  </MenuItem>
                ))}
              </TextField>
            )}
          />

          <Controller
            control={form.control}
            name="doctor_user_id"
            render={({ field }) => (
              <TextField
                helperText={doctors.length === 0 ? 'No active doctors found for this hospital.' : undefined}
                label="Doctor"
                onChange={(event) => field.onChange(event.target.value ? Number(event.target.value) : null)}
                select
                value={field.value ?? ''}
              >
                <MenuItem value="">Unassigned</MenuItem>
                {doctors.map((doctor) => (
                  <MenuItem key={doctor.id} value={doctor.id}>
                    {doctor.name}
                  </MenuItem>
                ))}
              </TextField>
            )}
          />

          <Controller
            control={form.control}
            name="appointment_date"
            render={({ field, fieldState }) => (
              <TextField
                {...field}
                error={Boolean(fieldState.error)}
                helperText={fieldState.error?.message}
                label="Appointment Date"
                slotProps={{ inputLabel: { shrink: true } }}
                type="date"
              />
            )}
          />
          <Stack direction="row" spacing={2}>
            <Controller
              control={form.control}
              name="slot_start"
              render={({ field, fieldState }) => {
                const slots = slotsQuery.data?.data.slots ?? []
                const onLeave = Boolean(slotsQuery.data?.data.on_leave)
                const usesSlotPicker = Boolean(watchedDoctorId) && (slots.length > 0 || onLeave || slotsQuery.isFetched)

                if (usesSlotPicker) {
                  return (
                    <TextField
                      error={Boolean(fieldState.error) || onLeave}
                      fullWidth
                      helperText={
                        onLeave
                          ? `Doctor on leave${slotsQuery.data?.data.leave_reason ? `: ${slotsQuery.data.data.leave_reason}` : ''}`
                          : fieldState.error?.message || (slots.length === 0 ? 'No slots for this date' : undefined)
                      }
                      label="Available Slot"
                      onChange={(event) => {
                        const value = event.target.value
                        if (!value) {
                          field.onChange(null)
                          form.setValue('slot_end', null)
                          return
                        }
                        const [start, end] = value.split('|')
                        field.onChange(start)
                        form.setValue('slot_end', end)
                      }}
                      select
                      value={
                        field.value && form.getValues('slot_end')
                          ? `${field.value}|${form.getValues('slot_end')}`
                          : ''
                      }
                    >
                      {slots
                        .filter((slot) => slot.available)
                        .map((slot) => (
                          <MenuItem key={`${slot.slot_start}-${slot.slot_end}`} value={`${slot.slot_start}|${slot.slot_end}`}>
                            {slot.slot_start} – {slot.slot_end}
                          </MenuItem>
                        ))}
                    </TextField>
                  )
                }

                return (
                  <TextField
                    fullWidth
                    label="Slot Start"
                    onChange={(event) => field.onChange(event.target.value || null)}
                    slotProps={{ inputLabel: { shrink: true } }}
                    type="time"
                    value={field.value ?? ''}
                  />
                )
              }}
            />
            {!watchedDoctorId ? (
              <Controller
                control={form.control}
                name="slot_end"
                render={({ field }) => (
                  <TextField
                    fullWidth
                    label="Slot End"
                    onChange={(event) => field.onChange(event.target.value || null)}
                    slotProps={{ inputLabel: { shrink: true } }}
                    type="time"
                    value={field.value ?? ''}
                  />
                )}
              />
            ) : null}
          </Stack>
          <Controller
            control={form.control}
            name="visit_type"
            render={({ field }) => (
              <TextField {...field} label="Visit Type" select>
                <MenuItem value="first_visit">First Visit</MenuItem>
                <MenuItem value="follow_up">Follow-up</MenuItem>
                <MenuItem value="emergency">Emergency</MenuItem>
              </TextField>
            )}
          />
          <Controller
            control={form.control}
            name="source"
            render={({ field }) => (
              <TextField {...field} label="Source" select>
                <MenuItem value="walk_in">Walk-in</MenuItem>
                <MenuItem value="phone">Phone</MenuItem>
                <MenuItem value="online">Online</MenuItem>
                <MenuItem value="referral">Referral</MenuItem>
              </TextField>
            )}
          />
          <Controller
            control={form.control}
            name="priority"
            render={({ field }) => (
              <TextField {...field} label="Priority" select>
                <MenuItem value="normal">Normal</MenuItem>
                <MenuItem value="urgent">Urgent</MenuItem>
                <MenuItem value="vip">VIP</MenuItem>
              </TextField>
            )}
          />
          <Controller
            control={form.control}
            name="fee_amount"
            render={({ field }) => (
              <TextField
                helperText={
                  watchedDoctorId && slotsQuery.data?.data.fee_amount != null
                    ? 'Auto-filled from doctor fee master; you may override.'
                    : undefined
                }
                label="Fee Amount (₹)"
                onChange={(event) => field.onChange(event.target.value === '' ? null : Number(event.target.value))}
                type="number"
                value={field.value ?? ''}
              />
            )}
          />
          <Controller
            control={form.control}
            name="reason"
            render={({ field }) => (
              <TextField
                {...field}
                label="Reason"
                multiline
                onChange={(event) => field.onChange(event.target.value || null)}
                rows={2}
                value={field.value ?? ''}
              />
            )}
          />

          <Button loading={bookMutation.isPending} type="submit" variant="contained">
            Book Appointment
          </Button>
        </Stack>
      </Drawer>

      <Dialog fullWidth maxWidth="xs" onClose={() => setCancelTarget(null)} open={Boolean(cancelTarget)}>
        <DialogTitle>Cancel Appointment</DialogTitle>
        <DialogContent>
          <Stack spacing={2} sx={{ pt: 1 }}>
            {cancelMutation.isError ? (
              <Alert severity="error">
                {getApiErrorMessage(cancelMutation.error, 'Unable to cancel appointment.')}
              </Alert>
            ) : null}
            <Typography color="text.secondary" variant="body2">
              Cancelling {cancelTarget?.appointment_number} for {cancelTarget?.patient?.name}.
            </Typography>
            <TextField
              label="Cancellation Reason"
              multiline
              onChange={(event) => setCancellationReason(event.target.value)}
              rows={2}
              value={cancellationReason}
            />
          </Stack>
        </DialogContent>
        <DialogActions sx={{ px: 3, pb: 3 }}>
          <Button onClick={() => setCancelTarget(null)}>Back</Button>
          <Button
            color="error"
            disabled={!cancellationReason.trim()}
            loading={cancelMutation.isPending}
            onClick={() => cancelMutation.mutate()}
            variant="contained"
          >
            Cancel Appointment
          </Button>
        </DialogActions>
      </Dialog>
    </Stack>
  )
}

function statusColor(status: Appointment['status']): 'default' | 'success' | 'error' | 'warning' | 'info' {
  switch (status) {
    case 'completed':
      return 'success'
    case 'checked_in':
      return 'info'
    case 'cancelled':
    case 'no_show':
      return 'error'
    case 'confirmed':
      return 'warning'
    default:
      return 'default'
  }
}

function defaultBookingValues(): BookingFormValues {
  return {
    patient_id: 0,
    department_id: null,
    doctor_user_id: null,
    appointment_date: todayIso(),
    slot_start: null,
    slot_end: null,
    visit_type: 'first_visit',
    source: 'walk_in',
    priority: 'normal',
    fee_amount: 0,
    reason: '',
  }
}
