import {
  Accordion,
  AccordionDetails,
  AccordionSummary,
  Alert,
  Chip,
  Stack,
  Typography,
} from '@mui/material'
import ExpandMoreIcon from '@mui/icons-material/ExpandMore'
import { useQuery } from '@tanstack/react-query'
import { getPatientClinicalHistory, type ClinicalHistoryEncounter } from '../../api/patients'
import { formatDate } from '../../utils/format'

type Props = {
  patientId: number
  excludeEncounterId?: number
}

export function PatientVisitHistoryPanel({ patientId, excludeEncounterId }: Props) {
  const historyQuery = useQuery({
    queryKey: ['clinical-history', patientId, excludeEncounterId],
    queryFn: () =>
      getPatientClinicalHistory(patientId, {
        exclude_encounter_id: excludeEncounterId,
        limit: 10,
      }),
    enabled: patientId > 0,
  })

  const encounters = historyQuery.data?.data.encounters ?? []

  return (
    <Stack spacing={1.5} sx={{ minWidth: { md: 280 }, maxWidth: { md: 340 } }}>
      <Typography sx={{ fontWeight: 700 }} variant="subtitle1">
        Prior visits
      </Typography>
      {historyQuery.isLoading ? (
        <Typography color="text.secondary" variant="body2">
          Loading history…
        </Typography>
      ) : null}
      {historyQuery.isError ? <Alert severity="warning">Unable to load visit history.</Alert> : null}
      {!historyQuery.isLoading && encounters.length === 0 ? (
        <Typography color="text.secondary" variant="body2">
          No prior completed visits for this patient.
        </Typography>
      ) : null}
      {encounters.map((encounter) => (
        <HistoryCard key={encounter.id} encounter={encounter} />
      ))}
    </Stack>
  )
}

function HistoryCard({ encounter }: { encounter: ClinicalHistoryEncounter }) {
  const vitals = encounter.vitals_summary
  const vitalsLine = [
    vitals.temperature_c != null ? `Temp ${vitals.temperature_c}` : null,
    vitals.pulse_bpm != null ? `Pulse ${vitals.pulse_bpm}` : null,
    vitals.bp_systolic != null && vitals.bp_diastolic != null
      ? `BP ${vitals.bp_systolic}/${vitals.bp_diastolic}`
      : null,
    vitals.spo2_percent != null ? `SpO2 ${vitals.spo2_percent}%` : null,
  ]
    .filter(Boolean)
    .join(' · ')

  return (
    <Accordion disableGutters elevation={0} variant="outlined">
      <AccordionSummary expandIcon={<ExpandMoreIcon />}>
        <Stack spacing={0.5} sx={{ pr: 1 }}>
          <Typography sx={{ fontWeight: 600 }} variant="body2">
            {encounter.date ? formatDate(encounter.date) : encounter.encounter_number}
          </Typography>
          <Typography color="text.secondary" variant="caption">
            {encounter.doctor?.name ?? 'Doctor'} · {encounter.department?.name ?? 'OPD'}
          </Typography>
          <Chip label={encounter.status} size="small" sx={{ alignSelf: 'flex-start' }} />
        </Stack>
      </AccordionSummary>
      <AccordionDetails>
        <Stack spacing={1}>
          {encounter.chief_complaints.length > 0 ? (
            <Typography variant="body2">
              <strong>Complaints:</strong> {encounter.chief_complaints.join(', ')}
            </Typography>
          ) : null}
          {encounter.diagnoses.length > 0 ? (
            <Typography variant="body2">
              <strong>Diagnosis:</strong>{' '}
              {encounter.diagnoses.map((item) => item.display).filter(Boolean).join(', ')}
            </Typography>
          ) : null}
          {vitalsLine ? (
            <Typography variant="body2">
              <strong>Vitals:</strong> {vitalsLine}
            </Typography>
          ) : null}
          {encounter.prescription_items.length > 0 ? (
            <Typography variant="body2">
              <strong>Rx:</strong>{' '}
              {encounter.prescription_items
                .map((item) =>
                  [item.medicine_name, item.strength, item.frequency, item.duration].filter(Boolean).join(' '),
                )
                .join('; ')}
            </Typography>
          ) : null}
          {encounter.care_plan_notes ? (
            <Typography variant="body2">
              <strong>Plan:</strong> {encounter.care_plan_notes}
            </Typography>
          ) : null}
          <Typography color="text.secondary" variant="caption">
            {encounter.encounter_number}
          </Typography>
        </Stack>
      </AccordionDetails>
    </Accordion>
  )
}
