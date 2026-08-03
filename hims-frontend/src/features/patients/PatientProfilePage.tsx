import AddIcon from '@mui/icons-material/Add'
import CalendarMonthIcon from '@mui/icons-material/CalendarMonth'
import ChevronLeftIcon from '@mui/icons-material/ChevronLeft'
import DescriptionIcon from '@mui/icons-material/Description'
import DirectionsWalkIcon from '@mui/icons-material/DirectionsWalk'
import ReceiptLongIcon from '@mui/icons-material/ReceiptLong'
import {
  Alert,
  Box,
  Button,
  Chip,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  Divider,
  IconButton,
  List,
  ListItem,
  ListItemIcon,
  ListItemText,
  Paper,
  Stack,
  TextField,
  Typography,
} from '@mui/material'
import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Controller, useForm } from 'react-hook-form'
import { Link as RouterLink, useNavigate, useParams, useSearchParams } from 'react-router-dom'
import { z } from 'zod'
import { getApiErrorMessage } from '../../api/errors'
import {
  type PatientDocumentPayload,
  addPatientDocument,
  getPatient,
  getPatientDocuments,
} from '../../api/patients'
import { formatDate } from '../../utils/format'

const documentSchema = z.object({
  document_type: z.string().min(1, 'Document type is required'),
  title: z.string().min(1, 'Title is required'),
  file_path: z.string().nullable().optional(),
  notes: z.string().nullable().optional(),
})

type DocumentFormValues = z.infer<typeof documentSchema>

export function PatientProfilePage() {
  const { id } = useParams<{ id: string }>()
  const patientId = Number(id)
  const navigate = useNavigate()
  const [searchParams, setSearchParams] = useSearchParams()
  const queryClient = useQueryClient()
  const [documentDialogOpen, setDocumentDialogOpen] = useState(false)
  const justRegistered = searchParams.get('registered') === '1'

  const patientQuery = useQuery({
    enabled: Number.isFinite(patientId),
    queryKey: ['patient', patientId],
    queryFn: () => getPatient(patientId),
  })
  const documentsQuery = useQuery({
    enabled: Number.isFinite(patientId),
    queryKey: ['patient-documents', patientId],
    queryFn: () => getPatientDocuments(patientId),
  })

  const form = useForm<DocumentFormValues>({
    resolver: zodResolver(documentSchema),
    defaultValues: { document_type: '', title: '', file_path: '', notes: '' },
  })

  const saveDocument = useMutation({
    mutationFn: (values: DocumentFormValues) => {
      const payload: PatientDocumentPayload = {
        document_type: values.document_type,
        title: values.title,
        file_path: values.file_path || null,
        metadata: values.notes ? { notes: values.notes } : null,
      }

      return addPatientDocument(patientId, payload)
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['patient-documents', patientId] })
      setDocumentDialogOpen(false)
      form.reset({ document_type: '', title: '', file_path: '', notes: '' })
    },
  })

  const dismissRegisteredBanner = () => {
    const next = new URLSearchParams(searchParams)
    next.delete('registered')
    setSearchParams(next, { replace: true })
  }

  const patient = patientQuery.data?.data

  if (patientQuery.isError) {
    return <Alert severity="error">Unable to load patient details.</Alert>
  }

  return (
    <Stack spacing={3}>
      <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
        <IconButton aria-label="Back to patients" onClick={() => navigate('/patients')}>
          <ChevronLeftIcon />
        </IconButton>
        <Typography component="h1" sx={{ fontWeight: 700 }} variant="h4">
          Patient Profile
        </Typography>
      </Stack>

      {justRegistered ? (
        <Alert onClose={dismissRegisteredBanner} severity="success">
          Patient registered successfully. UHID {patient?.uhid ?? '-'}.
        </Alert>
      ) : null}

      {patient ? (
        <Paper sx={{ p: 3 }} variant="outlined">
          <Stack direction={{ xs: 'column', md: 'row' }} spacing={3} sx={{ justifyContent: 'space-between' }}>
            <Stack spacing={1}>
              <Typography sx={{ fontWeight: 700 }} variant="h5">
                {patient.full_name}
              </Typography>
              <Typography color="text.secondary" variant="body2">
                UHID {patient.uhid}
              </Typography>
              <Stack direction="row" sx={{ flexWrap: 'wrap', gap: 1, mt: 1 }}>
                <Chip label={`${patient.gender} / ${formatAge(patient)}`} size="small" variant="outlined" />
                <Chip label={patient.mobile ?? 'No mobile'} size="small" variant="outlined" />
                <Chip label={patient.abha_id ? `ABHA ${patient.abha_id}` : 'No ABHA'} size="small" variant="outlined" />
                <Chip
                  color={patient.status === 'active' ? 'success' : 'default'}
                  label={patient.status}
                  size="small"
                />
                <Chip color="primary" label={patient.patient_category} size="small" variant="outlined" />
              </Stack>
            </Stack>
          </Stack>
        </Paper>
      ) : null}

      <Box sx={{ display: 'grid', gap: 3, gridTemplateColumns: { xs: '1fr', lg: '2fr 1fr' } }}>
        <Paper sx={{ p: 3 }} variant="outlined">
          <Stack direction="row" spacing={2} sx={{ alignItems: 'center', justifyContent: 'space-between', mb: 2 }}>
            <Typography sx={{ fontWeight: 700 }} variant="h6">
              Documents
            </Typography>
            <Button
              disabled={!Number.isFinite(patientId)}
              onClick={() => setDocumentDialogOpen(true)}
              startIcon={<AddIcon />}
              variant="outlined"
            >
              Add Document
            </Button>
          </Stack>
          {documentsQuery.isError ? <Alert severity="error">Unable to load documents.</Alert> : null}
          <List>
            {(documentsQuery.data?.data ?? []).map((document) => (
              <ListItem divider key={document.id}>
                <ListItemIcon>
                  <DescriptionIcon color="action" />
                </ListItemIcon>
                <ListItemText
                  primary={document.title}
                  secondary={`${document.document_type} · ${formatDate(document.created_at)}`}
                />
              </ListItem>
            ))}
            {(documentsQuery.data?.data ?? []).length === 0 ? (
              <Typography color="text.secondary" sx={{ py: 2, textAlign: 'center' }}>
                No documents recorded yet.
              </Typography>
            ) : null}
          </List>
        </Paper>

        <Paper sx={{ p: 3 }} variant="outlined">
          <Typography sx={{ fontWeight: 700, mb: 2 }} variant="h6">
            Next Actions
          </Typography>
          <Stack spacing={1.5}>
            <Button
              component={RouterLink}
              fullWidth
              startIcon={<CalendarMonthIcon />}
              to={`/appointments?patient_id=${patientId}&book=1`}
              variant="contained"
            >
              Book Appointment
            </Button>
            <Button
              component={RouterLink}
              fullWidth
              startIcon={<DirectionsWalkIcon />}
              to={`/appointments?patient_id=${patientId}&book=1&walkin=1`}
              variant="outlined"
            >
              OPD Walk-in
            </Button>
            <Button
              component={RouterLink}
              fullWidth
              startIcon={<ReceiptLongIcon />}
              to={`/billing?patient_id=${patientId}`}
              variant="outlined"
            >
              Billing
            </Button>
          </Stack>
          <Divider sx={{ my: 2 }} />
          <Typography color="text.secondary" variant="body2">
            Registered {patient?.registered_at ? formatDate(patient.registered_at) : '-'}
          </Typography>
        </Paper>
      </Box>

      <Dialog fullWidth maxWidth="sm" onClose={() => setDocumentDialogOpen(false)} open={documentDialogOpen}>
        <Stack component="form" onSubmit={form.handleSubmit((values) => saveDocument.mutate(values))}>
          <DialogTitle>Add Document</DialogTitle>
          <DialogContent>
            <Stack spacing={2} sx={{ pt: 1 }}>
              {saveDocument.isError ? (
                <Alert severity="error">{getApiErrorMessage(saveDocument.error, 'Unable to add document.')}</Alert>
              ) : null}
              <Controller
                control={form.control}
                name="document_type"
                render={({ field, fieldState }) => (
                  <TextField
                    {...field}
                    error={Boolean(fieldState.error)}
                    helperText={fieldState.error?.message}
                    label="Document Type"
                    placeholder="aadhaar, lab_report, prescription..."
                  />
                )}
              />
              <Controller
                control={form.control}
                name="title"
                render={({ field, fieldState }) => (
                  <TextField
                    {...field}
                    error={Boolean(fieldState.error)}
                    helperText={fieldState.error?.message}
                    label="Title"
                  />
                )}
              />
              <Controller
                control={form.control}
                name="file_path"
                render={({ field }) => (
                  <TextField {...field} label="File Reference / URL" value={field.value ?? ''} />
                )}
              />
              <Controller
                control={form.control}
                name="notes"
                render={({ field }) => (
                  <TextField {...field} label="Notes" multiline rows={2} value={field.value ?? ''} />
                )}
              />
            </Stack>
          </DialogContent>
          <DialogActions sx={{ px: 3, pb: 3 }}>
            <Button onClick={() => setDocumentDialogOpen(false)}>Cancel</Button>
            <Button loading={saveDocument.isPending} type="submit" variant="contained">
              Save Document
            </Button>
          </DialogActions>
        </Stack>
      </Dialog>
    </Stack>
  )
}

function formatAge(patient: { age_years: number | null; age_months: number | null; age_days: number | null }): string {
  return (
    [
      patient.age_years !== null ? `${patient.age_years}y` : null,
      patient.age_months !== null ? `${patient.age_months}m` : null,
      patient.age_days !== null ? `${patient.age_days}d` : null,
    ]
      .filter(Boolean)
      .join(' ') || '-'
  )
}
