import AddIcon from '@mui/icons-material/Add'
import RestartAltIcon from '@mui/icons-material/RestartAlt'
import {
  Alert,
  Box,
  Button,
  Chip,
  MenuItem,
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
import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { Link as RouterLink } from 'react-router-dom'
import { getConsultations } from '../../api/consultations'
import { formatDate } from '../../utils/format'

export function ConsultationsListPage() {
  const [statusFilter, setStatusFilter] = useState<'all' | 'draft' | 'in_progress' | 'completed'>('all')
  const consultationsQuery = useQuery({
    queryKey: ['consultations', statusFilter],
    queryFn: () => getConsultations({ status: statusFilter === 'all' ? undefined : statusFilter }),
  })

  const consultations = consultationsQuery.data?.data ?? []

  return (
    <Stack spacing={3}>
      <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2} sx={{ justifyContent: 'space-between' }}>
        <Box>
          <Typography component="h1" sx={{ fontWeight: 700 }} variant="h4">
            Consultations
          </Typography>
          <Typography color="text.secondary">Doctor consultation notes and prescriptions.</Typography>
        </Box>
        <Button component={RouterLink} startIcon={<AddIcon />} to="/opd/queue" variant="contained">
          Start from OPD Queue
        </Button>
      </Stack>

      {consultationsQuery.isError ? (
        <Alert severity="error">Unable to load consultations. Check that you have the consult permission.</Alert>
      ) : null}

      <Box sx={{ display: 'grid', gap: 2, gridTemplateColumns: { xs: '1fr', md: '220px auto' } }}>
        <TextField
          label="Status"
          onChange={(event) => setStatusFilter(event.target.value as typeof statusFilter)}
          select
          size="small"
          value={statusFilter}
        >
          <MenuItem value="all">All statuses</MenuItem>
          <MenuItem value="draft">Draft</MenuItem>
          <MenuItem value="in_progress">In Progress</MenuItem>
          <MenuItem value="completed">Completed</MenuItem>
        </TextField>
        <Button onClick={() => setStatusFilter('all')} startIcon={<RestartAltIcon />} variant="outlined" sx={{ justifySelf: 'start' }}>
          Reset
        </Button>
      </Box>

      <Paper variant="outlined">
        <Table>
          <TableHead>
            <TableRow>
              <TableCell>Encounter #</TableCell>
              <TableCell>Patient</TableCell>
              <TableCell>Doctor</TableCell>
              <TableCell>Started</TableCell>
              <TableCell>Status</TableCell>
              <TableCell align="right">Actions</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {consultations.map((consultation) => (
              <TableRow key={consultation.id}>
                <TableCell>{consultation.encounter_number}</TableCell>
                <TableCell>{consultation.patient?.name ?? '-'}</TableCell>
                <TableCell>{consultation.doctor?.name ?? '-'}</TableCell>
                <TableCell>{formatDate(consultation.started_at)}</TableCell>
                <TableCell>
                  <Chip label={consultation.status} size="small" />
                </TableCell>
                <TableCell align="right">
                  <Button component={RouterLink} size="small" to={`/opd/consultations/${consultation.id}`}>
                    Open
                  </Button>
                </TableCell>
              </TableRow>
            ))}
            {consultations.length === 0 && !consultationsQuery.isError ? (
              <TableRow>
                <TableCell colSpan={6}>
                  <Typography color="text.secondary" sx={{ py: 2, textAlign: 'center' }}>
                    No consultations recorded yet.
                  </Typography>
                </TableCell>
              </TableRow>
            ) : null}
          </TableBody>
        </Table>
      </Paper>
    </Stack>
  )
}
