import AddIcon from '@mui/icons-material/Add'
import DeleteIcon from '@mui/icons-material/Delete'
import {
  Alert,
  Box,
  Button,
  Chip,
  IconButton,
  Paper,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableRow,
  TextField,
  Typography,
} from '@mui/material'
import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect } from 'react'
import { Controller, useFieldArray, useForm } from 'react-hook-form'
import { Link as RouterLink, useNavigate, useParams, useSearchParams } from 'react-router-dom'
import { z } from 'zod'
import {
  type ConsultationUpdatePayload,
  complaintTextFromConsultation,
  completeConsultation,
  createConsultation,
  diagnosisTextFromConsultation,
  getConsultation,
  prescriptionItemsFromConsultation,
  updateConsultation,
} from '../../api/consultations'
import { getApiErrorMessage } from '../../api/errors'
import { PatientVisitHistoryPanel } from './PatientVisitHistoryPanel'

const prescriptionItemSchema = z.object({
  medicine_name: z.string().min(1, 'Medicine name is required'),
  generic_name: z.string().nullable().optional(),
  formulation: z.string().nullable().optional(),
  strength: z.string().nullable().optional(),
  route: z.string().nullable().optional(),
  frequency: z.string().nullable().optional(),
  duration: z.string().nullable().optional(),
  quantity: z.number().nullable().optional(),
  instructions: z.string().nullable().optional(),
})

const schema = z.object({
  temperature_c: z.number().nullable().optional(),
  pulse_bpm: z.number().nullable().optional(),
  respiratory_rate: z.number().nullable().optional(),
  bp_systolic: z.number().nullable().optional(),
  bp_diastolic: z.number().nullable().optional(),
  spo2_percent: z.number().nullable().optional(),
  height_cm: z.number().nullable().optional(),
  weight_kg: z.number().nullable().optional(),
  chief_complaints_text: z.string(),
  diagnoses_text: z.string(),
  care_plan_notes: z.string().nullable().optional(),
  prescription_items: z.array(prescriptionItemSchema),
})

type ConsultationFormValues = z.infer<typeof schema>

export function ConsultationWorkspacePage() {
  const { id } = useParams<{ id: string }>()
  const isNew = id === 'new'
  const consultationId = isNew ? null : Number(id)
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const queryClient = useQueryClient()
  const queueId = searchParams.get('opd_queue_id')
  const appointmentId = searchParams.get('appointment_id')
  const patientId = searchParams.get('patient_id')

  const startMutation = useMutation({
    mutationFn: () => {
      if (queueId) {
        return createConsultation({ opd_queue_id: Number(queueId) })
      }

      return createConsultation({
        patient_id: patientId ? Number(patientId) : null,
        appointment_id: appointmentId ? Number(appointmentId) : null,
      })
    },
    onSuccess: async (response) => {
      await queryClient.invalidateQueries({ queryKey: ['consultations'] })
      await queryClient.invalidateQueries({ queryKey: ['opd-queue'] })
      navigate(`/opd/consultations/${response.data.id}`, { replace: true })
    },
  })

  useEffect(() => {
    if (!isNew || startMutation.isPending || startMutation.isSuccess || startMutation.isError) {
      return
    }

    if (queueId || (patientId && appointmentId)) {
      startMutation.mutate()
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isNew, queueId, patientId, appointmentId])

  const consultationQuery = useQuery({
    enabled: Boolean(consultationId),
    queryKey: ['consultation', consultationId],
    queryFn: () => getConsultation(consultationId as number),
    retry: false,
  })

  const form = useForm<ConsultationFormValues>({
    resolver: zodResolver(schema),
    defaultValues: defaultValues(),
  })
  const { fields, append, remove } = useFieldArray({ control: form.control, name: 'prescription_items' })

  useEffect(() => {
    const consultation = consultationQuery.data?.data
    if (!consultation) {
      return
    }

    form.reset({
      temperature_c: consultation.vitals?.temperature_c ?? null,
      pulse_bpm: consultation.vitals?.pulse_bpm ?? null,
      respiratory_rate: consultation.vitals?.respiratory_rate ?? null,
      bp_systolic: consultation.vitals?.bp_systolic ?? null,
      bp_diastolic: consultation.vitals?.bp_diastolic ?? null,
      spo2_percent: consultation.vitals?.spo2_percent ?? null,
      height_cm: consultation.vitals?.height_cm ?? null,
      weight_kg: consultation.vitals?.weight_kg ?? null,
      chief_complaints_text: complaintTextFromConsultation(consultation),
      diagnoses_text: diagnosisTextFromConsultation(consultation),
      care_plan_notes: consultation.care_plan?.notes ?? '',
      prescription_items: prescriptionItemsFromConsultation(consultation),
    })
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [consultationQuery.data?.data])

  const toPayload = (values: ConsultationFormValues): ConsultationUpdatePayload => ({
    vitals: {
      temperature_c: values.temperature_c ?? null,
      pulse_bpm: values.pulse_bpm ?? null,
      respiratory_rate: values.respiratory_rate ?? null,
      bp_systolic: values.bp_systolic ?? null,
      bp_diastolic: values.bp_diastolic ?? null,
      spo2_percent: values.spo2_percent ?? null,
      height_cm: values.height_cm ?? null,
      weight_kg: values.weight_kg ?? null,
    },
    chief_complaints: splitList(values.chief_complaints_text),
    diagnoses: splitList(values.diagnoses_text).map((display) => ({ display })),
    care_plan: values.care_plan_notes ? { notes: values.care_plan_notes } : undefined,
    prescription_items: values.prescription_items,
  })

  const saveDraft = useMutation({
    mutationFn: (values: ConsultationFormValues) => {
      if (!consultationId) {
        throw new Error('Consultation has not been started yet.')
      }

      return updateConsultation(consultationId, toPayload(values))
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['consultations'] })
      await queryClient.invalidateQueries({ queryKey: ['consultation', consultationId] })
    },
  })

  const completeMutation = useMutation({
    mutationFn: async (values: ConsultationFormValues) => {
      if (!consultationId) {
        throw new Error('Consultation has not been started yet.')
      }

      await updateConsultation(consultationId, toPayload(values))
      return completeConsultation(consultationId)
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['consultations'] })
      await queryClient.invalidateQueries({ queryKey: ['opd-queue'] })
      navigate('/opd/consultations')
    },
  })

  const consultation = consultationQuery.data?.data
  const isCompleted = consultation?.status === 'completed'
  const missingStartContext = isNew && !queueId && !(patientId && appointmentId)

  return (
    <Stack spacing={3}>
      <Box>
        <Typography component="h1" sx={{ fontWeight: 700 }} variant="h4">
          {isNew ? 'Starting Consultation' : `Consultation ${consultation?.encounter_number ?? ''}`}
        </Typography>
        <Typography color="text.secondary">Vitals, complaints, diagnosis and prescription for this visit.</Typography>
      </Box>

      {missingStartContext ? (
        <Alert
          action={
            <Button color="inherit" component={RouterLink} size="small" to="/opd/queue">
              Open OPD Queue
            </Button>
          }
          severity="info"
        >
          Start a consultation from the OPD queue (or with a patient + appointment). Free-form starts are not allowed.
        </Alert>
      ) : null}

      {startMutation.isError ? (
        <Alert severity="error">{getApiErrorMessage(startMutation.error, 'Unable to start consultation.')}</Alert>
      ) : null}
      {consultationQuery.isError ? (
        <Alert severity="error">{getApiErrorMessage(consultationQuery.error, 'Unable to load consultation.')}</Alert>
      ) : null}
      {saveDraft.isError ? (
        <Alert severity="error">{getApiErrorMessage(saveDraft.error, 'Unable to save consultation.')}</Alert>
      ) : null}
      {completeMutation.isError ? (
        <Alert severity="error">{getApiErrorMessage(completeMutation.error, 'Unable to complete consultation.')}</Alert>
      ) : null}

      {isNew && !missingStartContext ? (
        <Alert severity="info">Starting consultation from queue or appointment…</Alert>
      ) : null}

      {!isNew && consultation ? (
        <Box
          sx={{
            display: 'grid',
            gap: 2,
            gridTemplateColumns: { xs: '1fr', lg: 'minmax(0, 1fr) 320px' },
            alignItems: 'start',
          }}
        >
          <Paper sx={{ p: 3 }} variant="outlined">
            <Stack spacing={3}>
              <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2} sx={{ justifyContent: 'space-between' }}>
                <Box>
                  <Typography sx={{ fontWeight: 700 }} variant="h6">
                    {consultation.patient?.name ?? `Patient #${consultation.patient_id}`}
                  </Typography>
                  <Typography color="text.secondary" variant="body2">
                    UHID {consultation.patient?.uhid ?? '-'} · Doctor {consultation.doctor?.name ?? '-'}
                  </Typography>
                </Box>
                <Chip label={consultation.status} color={isCompleted ? 'success' : 'default'} />
              </Stack>

              {isCompleted ? (
                <Alert severity="success">This consultation is completed and cannot be edited.</Alert>
              ) : null}

              <SectionTitle title="Vitals" />
            <Box sx={{ display: 'grid', gap: 2, gridTemplateColumns: { xs: '1fr', sm: 'repeat(4, minmax(0, 1fr))' } }}>
              <NumberField control={form.control} disabled={isCompleted} label="Temp (°C)" name="temperature_c" />
              <NumberField control={form.control} disabled={isCompleted} label="Pulse (bpm)" name="pulse_bpm" />
              <NumberField control={form.control} disabled={isCompleted} label="Resp. Rate" name="respiratory_rate" />
              <NumberField control={form.control} disabled={isCompleted} label="SpO2 (%)" name="spo2_percent" />
              <NumberField control={form.control} disabled={isCompleted} label="BP Systolic" name="bp_systolic" />
              <NumberField control={form.control} disabled={isCompleted} label="BP Diastolic" name="bp_diastolic" />
              <NumberField control={form.control} disabled={isCompleted} label="Height (cm)" name="height_cm" />
              <NumberField control={form.control} disabled={isCompleted} label="Weight (kg)" name="weight_kg" />
            </Box>

            <SectionTitle title="Complaints & Diagnosis" />
            <Controller
              control={form.control}
              name="chief_complaints_text"
              render={({ field }) => (
                <TextField
                  {...field}
                  disabled={isCompleted}
                  helperText="Comma separated"
                  label="Chief Complaints"
                  multiline
                  rows={2}
                />
              )}
            />
            <Controller
              control={form.control}
              name="diagnoses_text"
              render={({ field }) => (
                <TextField
                  {...field}
                  disabled={isCompleted}
                  helperText="Comma separated"
                  label="Diagnoses"
                  multiline
                  rows={2}
                />
              )}
            />
            <Controller
              control={form.control}
              name="care_plan_notes"
              render={({ field }) => (
                <TextField
                  {...field}
                  disabled={isCompleted}
                  label="Care Plan Notes"
                  multiline
                  rows={2}
                  value={field.value ?? ''}
                />
              )}
            />

            <Stack direction="row" spacing={2} sx={{ alignItems: 'center', justifyContent: 'space-between' }}>
              <SectionTitle title="Prescription" />
              <Button
                disabled={isCompleted}
                onClick={() =>
                  append({
                    medicine_name: '',
                    generic_name: '',
                    formulation: '',
                    strength: '',
                    route: '',
                    frequency: '',
                    duration: '',
                    quantity: null,
                    instructions: '',
                  })
                }
                size="small"
                startIcon={<AddIcon />}
                variant="outlined"
              >
                Add Medicine
              </Button>
            </Stack>
            <Table size="small">
              <TableHead>
                <TableRow>
                  <TableCell>Medicine</TableCell>
                  <TableCell>Strength</TableCell>
                  <TableCell>Route</TableCell>
                  <TableCell>Frequency</TableCell>
                  <TableCell>Duration</TableCell>
                  <TableCell>Instructions</TableCell>
                  <TableCell align="right" />
                </TableRow>
              </TableHead>
              <TableBody>
                {fields.map((field, index) => (
                  <TableRow key={field.id}>
                    <TableCell sx={{ minWidth: 160 }}>
                      <RxField control={form.control} disabled={isCompleted} index={index} name="medicine_name" />
                    </TableCell>
                    <TableCell sx={{ minWidth: 100 }}>
                      <RxField control={form.control} disabled={isCompleted} index={index} name="strength" />
                    </TableCell>
                    <TableCell sx={{ minWidth: 100 }}>
                      <RxField control={form.control} disabled={isCompleted} index={index} name="route" />
                    </TableCell>
                    <TableCell sx={{ minWidth: 120 }}>
                      <RxField control={form.control} disabled={isCompleted} index={index} name="frequency" />
                    </TableCell>
                    <TableCell sx={{ minWidth: 100 }}>
                      <RxField control={form.control} disabled={isCompleted} index={index} name="duration" />
                    </TableCell>
                    <TableCell sx={{ minWidth: 160 }}>
                      <RxField control={form.control} disabled={isCompleted} index={index} name="instructions" />
                    </TableCell>
                    <TableCell align="right">
                      <IconButton
                        aria-label="Remove medicine"
                        disabled={isCompleted}
                        onClick={() => remove(index)}
                        size="small"
                      >
                        <DeleteIcon fontSize="small" />
                      </IconButton>
                    </TableCell>
                  </TableRow>
                ))}
                {fields.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={7}>
                      <Typography color="text.secondary" sx={{ py: 1, textAlign: 'center' }}>
                        No medicines added.
                      </Typography>
                    </TableCell>
                  </TableRow>
                ) : null}
              </TableBody>
            </Table>

            {!isCompleted ? (
              <Stack direction="row" spacing={2}>
                <Button
                  loading={saveDraft.isPending}
                  onClick={form.handleSubmit((values) => saveDraft.mutate(values))}
                  variant="outlined"
                >
                  Save Draft
                </Button>
                <Button
                  loading={completeMutation.isPending}
                  onClick={form.handleSubmit((values) => completeMutation.mutate(values))}
                  variant="contained"
                >
                  Complete Consultation
                </Button>
              </Stack>
            ) : null}
          </Stack>
          </Paper>

          <Paper sx={{ p: 2 }} variant="outlined">
            <PatientVisitHistoryPanel
              excludeEncounterId={consultation.id}
              patientId={consultation.patient_id}
            />
          </Paper>
        </Box>
      ) : null}
    </Stack>
  )
}

function SectionTitle({ title }: { title: string }) {
  return (
    <Typography color="text.secondary" sx={{ fontWeight: 700, textTransform: 'uppercase' }} variant="caption">
      {title}
    </Typography>
  )
}

function NumberField({
  control,
  label,
  name,
  disabled,
}: {
  control: ReturnType<typeof useForm<ConsultationFormValues>>['control']
  label: string
  name: keyof ConsultationFormValues
  disabled?: boolean
}) {
  return (
    <Controller
      control={control}
      name={name}
      render={({ field }) => (
        <TextField
          disabled={disabled}
          label={label}
          onChange={(event) => field.onChange(event.target.value === '' ? null : Number(event.target.value))}
          type="number"
          value={field.value ?? ''}
        />
      )}
    />
  )
}

function RxField({
  control,
  index,
  name,
  disabled,
}: {
  control: ReturnType<typeof useForm<ConsultationFormValues>>['control']
  index: number
  name: 'medicine_name' | 'generic_name' | 'formulation' | 'strength' | 'route' | 'frequency' | 'duration' | 'instructions'
  disabled?: boolean
}) {
  return (
    <Controller
      control={control}
      name={`prescription_items.${index}.${name}` as const}
      render={({ field, fieldState }) => (
        <TextField
          {...field}
          disabled={disabled}
          error={Boolean(fieldState.error)}
          fullWidth
          size="small"
          value={field.value ?? ''}
          variant="standard"
        />
      )}
    />
  )
}

function splitList(value: string): string[] {
  return value
    .split(',')
    .map((item) => item.trim())
    .filter(Boolean)
}

function defaultValues(): ConsultationFormValues {
  return {
    temperature_c: null,
    pulse_bpm: null,
    respiratory_rate: null,
    bp_systolic: null,
    bp_diastolic: null,
    spo2_percent: null,
    height_cm: null,
    weight_kg: null,
    chief_complaints_text: '',
    diagnoses_text: '',
    care_plan_notes: '',
    prescription_items: [],
  }
}
