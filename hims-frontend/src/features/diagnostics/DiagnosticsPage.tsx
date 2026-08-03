import AddIcon from '@mui/icons-material/Add'
import BiotechIcon from '@mui/icons-material/Biotech'
import CancelIcon from '@mui/icons-material/Cancel'
import ReceiptLongIcon from '@mui/icons-material/ReceiptLong'
import ScienceIcon from '@mui/icons-material/Science'
import {
  Alert,
  Autocomplete,
  Box,
  Button,
  Checkbox,
  Chip,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  Drawer,
  FormControlLabel,
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
import { useMemo, useState } from 'react'
import { Link as RouterLink } from 'react-router-dom'
import { getApiErrorMessage } from '../../api/errors'
import {
  type DiagnosticCategory,
  type DiagnosticOrder,
  type DiagnosticOrderStatus,
  billDiagnosticOrder,
  cancelDiagnosticOrder,
  collectDiagnosticOrder,
  createDiagnosticOrder,
  getDiagnosticCatalog,
  getDiagnosticOrders,
  resultDiagnosticOrder,
} from '../../api/diagnostics'
import { getPatients } from '../../api/patients'
import { usePermissions } from '../auth/usePermissions'
import { formatDate, todayIso } from '../../utils/format'

const CATEGORY_OPTIONS: Array<{ value: DiagnosticCategory; label: string }> = [
  { value: 'pathology', label: 'Pathology' },
  { value: 'radiology', label: 'Radiology' },
  { value: 'procedure', label: 'Procedure' },
]

const STATUS_OPTIONS: Array<{ value: DiagnosticOrderStatus; label: string }> = [
  { value: 'ordered', label: 'Ordered' },
  { value: 'sample_collected', label: 'Collected' },
  { value: 'in_progress', label: 'In Progress' },
  { value: 'result_ready', label: 'Result Ready' },
  { value: 'billed', label: 'Billed' },
  { value: 'cancelled', label: 'Cancelled' },
]

export function DiagnosticsPage() {
  const queryClient = useQueryClient()
  const { can } = usePermissions()
  const canOrder = can('diagnostics.order')
  const canResult = can('diagnostics.result')
  const canBill = can('billing.manage')
  const canPickPatients = can('patients.manage')

  const [categoryFilter, setCategoryFilter] = useState<'all' | DiagnosticCategory>('all')
  const [statusFilter, setStatusFilter] = useState<'all' | DiagnosticOrderStatus>('all')
  const [dateFilter, setDateFilter] = useState(todayIso())
  const [drawerOpen, setDrawerOpen] = useState(false)
  const [resultTarget, setResultTarget] = useState<DiagnosticOrder | null>(null)
  const [resultSummary, setResultSummary] = useState('')

  const [category, setCategory] = useState<DiagnosticCategory>('pathology')
  const [patientId, setPatientId] = useState<number | null>(null)
  const [priority, setPriority] = useState<'routine' | 'urgent'>('routine')
  const [clinicalNotes, setClinicalNotes] = useState('')
  const [selectedServiceIds, setSelectedServiceIds] = useState<number[]>([])

  const ordersQuery = useQuery({
    queryKey: ['diagnostic-orders', categoryFilter, statusFilter, dateFilter],
    queryFn: () =>
      getDiagnosticOrders({
        category: categoryFilter === 'all' ? undefined : categoryFilter,
        status: statusFilter === 'all' ? undefined : statusFilter,
        date: dateFilter || undefined,
      }),
  })

  const patientsQuery = useQuery({
    queryKey: ['patients-for-diagnostics'],
    queryFn: getPatients,
    enabled: canPickPatients && drawerOpen,
  })

  const catalogQuery = useQuery({
    queryKey: ['diagnostic-catalog', category],
    queryFn: () => getDiagnosticCatalog(category),
    enabled: drawerOpen && canOrder,
  })

  const refresh = async () => {
    await queryClient.invalidateQueries({ queryKey: ['diagnostic-orders'] })
  }

  const createMutation = useMutation({
    mutationFn: createDiagnosticOrder,
    onSuccess: async () => {
      await refresh()
      setDrawerOpen(false)
      setSelectedServiceIds([])
      setClinicalNotes('')
      setPatientId(null)
    },
  })

  const collectMutation = useMutation({
    mutationFn: collectDiagnosticOrder,
    onSuccess: refresh,
  })

  const cancelMutation = useMutation({
    mutationFn: cancelDiagnosticOrder,
    onSuccess: refresh,
  })

  const resultMutation = useMutation({
    mutationFn: ({ id, summary }: { id: number; summary: string }) =>
      resultDiagnosticOrder(id, { result_summary: summary }),
    onSuccess: async () => {
      await refresh()
      setResultTarget(null)
      setResultSummary('')
    },
  })

  const billMutation = useMutation({
    mutationFn: billDiagnosticOrder,
    onSuccess: refresh,
  })

  const patients = patientsQuery.data?.data ?? []
  const catalog = catalogQuery.data?.data ?? []
  const orders = ordersQuery.data?.data ?? []

  const selectedPatient = useMemo(
    () => patients.find((patient) => patient.id === patientId) ?? null,
    [patients, patientId],
  )

  const actionError =
    createMutation.error ||
    collectMutation.error ||
    cancelMutation.error ||
    resultMutation.error ||
    billMutation.error

  return (
    <Stack spacing={3}>
      <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2} sx={{ justifyContent: 'space-between' }}>
        <Box>
          <Typography component="h1" sx={{ fontWeight: 700 }} variant="h4">
            Diagnostics
          </Typography>
          <Typography color="text.secondary">Pathology, radiology and procedure orders → result → bill.</Typography>
        </Box>
        {canOrder && canPickPatients ? (
          <Button startIcon={<AddIcon />} variant="contained" onClick={() => setDrawerOpen(true)}>
            New order
          </Button>
        ) : null}
      </Stack>

      <Box
        sx={{
          alignItems: { xs: 'stretch', md: 'center' },
          display: 'grid',
          gap: 2,
          gridTemplateColumns: { xs: '1fr', md: '180px 180px 200px' },
        }}
      >
        <TextField
          label="Category"
          select
          size="small"
          value={categoryFilter}
          onChange={(event) => setCategoryFilter(event.target.value as 'all' | DiagnosticCategory)}
        >
          <MenuItem value="all">All</MenuItem>
          {CATEGORY_OPTIONS.map((option) => (
            <MenuItem key={option.value} value={option.value}>
              {option.label}
            </MenuItem>
          ))}
        </TextField>
        <TextField
          label="Status"
          select
          size="small"
          value={statusFilter}
          onChange={(event) => setStatusFilter(event.target.value as 'all' | DiagnosticOrderStatus)}
        >
          <MenuItem value="all">All</MenuItem>
          {STATUS_OPTIONS.map((option) => (
            <MenuItem key={option.value} value={option.value}>
              {option.label}
            </MenuItem>
          ))}
        </TextField>
        <TextField
          label="Ordered date"
          type="date"
          size="small"
          value={dateFilter}
          onChange={(event) => setDateFilter(event.target.value)}
          slotProps={{ inputLabel: { shrink: true } }}
        />
      </Box>

      {ordersQuery.isError ? (
        <Alert severity="error">{getApiErrorMessage(ordersQuery.error, 'Unable to load diagnostic orders.')}</Alert>
      ) : null}
      {actionError ? (
        <Alert severity="error">{getApiErrorMessage(actionError, 'Diagnostics action failed.')}</Alert>
      ) : null}

      <Paper variant="outlined">
        <Table size="small">
          <TableHead>
            <TableRow>
              <TableCell>Order</TableCell>
              <TableCell>Patient</TableCell>
              <TableCell>Category</TableCell>
              <TableCell>Items</TableCell>
              <TableCell>Status</TableCell>
              <TableCell>Ordered</TableCell>
              <TableCell align="right">Actions</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {orders.length === 0 ? (
              <TableRow>
                <TableCell colSpan={7}>
                  <Typography color="text.secondary" sx={{ py: 2 }}>
                    No diagnostic orders for these filters.
                  </Typography>
                </TableCell>
              </TableRow>
            ) : (
              orders.map((order) => (
                <TableRow key={order.id} hover>
                  <TableCell>
                    <Typography sx={{ fontWeight: 600 }}>{order.order_number}</Typography>
                    {order.priority === 'urgent' ? <Chip size="small" color="warning" label="Urgent" sx={{ mt: 0.5 }} /> : null}
                  </TableCell>
                  <TableCell>
                    <Typography>{order.patient?.name ?? `Patient #${order.patient_id}`}</Typography>
                    <Typography variant="body2" color="text.secondary">
                      {order.patient?.uhid}
                    </Typography>
                  </TableCell>
                  <TableCell sx={{ textTransform: 'capitalize' }}>{order.category}</TableCell>
                  <TableCell>{(order.items ?? []).map((item) => item.service_name).join(', ') || '—'}</TableCell>
                  <TableCell>
                    <Chip size="small" label={order.status.replaceAll('_', ' ')} />
                  </TableCell>
                  <TableCell>{order.ordered_at ? formatDate(order.ordered_at.slice(0, 10)) : '—'}</TableCell>
                  <TableCell align="right">
                    <Stack direction="row" spacing={0.5} sx={{ justifyContent: 'flex-end' }}>
                      {canResult && ['ordered', 'in_progress'].includes(order.status) ? (
                        <Tooltip title="Mark collected">
                          <IconButton
                            size="small"
                            onClick={() => collectMutation.mutate(order.id)}
                            disabled={collectMutation.isPending}
                          >
                            <ScienceIcon fontSize="small" />
                          </IconButton>
                        </Tooltip>
                      ) : null}
                      {canResult && ['ordered', 'sample_collected', 'in_progress'].includes(order.status) ? (
                        <Tooltip title="Enter result">
                          <IconButton
                            size="small"
                            onClick={() => {
                              setResultTarget(order)
                              setResultSummary(order.result_summary ?? '')
                            }}
                          >
                            <BiotechIcon fontSize="small" />
                          </IconButton>
                        </Tooltip>
                      ) : null}
                      {canBill &&
                      ['ordered', 'sample_collected', 'in_progress', 'result_ready'].includes(order.status) &&
                      !order.invoice_id ? (
                        <Tooltip title="Create draft invoice">
                          <IconButton
                            size="small"
                            onClick={() => billMutation.mutate(order.id)}
                            disabled={billMutation.isPending}
                          >
                            <ReceiptLongIcon fontSize="small" />
                          </IconButton>
                        </Tooltip>
                      ) : null}
                      {order.invoice_id ? (
                        <Button size="small" component={RouterLink} to={`/billing?invoice_id=${order.invoice_id}`}>
                          Invoice
                        </Button>
                      ) : null}
                      {canOrder && ['ordered', 'sample_collected', 'in_progress'].includes(order.status) ? (
                        <Tooltip title="Cancel order">
                          <IconButton
                            size="small"
                            onClick={() => cancelMutation.mutate(order.id)}
                            disabled={cancelMutation.isPending}
                          >
                            <CancelIcon fontSize="small" />
                          </IconButton>
                        </Tooltip>
                      ) : null}
                    </Stack>
                  </TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </Paper>

      <Drawer
        anchor="right"
        open={drawerOpen}
        onClose={() => setDrawerOpen(false)}
        slotProps={{ paper: { sx: { width: { xs: '100%', sm: 440 }, p: 3 } } }}
      >
        <Typography component="h2" variant="h6" sx={{ mb: 2 }}>
          New diagnostic order
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
            label="Category"
            value={category}
            onChange={(event) => {
              setCategory(event.target.value as DiagnosticCategory)
              setSelectedServiceIds([])
            }}
          >
            {CATEGORY_OPTIONS.map((option) => (
              <MenuItem key={option.value} value={option.value}>
                {option.label}
              </MenuItem>
            ))}
          </TextField>
          <TextField
            select
            label="Priority"
            value={priority}
            onChange={(event) => setPriority(event.target.value as 'routine' | 'urgent')}
          >
            <MenuItem value="routine">Routine</MenuItem>
            <MenuItem value="urgent">Urgent</MenuItem>
          </TextField>
          <Box>
            <Typography variant="subtitle2" sx={{ mb: 1 }}>
              Services
            </Typography>
            {catalogQuery.isLoading ? <Typography color="text.secondary">Loading catalog…</Typography> : null}
            {catalog.map((service) => (
              <FormControlLabel
                key={service.id}
                control={
                  <Checkbox
                    checked={selectedServiceIds.includes(service.id)}
                    onChange={(event) => {
                      setSelectedServiceIds((current) =>
                        event.target.checked
                          ? [...current, service.id]
                          : current.filter((id) => id !== service.id),
                      )
                    }}
                  />
                }
                label={`${service.name} (${service.code}) — ₹${service.base_rate}`}
              />
            ))}
          </Box>
          <TextField
            label="Clinical notes"
            multiline
            minRows={3}
            value={clinicalNotes}
            onChange={(event) => setClinicalNotes(event.target.value)}
          />
          {createMutation.isError ? (
            <Alert severity="error">{getApiErrorMessage(createMutation.error, 'Unable to create order.')}</Alert>
          ) : null}
          <Stack direction="row" spacing={1} sx={{ justifyContent: 'flex-end' }}>
            <Button onClick={() => setDrawerOpen(false)}>Cancel</Button>
            <Button
              variant="contained"
              disabled={!patientId || selectedServiceIds.length === 0 || createMutation.isPending}
              onClick={() => {
                if (!patientId) return
                createMutation.mutate({
                  patient_id: patientId,
                  category,
                  priority,
                  clinical_notes: clinicalNotes || undefined,
                  items: selectedServiceIds.map((service_id) => ({ service_id, quantity: 1 })),
                })
              }}
            >
              Create order
            </Button>
          </Stack>
        </Stack>
      </Drawer>

      <Dialog open={Boolean(resultTarget)} onClose={() => setResultTarget(null)} fullWidth maxWidth="sm">
        <DialogTitle>Enter result — {resultTarget?.order_number}</DialogTitle>
        <DialogContent>
          <TextField
            autoFocus
            margin="dense"
            label="Result summary"
            fullWidth
            multiline
            minRows={4}
            value={resultSummary}
            onChange={(event) => setResultSummary(event.target.value)}
          />
          {resultMutation.isError ? (
            <Alert severity="error" sx={{ mt: 1 }}>
              {getApiErrorMessage(resultMutation.error, 'Unable to save result.')}
            </Alert>
          ) : null}
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setResultTarget(null)}>Cancel</Button>
          <Button
            variant="contained"
            disabled={!resultSummary.trim() || resultMutation.isPending}
            onClick={() => {
              if (!resultTarget) return
              resultMutation.mutate({ id: resultTarget.id, summary: resultSummary.trim() })
            }}
          >
            Save result
          </Button>
        </DialogActions>
      </Dialog>
    </Stack>
  )
}
