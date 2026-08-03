import AddIcon from '@mui/icons-material/Add'
import FavoriteIcon from '@mui/icons-material/Favorite'
import LocalHotelIcon from '@mui/icons-material/LocalHotel'
import SwapHorizIcon from '@mui/icons-material/SwapHoriz'
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
  MenuItem,
  Paper,
  Stack,
  TextField,
  Typography,
} from '@mui/material'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useMemo, useState } from 'react'
import { Link as RouterLink } from 'react-router-dom'
import { getAppointmentOptions } from '../../api/appointments'
import { getApiErrorMessage } from '../../api/errors'
import {
  type Admission,
  type BedBoardEntry,
  type DischargeOutcome,
  addNursingNote,
  admitPatient,
  dischargeAdmission,
  getAdmissions,
  getBedBoard,
  getClearances,
  getIpdCharges,
  getNursingNotes,
  getWards,
  postDailyIpdCharges,
  transferAdmission,
  updateClearance,
} from '../../api/ipd'
import { getPatients } from '../../api/patients'
import { usePermissions } from '../auth/usePermissions'

const OUTCOMES: Array<{ value: DischargeOutcome; label: string }> = [
  { value: 'discharge', label: 'Discharge' },
  { value: 'lama', label: 'LAMA' },
  { value: 'dopr', label: 'DOPR' },
  { value: 'death', label: 'Death' },
]

const CLEARANCE_LABELS: Record<string, string> = {
  nursing: 'Nursing',
  diagnostics: 'Diagnostics',
  billing: 'Billing',
  ward: 'Ward',
}

function bedTone(status: string) {
  if (status === 'available') return 'success'
  if (status === 'occupied') return 'warning'
  return 'default'
}

function clearanceTone(status: string) {
  if (status === 'cleared' || status === 'waived') return 'success'
  return 'warning'
}

export function IpdPage() {
  const queryClient = useQueryClient()
  const { can } = usePermissions()
  const canManage = can('ipd.manage')
  const canPickPatients = can('patients.manage')

  const [wardFilter, setWardFilter] = useState<'all' | number>('all')
  const [admitOpen, setAdmitOpen] = useState(false)
  const [transferTarget, setTransferTarget] = useState<Admission | null>(null)
  const [exitTarget, setExitTarget] = useState<Admission | null>(null)
  const [careTarget, setCareTarget] = useState<Admission | null>(null)
  const [nursingText, setNursingText] = useState('')
  const [pulse, setPulse] = useState('')
  const [bpSys, setBpSys] = useState('')
  const [bpDia, setBpDia] = useState('')

  const [patientId, setPatientId] = useState<number | null>(null)
  const [bedId, setBedId] = useState<number | null>(null)
  const [doctorId, setDoctorId] = useState<number | null>(null)
  const [diagnosis, setDiagnosis] = useState('')
  const [attendantName, setAttendantName] = useState('')
  const [attendantMobile, setAttendantMobile] = useState('')

  const [transferBedId, setTransferBedId] = useState<number | null>(null)
  const [transferReason, setTransferReason] = useState('')

  const [outcome, setOutcome] = useState<DischargeOutcome>('discharge')
  const [exitSummary, setExitSummary] = useState('')
  const [deathAt, setDeathAt] = useState('')

  const [exitInvoiceNumber, setExitInvoiceNumber] = useState<string | null>(null)

  const wardsQuery = useQuery({ queryKey: ['ipd-wards'], queryFn: getWards, enabled: canManage })
  const boardQuery = useQuery({
    queryKey: ['ipd-board', wardFilter],
    queryFn: () => getBedBoard(wardFilter === 'all' ? {} : { ward_id: wardFilter }),
    enabled: canManage,
    refetchInterval: 20000,
  })
  const admissionsQuery = useQuery({
    queryKey: ['ipd-admissions-active'],
    queryFn: () => getAdmissions({ status: 'admitted' }),
    enabled: canManage,
  })
  const patientsQuery = useQuery({
    queryKey: ['patients-for-ipd'],
    queryFn: getPatients,
    enabled: canPickPatients && admitOpen,
  })
  const optionsQuery = useQuery({
    queryKey: ['appointment-options-ipd'],
    queryFn: getAppointmentOptions,
    enabled: can('appointments.manage') && admitOpen,
  })

  const nursingQuery = useQuery({
    queryKey: ['ipd-nursing', careTarget?.id],
    queryFn: () => getNursingNotes(careTarget!.id),
    enabled: Boolean(careTarget),
  })
  const chargesQuery = useQuery({
    queryKey: ['ipd-charges', careTarget?.id],
    queryFn: () => getIpdCharges(careTarget!.id),
    enabled: Boolean(careTarget),
  })
  const clearancesQuery = useQuery({
    queryKey: ['ipd-clearances', careTarget?.id ?? exitTarget?.id],
    queryFn: () => getClearances((careTarget ?? exitTarget)!.id),
    enabled: Boolean(careTarget || exitTarget),
  })

  const refresh = async () => {
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: ['ipd-board'] }),
      queryClient.invalidateQueries({ queryKey: ['ipd-admissions-active'] }),
      queryClient.invalidateQueries({ queryKey: ['ipd-nursing'] }),
      queryClient.invalidateQueries({ queryKey: ['ipd-charges'] }),
      queryClient.invalidateQueries({ queryKey: ['ipd-clearances'] }),
    ])
  }

  const admitMutation = useMutation({
    mutationFn: admitPatient,
    onSuccess: async () => {
      await refresh()
      setAdmitOpen(false)
      setPatientId(null)
      setBedId(null)
      setDoctorId(null)
      setDiagnosis('')
      setAttendantName('')
      setAttendantMobile('')
    },
  })

  const transferMutation = useMutation({
    mutationFn: ({ id, bed_id, reason }: { id: number; bed_id: number; reason?: string }) =>
      transferAdmission(id, { bed_id, reason }),
    onSuccess: async () => {
      await refresh()
      setTransferTarget(null)
      setTransferBedId(null)
      setTransferReason('')
    },
  })

  const exitMutation = useMutation({
    mutationFn: ({
      id,
      payload,
    }: {
      id: number
      payload: { outcome: DischargeOutcome; discharge_summary: string; death_at?: string }
    }) => dischargeAdmission(id, { ...payload, create_invoice: true }),
    onSuccess: async (response) => {
      await refresh()
      setExitInvoiceNumber(response.data.invoice?.invoice_number ?? null)
      setExitTarget(null)
      setExitSummary('')
      setDeathAt('')
      setOutcome('discharge')
    },
  })

  const nursingMutation = useMutation({
    mutationFn: () => {
      if (!careTarget) throw new Error('No admission')
      return addNursingNote(careTarget.id, {
        notes: nursingText || undefined,
        vitals: {
          pulse_bpm: pulse ? Number(pulse) : null,
          bp_systolic: bpSys ? Number(bpSys) : null,
          bp_diastolic: bpDia ? Number(bpDia) : null,
        },
      })
    },
    onSuccess: async () => {
      setNursingText('')
      setPulse('')
      setBpSys('')
      setBpDia('')
      await refresh()
    },
  })

  const dailyChargeMutation = useMutation({
    mutationFn: () => {
      if (!careTarget) throw new Error('No admission')
      return postDailyIpdCharges(careTarget.id)
    },
    onSuccess: refresh,
  })

  const clearanceMutation = useMutation({
    mutationFn: (payload: { clearance_type: string; status: 'cleared' | 'waived' }) => {
      const id = careTarget?.id ?? exitTarget?.id
      if (!id) throw new Error('No admission')
      return updateClearance(id, payload)
    },
    onSuccess: refresh,
  })

  const wards = wardsQuery.data?.data ?? []
  const beds = boardQuery.data?.data ?? []
  const admissions = admissionsQuery.data?.data ?? []
  const patients = patientsQuery.data?.data ?? []
  const doctors = optionsQuery.data?.data?.doctors ?? []
  const nursingNotes = nursingQuery.data?.data ?? []
  const chargeLines = chargesQuery.data?.data ?? []
  const clearances = clearancesQuery.data?.data ?? []
  const clearancesReady =
    clearances.length > 0 && clearances.every((item) => item.status === 'cleared' || item.status === 'waived')

  const availableBeds = useMemo(() => beds.filter((bed) => bed.status === 'available'), [beds])

  const selectedPatient = useMemo(
    () => patients.find((patient) => patient.id === patientId) ?? null,
    [patients, patientId],
  )

  const groupedBeds = useMemo(() => {
    const map = new Map<string, BedBoardEntry[]>()
    for (const bed of beds) {
      const key = bed.ward?.name ?? `Ward #${bed.ward_id}`
      const list = map.get(key) ?? []
      list.push(bed)
      map.set(key, list)
    }
    return [...map.entries()]
  }, [beds])

  const actionError =
    admitMutation.error ||
    transferMutation.error ||
    exitMutation.error ||
    nursingMutation.error ||
    dailyChargeMutation.error ||
    clearanceMutation.error

  return (
    <Stack spacing={3}>
      <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2} sx={{ justifyContent: 'space-between' }}>
        <Box>
          <Typography component="h1" sx={{ fontWeight: 700 }} variant="h4">
            IPD
          </Typography>
          <Typography color="text.secondary">Indoor admit, bed board, transfer, and exit package (discharge / LAMA / DOPR / death).</Typography>
        </Box>
        {canPickPatients ? (
          <Button startIcon={<AddIcon />} variant="contained" onClick={() => setAdmitOpen(true)}>
            Admit patient
          </Button>
        ) : null}
      </Stack>

      <TextField
        label="Ward"
        select
        size="small"
        value={wardFilter === 'all' ? 'all' : String(wardFilter)}
        onChange={(event) =>
          setWardFilter(event.target.value === 'all' ? 'all' : Number(event.target.value))
        }
        sx={{ maxWidth: 260 }}
      >
        <MenuItem value="all">All wards</MenuItem>
        {wards.map((ward) => (
          <MenuItem key={ward.id} value={String(ward.id)}>
            {ward.name}
          </MenuItem>
        ))}
      </TextField>

      {boardQuery.isError ? (
        <Alert severity="error">{getApiErrorMessage(boardQuery.error, 'Unable to load bed board.')}</Alert>
      ) : null}
      {actionError ? (
        <Alert severity="error">{getApiErrorMessage(actionError, 'IPD action failed.')}</Alert>
      ) : null}
      {exitInvoiceNumber ? (
        <Alert severity="success" onClose={() => setExitInvoiceNumber(null)}>
          Exit recorded. Draft IPD invoice {exitInvoiceNumber} created.{' '}
          <Button component={RouterLink} to="/billing" size="small">
            Open billing
          </Button>
        </Alert>
      ) : null}

      {groupedBeds.map(([wardName, wardBeds]) => (
        <Paper key={wardName} variant="outlined" sx={{ p: 2 }}>
          <Typography variant="h6" sx={{ mb: 2 }}>
            {wardName}
          </Typography>
          <Box
            sx={{
              display: 'grid',
              gap: 1.5,
              gridTemplateColumns: { xs: '1fr 1fr', sm: 'repeat(3, 1fr)', md: 'repeat(4, 1fr)' },
            }}
          >
            {wardBeds.map((bed) => (
              <Paper key={bed.id} variant="outlined" sx={{ p: 1.5 }}>
                <Stack spacing={1}>
                  <Stack direction="row" spacing={1} sx={{ alignItems: 'center', justifyContent: 'space-between' }}>
                    <Typography sx={{ fontWeight: 600 }}>{bed.bed_number}</Typography>
                    <Chip size="small" color={bedTone(bed.status)} label={bed.status} />
                  </Stack>
                  <Typography variant="body2" color="text.secondary" sx={{ textTransform: 'capitalize' }}>
                    {bed.bed_type}
                  </Typography>
                  {bed.current_admission ? (
                    <>
                      <Typography variant="body2">{bed.current_admission.patient?.name}</Typography>
                      <Typography variant="caption" color="text.secondary">
                        {bed.current_admission.admission_number}
                      </Typography>
                    </>
                  ) : (
                    <Typography variant="body2" color="text.secondary">
                      Empty
                    </Typography>
                  )}
                </Stack>
              </Paper>
            ))}
          </Box>
        </Paper>
      ))}

      <Paper variant="outlined" sx={{ p: 2 }}>
        <Typography variant="h6" sx={{ mb: 2 }}>
          Active admissions
        </Typography>
        <Stack spacing={1.5}>
          {admissions.length === 0 ? (
            <Typography color="text.secondary">No active indoor patients.</Typography>
          ) : (
            admissions.map((admission) => (
              <Paper key={admission.id} variant="outlined" sx={{ p: 1.5 }}>
                <Stack direction={{ xs: 'column', md: 'row' }} spacing={1} sx={{ justifyContent: 'space-between' }}>
                  <Box>
                    <Typography sx={{ fontWeight: 600 }}>
                      {admission.admission_number} — {admission.patient?.name}
                    </Typography>
                    <Typography variant="body2" color="text.secondary">
                      {admission.ward?.name} / {admission.bed?.bed_number}
                      {admission.provisional_diagnosis ? ` · ${admission.provisional_diagnosis}` : ''}
                    </Typography>
                  </Box>
                  <Stack direction="row" spacing={1}>
                    <Button
                      size="small"
                      startIcon={<FavoriteIcon />}
                      variant="outlined"
                      onClick={() => {
                        setCareTarget(admission)
                        setNursingText('')
                      }}
                    >
                      Care
                    </Button>
                    <Button
                      size="small"
                      startIcon={<SwapHorizIcon />}
                      variant="outlined"
                      onClick={() => {
                        setTransferTarget(admission)
                        setTransferBedId(null)
                      }}
                    >
                      Transfer
                    </Button>
                    <Button
                      size="small"
                      startIcon={<LocalHotelIcon />}
                      variant="contained"
                      onClick={() => {
                        setExitTarget(admission)
                        setOutcome('discharge')
                        setExitSummary('')
                      }}
                    >
                      Exit
                    </Button>
                  </Stack>
                </Stack>
              </Paper>
            ))
          )}
        </Stack>
      </Paper>

      <Drawer
        anchor="right"
        open={admitOpen}
        onClose={() => setAdmitOpen(false)}
        slotProps={{ paper: { sx: { width: { xs: '100%', sm: 440 }, p: 3 } } }}
      >
        <Typography component="h2" variant="h6" sx={{ mb: 2 }}>
          Indoor registration
        </Typography>
        <Stack spacing={2}>
          <Autocomplete
            options={patients}
            value={selectedPatient}
            onChange={(_, value) => setPatientId(value?.id ?? null)}
            getOptionLabel={(option) => `${option.uhid} — ${option.full_name}`}
            renderInput={(params) => <TextField {...params} label="Patient" required />}
          />
          <TextField
            select
            label="Bed"
            value={bedId ?? ''}
            onChange={(event) => setBedId(Number(event.target.value))}
            required
          >
            {availableBeds.map((bed) => (
              <MenuItem key={bed.id} value={bed.id}>
                {bed.ward?.name} / {bed.bed_number}
              </MenuItem>
            ))}
          </TextField>
          <TextField
            select
            label="Admitting doctor"
            value={doctorId ?? ''}
            onChange={(event) => setDoctorId(event.target.value ? Number(event.target.value) : null)}
          >
            <MenuItem value="">Unassigned</MenuItem>
            {doctors.map((doctor) => (
              <MenuItem key={doctor.id} value={doctor.id}>
                {doctor.name}
              </MenuItem>
            ))}
          </TextField>
          <TextField
            label="Provisional diagnosis"
            value={diagnosis}
            onChange={(event) => setDiagnosis(event.target.value)}
          />
          <TextField
            label="Attendant name"
            value={attendantName}
            onChange={(event) => setAttendantName(event.target.value)}
          />
          <TextField
            label="Attendant mobile"
            value={attendantMobile}
            onChange={(event) => setAttendantMobile(event.target.value)}
          />
          {admitMutation.isError ? (
            <Alert severity="error">{getApiErrorMessage(admitMutation.error, 'Unable to admit patient.')}</Alert>
          ) : null}
          <Stack direction="row" spacing={1} sx={{ justifyContent: 'flex-end' }}>
            <Button onClick={() => setAdmitOpen(false)}>Cancel</Button>
            <Button
              variant="contained"
              disabled={!patientId || !bedId || admitMutation.isPending}
              onClick={() => {
                if (!patientId || !bedId) return
                admitMutation.mutate({
                  patient_id: patientId,
                  bed_id: bedId,
                  admitting_doctor_user_id: doctorId ?? undefined,
                  provisional_diagnosis: diagnosis || undefined,
                  attendant_name: attendantName || undefined,
                  attendant_mobile: attendantMobile || undefined,
                })
              }}
            >
              Admit
            </Button>
          </Stack>
        </Stack>
      </Drawer>

      <Dialog open={Boolean(transferTarget)} onClose={() => setTransferTarget(null)} fullWidth maxWidth="sm">
        <DialogTitle>Transfer — {transferTarget?.admission_number}</DialogTitle>
        <DialogContent>
          <Stack spacing={2} sx={{ mt: 1 }}>
            <TextField
              select
              label="New bed"
              value={transferBedId ?? ''}
              onChange={(event) => setTransferBedId(Number(event.target.value))}
            >
              {availableBeds.map((bed) => (
                <MenuItem key={bed.id} value={bed.id}>
                  {bed.ward?.name} / {bed.bed_number}
                </MenuItem>
              ))}
            </TextField>
            <TextField
              label="Reason"
              value={transferReason}
              onChange={(event) => setTransferReason(event.target.value)}
            />
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setTransferTarget(null)}>Cancel</Button>
          <Button
            variant="contained"
            disabled={!transferBedId || transferMutation.isPending}
            onClick={() => {
              if (!transferTarget || !transferBedId) return
              transferMutation.mutate({
                id: transferTarget.id,
                bed_id: transferBedId,
                reason: transferReason || undefined,
              })
            }}
          >
            Transfer
          </Button>
        </DialogActions>
      </Dialog>

      <Dialog open={Boolean(exitTarget)} onClose={() => setExitTarget(null)} fullWidth maxWidth="sm">
        <DialogTitle>Exit package — {exitTarget?.admission_number}</DialogTitle>
        <DialogContent>
          <Stack spacing={2} sx={{ mt: 1 }}>
            <Box>
              <Typography variant="subtitle2" sx={{ mb: 1 }}>
                Clearances (required)
              </Typography>
              <Stack direction="row" spacing={1} sx={{ flexWrap: 'wrap', gap: 1 }}>
                {clearances.map((item) => (
                  <Chip
                    key={item.clearance_type}
                    size="small"
                    color={clearanceTone(item.status)}
                    label={`${CLEARANCE_LABELS[item.clearance_type] ?? item.clearance_type}: ${item.status}`}
                    onClick={() =>
                      clearanceMutation.mutate({
                        clearance_type: item.clearance_type,
                        status: 'cleared',
                      })
                    }
                  />
                ))}
              </Stack>
              {!clearancesReady ? (
                <Typography variant="caption" color="warning.main">
                  Click each chip to clear/waive before finalizing exit.
                </Typography>
              ) : null}
            </Box>
            <TextField
              select
              label="Outcome"
              value={outcome}
              onChange={(event) => setOutcome(event.target.value as DischargeOutcome)}
            >
              {OUTCOMES.map((item) => (
                <MenuItem key={item.value} value={item.value}>
                  {item.label}
                </MenuItem>
              ))}
            </TextField>
            {outcome === 'death' ? (
              <TextField
                label="Death date/time"
                type="datetime-local"
                value={deathAt}
                onChange={(event) => setDeathAt(event.target.value)}
                slotProps={{ inputLabel: { shrink: true } }}
              />
            ) : null}
            <TextField
              label="Summary"
              multiline
              minRows={4}
              value={exitSummary}
              onChange={(event) => setExitSummary(event.target.value)}
              helperText="Stay documents + diagnostics are packaged automatically. Final bill uses posted daily charges."
            />
            {exitMutation.isError ? (
              <Alert severity="error">{getApiErrorMessage(exitMutation.error, 'Unable to complete exit.')}</Alert>
            ) : null}
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setExitTarget(null)}>Cancel</Button>
          <Button
            variant="contained"
            disabled={
              !clearancesReady ||
              !exitSummary.trim() ||
              (outcome === 'death' && !deathAt) ||
              exitMutation.isPending
            }
            onClick={() => {
              if (!exitTarget) return
              exitMutation.mutate({
                id: exitTarget.id,
                payload: {
                  outcome,
                  discharge_summary: exitSummary.trim(),
                  death_at: outcome === 'death' && deathAt ? new Date(deathAt).toISOString() : undefined,
                },
              })
            }}
          >
            Finalize exit
          </Button>
        </DialogActions>
      </Dialog>

      <Dialog open={Boolean(careTarget)} onClose={() => setCareTarget(null)} fullWidth maxWidth="md">
        <DialogTitle>Care — {careTarget?.admission_number}</DialogTitle>
        <DialogContent>
          <Stack spacing={3} sx={{ mt: 1 }}>
            <Box>
              <Typography variant="subtitle1" sx={{ mb: 1 }}>
                Nursing chart
              </Typography>
              <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1} sx={{ mb: 1 }}>
                <TextField label="Pulse" size="small" value={pulse} onChange={(e) => setPulse(e.target.value)} />
                <TextField label="BP sys" size="small" value={bpSys} onChange={(e) => setBpSys(e.target.value)} />
                <TextField label="BP dia" size="small" value={bpDia} onChange={(e) => setBpDia(e.target.value)} />
              </Stack>
              <TextField
                label="Notes"
                fullWidth
                multiline
                minRows={2}
                value={nursingText}
                onChange={(e) => setNursingText(e.target.value)}
                sx={{ mb: 1 }}
              />
              <Button
                variant="contained"
                disabled={nursingMutation.isPending || (!nursingText.trim() && !pulse && !bpSys)}
                onClick={() => nursingMutation.mutate()}
              >
                Save nursing entry
              </Button>
              <Stack spacing={1} sx={{ mt: 2 }}>
                {nursingNotes.map((note) => (
                  <Paper key={note.id} variant="outlined" sx={{ p: 1 }}>
                    <Typography variant="caption" color="text.secondary">
                      {note.recorded_at} · {note.recorded_by?.name ?? 'Staff'}
                    </Typography>
                    <Typography variant="body2">{note.notes || 'Vitals only'}</Typography>
                  </Paper>
                ))}
              </Stack>
            </Box>

            <Box>
              <Stack direction="row" spacing={1} sx={{ alignItems: 'center', mb: 1, justifyContent: 'space-between' }}>
                <Typography variant="subtitle1">Daily charges</Typography>
                <Button size="small" variant="outlined" onClick={() => dailyChargeMutation.mutate()} disabled={dailyChargeMutation.isPending}>
                  Post missing bed-days
                </Button>
              </Stack>
              {chargeLines.length === 0 ? (
                <Typography color="text.secondary">No charges yet.</Typography>
              ) : (
                chargeLines.map((line) => (
                  <Typography key={line.id} variant="body2">
                    {line.charge_date} — {line.description}: ₹{line.amount} ({line.status})
                  </Typography>
                ))
              )}
            </Box>

            <Box>
              <Typography variant="subtitle1" sx={{ mb: 1 }}>
                Clearances
              </Typography>
              <Stack direction="row" spacing={1} sx={{ flexWrap: 'wrap', gap: 1 }}>
                {clearances.map((item) => (
                  <Chip
                    key={item.clearance_type}
                    color={clearanceTone(item.status)}
                    label={`${CLEARANCE_LABELS[item.clearance_type] ?? item.clearance_type}: ${item.status}`}
                    onClick={() =>
                      clearanceMutation.mutate({
                        clearance_type: item.clearance_type,
                        status: 'cleared',
                      })
                    }
                  />
                ))}
              </Stack>
            </Box>
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setCareTarget(null)}>Close</Button>
        </DialogActions>
      </Dialog>
    </Stack>
  )
}
