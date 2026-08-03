import AddIcon from '@mui/icons-material/Add'
import DoneIcon from '@mui/icons-material/Done'
import DeleteIcon from '@mui/icons-material/Delete'
import PaymentIcon from '@mui/icons-material/Payment'
import PrintIcon from '@mui/icons-material/Print'
import RestartAltIcon from '@mui/icons-material/RestartAlt'
import {
  Alert,
  Autocomplete,
  Box,
  Button,
  Chip,
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
  Typography,
} from '@mui/material'
import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useMemo, useState } from 'react'
import { Controller, useFieldArray, useForm } from 'react-hook-form'
import { useSearchParams } from 'react-router-dom'
import { z } from 'zod'
import {
  type Invoice,
  type InvoicePayload,
  type Payment,
  createInvoice,
  createPayment,
  finalizeInvoice,
  getInvoiceReceipt,
  getInvoices,
  getServices,
} from '../../api/billing'
import { getApiErrorMessage } from '../../api/errors'
import { getPatients, type Patient } from '../../api/patients'
import { formatCurrency, formatDate } from '../../utils/format'

const invoiceItemSchema = z.object({
  service_id: z.number().nullable().optional(),
  description: z.string().min(1, 'Description is required'),
  quantity: z.number().min(0.01, 'Quantity is required'),
  unit_rate: z.number().min(0, 'Rate is required'),
  discount_amount: z.number().min(0).optional(),
})

const invoiceSchema = z.object({
  patient_id: z.number().min(1, 'Select a patient'),
  invoice_type: z.string().min(1),
  payer_type: z.string().min(1),
  items: z.array(invoiceItemSchema).min(1, 'Add at least one line item'),
})

type InvoiceFormValues = z.infer<typeof invoiceSchema>

const paymentSchema = z.object({
  payment_mode: z.enum(['cash', 'upi', 'card', 'bank', 'cheque', 'mixed']),
  amount: z.number().min(0.01, 'Amount is required'),
  reference_number: z.string().nullable().optional(),
  bank_name: z.string().nullable().optional(),
})

type PaymentFormValues = z.infer<typeof paymentSchema>

function isPayable(invoice: Invoice): boolean {
  return !['draft', 'voided', 'cancelled'].includes(invoice.status) && Number(invoice.balance_total) > 0
}

function isFinalizable(invoice: Invoice): boolean {
  return invoice.status === 'draft'
}

export function BillingPage() {
  const queryClient = useQueryClient()
  const [searchParams] = useSearchParams()
  const patientIdParam = searchParams.get('patient_id')
  const [invoiceDrawerOpen, setInvoiceDrawerOpen] = useState(false)
  const [paymentDrawerOpen, setPaymentDrawerOpen] = useState(false)
  const [paymentTarget, setPaymentTarget] = useState<Invoice | null>(null)
  const [receiptPayment, setReceiptPayment] = useState<{ invoice: Invoice; payment: Payment } | null>(null)
  const [serviceCategory, setServiceCategory] = useState<'all' | string>('all')

  const invoicesQuery = useQuery({
    queryKey: ['billing-invoices', patientIdParam],
    queryFn: () => getInvoices({ patient_id: patientIdParam ? Number(patientIdParam) : undefined }),
  })
  const servicesQuery = useQuery({
    queryKey: ['billing-services', serviceCategory],
    queryFn: () => getServices(serviceCategory === 'all' ? {} : { category: serviceCategory }),
    retry: false,
  })
  const patientsQuery = useQuery({ queryKey: ['patients'], queryFn: getPatients })

  const invoiceForm = useForm<InvoiceFormValues>({
    resolver: zodResolver(invoiceSchema),
    defaultValues: defaultInvoiceValues(patientIdParam ? Number(patientIdParam) : 0),
  })
  const { fields, append, remove } = useFieldArray({ control: invoiceForm.control, name: 'items' })
  const watchedItems = invoiceForm.watch('items')
  const estimatedTotal = useMemo(
    () =>
      watchedItems.reduce((sum, item) => {
        const gross = (item.quantity || 0) * (item.unit_rate || 0) - (item.discount_amount || 0)
        return sum + Math.max(gross, 0)
      }, 0),
    [watchedItems],
  )

  const paymentForm = useForm<PaymentFormValues>({
    resolver: zodResolver(paymentSchema),
    defaultValues: { payment_mode: 'cash', amount: 0, reference_number: '', bank_name: '' },
  })

  const createInvoiceMutation = useMutation({
    mutationFn: (values: InvoiceFormValues) => createInvoice(values as InvoicePayload),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['billing-invoices'] })
      setInvoiceDrawerOpen(false)
      invoiceForm.reset(defaultInvoiceValues(0))
    },
  })

  const finalizeMutation = useMutation({
    mutationFn: (invoiceId: number) => finalizeInvoice(invoiceId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['billing-invoices'] })
    },
  })

  const createPaymentMutation = useMutation({
    mutationFn: (values: PaymentFormValues) => {
      if (!paymentTarget) {
        throw new Error('No invoice selected for payment.')
      }

      return createPayment(paymentTarget.id, values)
    },
    onSuccess: async (response) => {
      await queryClient.invalidateQueries({ queryKey: ['billing-invoices'] })
      setPaymentDrawerOpen(false)
      if (paymentTarget) {
        setReceiptPayment({ invoice: paymentTarget, payment: response.data })
        void printInvoiceReceipt(paymentTarget.id)
      }
      setPaymentTarget(null)
    },
  })

  const printReceiptMutation = useMutation({
    mutationFn: (invoiceId: number) => printInvoiceReceipt(invoiceId),
  })

  const startCreateInvoice = () => {
    invoiceForm.reset(defaultInvoiceValues(patientIdParam ? Number(patientIdParam) : 0))
    setInvoiceDrawerOpen(true)
  }

  const startPayment = (invoice: Invoice) => {
    setPaymentTarget(invoice)
    paymentForm.reset({
      payment_mode: 'cash',
      amount: Number(invoice.balance_total) || Number(invoice.grand_total) || 0,
      reference_number: '',
      bank_name: '',
    })
    setPaymentDrawerOpen(true)
  }

  const invoices = invoicesQuery.data?.data ?? []
  const patients = patientsQuery.data?.data ?? []
  const services = servicesQuery.data?.data ?? []

  return (
    <Stack spacing={3}>
      <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2} sx={{ justifyContent: 'space-between' }}>
        <Box>
          <Typography component="h1" sx={{ fontWeight: 700 }} variant="h4">
            Billing
          </Typography>
          <Typography color="text.secondary">Invoices, payments and receipts.</Typography>
        </Box>
        <Button onClick={startCreateInvoice} startIcon={<AddIcon />} variant="contained">
          New Invoice
        </Button>
      </Stack>

      {invoicesQuery.isError ? (
        <Alert severity="error">{getApiErrorMessage(invoicesQuery.error, 'Unable to load invoices.')}</Alert>
      ) : null}
      {finalizeMutation.isError ? (
        <Alert severity="error">{getApiErrorMessage(finalizeMutation.error, 'Unable to finalize invoice.')}</Alert>
      ) : null}
      {printReceiptMutation.isError ? (
        <Alert severity="error">{getApiErrorMessage(printReceiptMutation.error, 'Unable to load receipt.')}</Alert>
      ) : null}

      {patientIdParam ? <Alert severity="info">Showing invoices for patient #{patientIdParam}.</Alert> : null}

      <Paper variant="outlined">
        <Table>
          <TableHead>
            <TableRow>
              <TableCell>Invoice #</TableCell>
              <TableCell>Patient</TableCell>
              <TableCell>Billed</TableCell>
              <TableCell align="right">Grand Total</TableCell>
              <TableCell align="right">Paid</TableCell>
              <TableCell align="right">Balance</TableCell>
              <TableCell>Status</TableCell>
              <TableCell align="right">Actions</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {invoices.map((invoice) => (
              <TableRow key={invoice.id}>
                <TableCell>{invoice.invoice_number}</TableCell>
                <TableCell>{invoice.patient?.name ?? '-'}</TableCell>
                <TableCell>{formatDate(invoice.billed_at)}</TableCell>
                <TableCell align="right">{formatCurrency(invoice.grand_total)}</TableCell>
                <TableCell align="right">{formatCurrency(invoice.paid_total)}</TableCell>
                <TableCell align="right">{formatCurrency(invoice.balance_total)}</TableCell>
                <TableCell>
                  <Chip label={invoice.status} size="small" />
                </TableCell>
                <TableCell align="right">
                  {isFinalizable(invoice) ? (
                    <IconButton
                      aria-label={`Finalize ${invoice.invoice_number}`}
                      loading={finalizeMutation.isPending}
                      onClick={() => finalizeMutation.mutate(invoice.id)}
                    >
                      <DoneIcon />
                    </IconButton>
                  ) : null}
                  <IconButton
                    aria-label={`Record payment for ${invoice.invoice_number}`}
                    disabled={!isPayable(invoice)}
                    onClick={() => startPayment(invoice)}
                  >
                    <PaymentIcon />
                  </IconButton>
                  <IconButton
                    aria-label={`Print receipt for ${invoice.invoice_number}`}
                    disabled={invoice.status === 'draft'}
                    onClick={() => printReceiptMutation.mutate(invoice.id)}
                  >
                    <PrintIcon />
                  </IconButton>
                </TableCell>
              </TableRow>
            ))}
            {invoices.length === 0 && !invoicesQuery.isError ? (
              <TableRow>
                <TableCell colSpan={8}>
                  <Typography color="text.secondary" sx={{ py: 2, textAlign: 'center' }}>
                    No invoices recorded yet.
                  </Typography>
                </TableCell>
              </TableRow>
            ) : null}
          </TableBody>
        </Table>
      </Paper>

      <Drawer anchor="right" onClose={() => setInvoiceDrawerOpen(false)} open={invoiceDrawerOpen}>
        <Stack
          component="form"
          onSubmit={invoiceForm.handleSubmit((values) => createInvoiceMutation.mutate(values))}
          spacing={2}
          sx={{ p: 3, width: { xs: 360, sm: 620 } }}
        >
          <Typography sx={{ fontWeight: 700 }} variant="h6">
            New Invoice
          </Typography>
          {createInvoiceMutation.isError ? (
            <Alert severity="error">
              {getApiErrorMessage(createInvoiceMutation.error, 'Unable to create invoice.')}
            </Alert>
          ) : null}

          <Controller
            control={invoiceForm.control}
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

          <Stack direction="row" spacing={2}>
            <Controller
              control={invoiceForm.control}
              name="invoice_type"
              render={({ field }) => (
                <TextField {...field} fullWidth label="Invoice Type" select>
                  <MenuItem value="opd">OPD</MenuItem>
                  <MenuItem value="ipd">IPD</MenuItem>
                  <MenuItem value="pathology">Pathology</MenuItem>
                  <MenuItem value="radiology">Radiology</MenuItem>
                  <MenuItem value="procedure">Procedure</MenuItem>
                  <MenuItem value="consultant_fee">Consultant Fee</MenuItem>
                  <MenuItem value="pharmacy">Pharmacy</MenuItem>
                  <MenuItem value="diagnostic">Diagnostic</MenuItem>
                </TextField>
              )}
            />
            <Controller
              control={invoiceForm.control}
              name="payer_type"
              render={({ field }) => (
                <TextField {...field} fullWidth label="Payer Type" select>
                  <MenuItem value="self">Self Pay</MenuItem>
                  <MenuItem value="insurance">Insurance</MenuItem>
                  <MenuItem value="tpa">TPA</MenuItem>
                  <MenuItem value="corporate">Corporate</MenuItem>
                </TextField>
              )}
            />
          </Stack>

          <Stack direction="row" spacing={2} sx={{ alignItems: 'center', justifyContent: 'space-between' }}>
            <Typography color="text.secondary" sx={{ fontWeight: 700, textTransform: 'uppercase' }} variant="caption">
              Line Items
            </Typography>
            <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
              <TextField
                label="Service category"
                onChange={(event) => setServiceCategory(event.target.value)}
                select
                size="small"
                sx={{ minWidth: 180 }}
                value={serviceCategory}
              >
                <MenuItem value="all">All categories</MenuItem>
                <MenuItem value="opd">OPD</MenuItem>
                <MenuItem value="ipd">IPD</MenuItem>
                <MenuItem value="pathology">Pathology</MenuItem>
                <MenuItem value="radiology">Radiology</MenuItem>
                <MenuItem value="procedure">Procedure</MenuItem>
                <MenuItem value="consultant_fee">Consultant Fee</MenuItem>
              </TextField>
              <Button
                onClick={() => append({ service_id: null, description: '', quantity: 1, unit_rate: 0, discount_amount: 0 })}
                size="small"
                startIcon={<AddIcon />}
                variant="outlined"
              >
                Add Item
              </Button>
            </Stack>
          </Stack>
          {invoiceForm.formState.errors.items?.message ? (
            <Alert severity="error">{invoiceForm.formState.errors.items.message}</Alert>
          ) : null}
          <Table size="small">
            <TableHead>
              <TableRow>
                <TableCell>Service</TableCell>
                <TableCell>Description</TableCell>
                <TableCell align="right">Qty</TableCell>
                <TableCell align="right">Rate (₹)</TableCell>
                <TableCell align="right">Discount (₹)</TableCell>
                <TableCell align="right" />
              </TableRow>
            </TableHead>
            <TableBody>
              {fields.map((field, index) => (
                <TableRow key={field.id}>
                  <TableCell sx={{ minWidth: 160 }}>
                    <TextField
                      onChange={(event) => {
                        const serviceId = event.target.value ? Number(event.target.value) : null
                        invoiceForm.setValue(`items.${index}.service_id`, serviceId)
                        const service = services.find((item) => item.id === serviceId)
                        if (service) {
                          invoiceForm.setValue(`items.${index}.description`, service.name)
                          invoiceForm.setValue(`items.${index}.unit_rate`, Number(service.base_rate))
                        }
                      }}
                      select
                      size="small"
                      value={invoiceForm.watch(`items.${index}.service_id`) ?? ''}
                    >
                      <MenuItem value="">Custom</MenuItem>
                      {services.map((service) => (
                        <MenuItem key={service.id} value={service.id}>
                          {service.name} ({service.category})
                        </MenuItem>
                      ))}
                    </TextField>
                  </TableCell>
                  <TableCell sx={{ minWidth: 160 }}>
                    <Controller
                      control={invoiceForm.control}
                      name={`items.${index}.description`}
                      render={({ field: descField, fieldState }) => (
                        <TextField {...descField} error={Boolean(fieldState.error)} size="small" variant="standard" />
                      )}
                    />
                  </TableCell>
                  <TableCell align="right" sx={{ minWidth: 80 }}>
                    <Controller
                      control={invoiceForm.control}
                      name={`items.${index}.quantity`}
                      render={({ field: qtyField }) => (
                        <TextField
                          onChange={(event) => qtyField.onChange(Number(event.target.value) || 0)}
                          size="small"
                          type="number"
                          value={qtyField.value}
                          variant="standard"
                        />
                      )}
                    />
                  </TableCell>
                  <TableCell align="right" sx={{ minWidth: 100 }}>
                    <Controller
                      control={invoiceForm.control}
                      name={`items.${index}.unit_rate`}
                      render={({ field: rateField }) => (
                        <TextField
                          onChange={(event) => rateField.onChange(Number(event.target.value) || 0)}
                          size="small"
                          type="number"
                          value={rateField.value}
                          variant="standard"
                        />
                      )}
                    />
                  </TableCell>
                  <TableCell align="right" sx={{ minWidth: 100 }}>
                    <Controller
                      control={invoiceForm.control}
                      name={`items.${index}.discount_amount`}
                      render={({ field: discField }) => (
                        <TextField
                          onChange={(event) => discField.onChange(Number(event.target.value) || 0)}
                          size="small"
                          type="number"
                          value={discField.value ?? 0}
                          variant="standard"
                        />
                      )}
                    />
                  </TableCell>
                  <TableCell align="right">
                    <IconButton aria-label="Remove item" onClick={() => remove(index)} size="small">
                      <DeleteIcon fontSize="small" />
                    </IconButton>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>

          <Stack sx={{ alignItems: 'flex-end' }}>
            <Typography sx={{ fontWeight: 700 }} variant="body1">
              Estimated Total: {formatCurrency(estimatedTotal)}
            </Typography>
            <Typography color="text.secondary" variant="caption">
              Final totals with GST are computed by the server.
            </Typography>
          </Stack>

          <Button loading={createInvoiceMutation.isPending} type="submit" variant="contained">
            Create Invoice
          </Button>
        </Stack>
      </Drawer>

      <Drawer anchor="right" onClose={() => setPaymentDrawerOpen(false)} open={paymentDrawerOpen}>
        <Stack
          component="form"
          onSubmit={paymentForm.handleSubmit((values) => createPaymentMutation.mutate(values))}
          spacing={2}
          sx={{ p: 3, width: { xs: 320, sm: 420 } }}
        >
          <Typography sx={{ fontWeight: 700 }} variant="h6">
            Record Payment
          </Typography>
          {createPaymentMutation.isError ? (
            <Alert severity="error">
              {getApiErrorMessage(createPaymentMutation.error, 'Unable to record payment.')}
            </Alert>
          ) : null}
          {paymentTarget ? (
            <Alert severity="info">
              Invoice {paymentTarget.invoice_number} · Balance {formatCurrency(paymentTarget.balance_total)}
            </Alert>
          ) : null}
          <Controller
            control={paymentForm.control}
            name="payment_mode"
            render={({ field, fieldState }) => (
              <TextField {...field} error={Boolean(fieldState.error)} helperText={fieldState.error?.message} label="Payment Mode" select>
                <MenuItem value="cash">Cash</MenuItem>
                <MenuItem value="card">Card</MenuItem>
                <MenuItem value="upi">UPI</MenuItem>
                <MenuItem value="bank">Bank Transfer</MenuItem>
                <MenuItem value="cheque">Cheque</MenuItem>
                <MenuItem value="mixed">Mixed</MenuItem>
              </TextField>
            )}
          />
          <Controller
            control={paymentForm.control}
            name="amount"
            render={({ field, fieldState }) => (
              <TextField
                error={Boolean(fieldState.error)}
                helperText={fieldState.error?.message}
                label="Amount (₹)"
                onChange={(event) => field.onChange(Number(event.target.value) || 0)}
                type="number"
                value={field.value}
              />
            )}
          />
          <Controller
            control={paymentForm.control}
            name="reference_number"
            render={({ field }) => <TextField {...field} label="Reference Number" value={field.value ?? ''} />}
          />
          <Controller
            control={paymentForm.control}
            name="bank_name"
            render={({ field }) => <TextField {...field} label="Bank Name" value={field.value ?? ''} />}
          />
          <Button loading={createPaymentMutation.isPending} type="submit" variant="contained">
            Record Payment
          </Button>
        </Stack>
      </Drawer>

      {receiptPayment ? (
        <Alert
          action={
            <Button color="inherit" onClick={() => setReceiptPayment(null)} size="small">
              Dismiss
            </Button>
          }
          severity="success"
          sx={{ position: 'fixed', bottom: 16, right: 16, boxShadow: 3, zIndex: 1300 }}
        >
          Payment {receiptPayment.payment.receipt_number} recorded. Receipt printed in a new window.
        </Alert>
      ) : null}

      <Box sx={{ display: 'flex', justifyContent: 'flex-end' }}>
        <Button onClick={() => invoicesQuery.refetch()} startIcon={<RestartAltIcon />} variant="text">
          Refresh
        </Button>
      </Box>
    </Stack>
  )
}

async function printInvoiceReceipt(invoiceId: number) {
  const response = await getInvoiceReceipt(invoiceId)
  const receipt = response.data
  const printWindow = window.open('', '_blank', 'width=420,height=640')
  if (!printWindow) {
    return response
  }

  const itemsHtml = (receipt.items ?? [])
    .map(
      (item) =>
        `<tr><td>${item.description}</td><td style="text-align:right">${item.quantity}</td><td style="text-align:right">${formatCurrency(item.unit_rate)}</td><td style="text-align:right">${formatCurrency(item.net_amount)}</td></tr>`,
    )
    .join('')

  const paymentsHtml = (receipt.payments ?? [])
    .map(
      (payment) =>
        `<tr><td>${payment.receipt_number}</td><td>${payment.payment_mode}</td><td style="text-align:right">${formatCurrency(payment.amount)}</td></tr>`,
    )
    .join('')

  printWindow.document.write(`
    <html>
      <head>
        <title>Receipt ${receipt.invoice.invoice_number}</title>
        <style>
          body { font-family: Arial, sans-serif; padding: 16px; color: #111; }
          h2 { margin-bottom: 0; }
          table { width: 100%; border-collapse: collapse; margin-top: 12px; }
          td, th { padding: 4px 0; font-size: 13px; }
          .totals td { border-top: 1px solid #ccc; font-weight: bold; }
        </style>
      </head>
      <body>
        <h2>${receipt.hospital.name ?? 'Hospital'}</h2>
        <p>GSTIN: ${receipt.hospital.gstin ?? '-'}<br/>Branch: ${receipt.branch.name ?? '-'}</p>
        <h3>Invoice ${receipt.invoice.invoice_number}</h3>
        <p>Patient: ${receipt.patient?.name ?? '-'} (${receipt.patient?.uhid ?? '-'})<br/>Status: ${receipt.invoice.status}</p>
        <table>
          <thead><tr><th style="text-align:left">Description</th><th style="text-align:right">Qty</th><th style="text-align:right">Rate</th><th style="text-align:right">Net</th></tr></thead>
          <tbody>${itemsHtml}</tbody>
          <tfoot>
            <tr class="totals"><td colspan="3">Taxable</td><td style="text-align:right">${formatCurrency(receipt.gst_summary.taxable_total)}</td></tr>
            <tr><td colspan="3">CGST</td><td style="text-align:right">${formatCurrency(receipt.gst_summary.cgst_total)}</td></tr>
            <tr><td colspan="3">SGST</td><td style="text-align:right">${formatCurrency(receipt.gst_summary.sgst_total)}</td></tr>
            <tr class="totals"><td colspan="3">Grand Total</td><td style="text-align:right">${formatCurrency(receipt.gst_summary.grand_total)}</td></tr>
            <tr><td colspan="3">Paid</td><td style="text-align:right">${formatCurrency(receipt.invoice.paid_total)}</td></tr>
            <tr><td colspan="3">Balance</td><td style="text-align:right">${formatCurrency(receipt.invoice.balance_total)}</td></tr>
          </tfoot>
        </table>
        <h4>Payments</h4>
        <table>
          <thead><tr><th style="text-align:left">Receipt</th><th style="text-align:left">Mode</th><th style="text-align:right">Amount</th></tr></thead>
          <tbody>${paymentsHtml || '<tr><td colspan="3">No payments posted</td></tr>'}</tbody>
        </table>
      </body>
    </html>
  `)
  printWindow.document.close()
  printWindow.focus()
  printWindow.print()
  return response
}

function defaultInvoiceValues(patientId: number): InvoiceFormValues {
  return {
    patient_id: patientId,
    invoice_type: 'opd',
    payer_type: 'self',
    items: [{ service_id: null, description: '', quantity: 1, unit_rate: 0, discount_amount: 0 }],
  }
}
