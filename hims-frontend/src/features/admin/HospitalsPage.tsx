import EditIcon from '@mui/icons-material/Edit'
import {
  Alert,
  Box,
  Button,
  Chip,
  Drawer,
  MenuItem,
  Paper,
  Stack,
  TextField,
  Typography,
} from '@mui/material'
import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Controller, useForm } from 'react-hook-form'
import { z } from 'zod'
import { type HospitalPayload, getHospitals, updateHospital } from '../../api/admin'
import { getApiErrorMessage } from '../../api/errors'

const schema = z.object({
  name: z.string().min(1, 'Name is required'),
  legal_name: z.string().nullable().optional(),
  registration_number: z.string().nullable().optional(),
  gstin: z.string().nullable().optional(),
  phone: z.string().nullable().optional(),
  status: z.enum(['active', 'inactive']),
})

export function HospitalsPage() {
  const queryClient = useQueryClient()
  const [open, setOpen] = useState(false)
  const hospitalsQuery = useQuery({ queryKey: ['admin-hospitals'], queryFn: getHospitals })
  const hospital = hospitalsQuery.data?.data[0] ?? null
  const form = useForm<HospitalPayload>({
    resolver: zodResolver(schema),
    defaultValues: defaultValues(),
  })

  const saveHospital = useMutation({
    mutationFn: (values: HospitalPayload) => {
      if (!hospital) {
        throw new Error('Hospital not loaded')
      }

      return updateHospital(hospital.id, values)
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['admin-hospitals'] })
      setOpen(false)
    },
  })

  const startEdit = () => {
    if (!hospital) {
      return
    }

    form.reset({
      name: hospital.name,
      legal_name: hospital.legal_name ?? '',
      registration_number: hospital.registration_number ?? '',
      gstin: hospital.gstin ?? '',
      phone: hospital.phone ?? '',
      status: hospital.status,
    })
    setOpen(true)
  }

  return (
    <Stack spacing={3}>
      <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2} sx={{ justifyContent: 'space-between' }}>
        <Box>
          <Typography component="h1" sx={{ fontWeight: 700 }} variant="h4">
            Hospital
          </Typography>
          <Typography color="text.secondary">Registration and compliance details for your hospital.</Typography>
        </Box>
        <Button disabled={!hospital} onClick={startEdit} startIcon={<EditIcon />} variant="contained">
          Edit Hospital
        </Button>
      </Stack>

      {hospitalsQuery.isError ? <Alert severity="error">Unable to load hospital details.</Alert> : null}

      {hospital ? (
        <Paper sx={{ p: 3 }} variant="outlined">
          <Stack spacing={2}>
            <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2} sx={{ justifyContent: 'space-between' }}>
              <Box>
                <Typography sx={{ fontWeight: 700 }} variant="h6">
                  {hospital.name}
                </Typography>
                <Typography color="text.secondary" variant="body2">
                  Code: {hospital.code}
                </Typography>
              </Box>
              <Chip color={hospital.status === 'active' ? 'success' : 'default'} label={hospital.status} />
            </Stack>
            <Box
              sx={{
                display: 'grid',
                gap: 2,
                gridTemplateColumns: { xs: '1fr', sm: 'repeat(2, minmax(0, 1fr))' },
              }}
            >
              <InfoItem label="Legal Name" value={hospital.legal_name ?? '-'} />
              <InfoItem label="Registration Number" value={hospital.registration_number ?? '-'} />
              <InfoItem label="GSTIN" value={hospital.gstin ?? '-'} />
              <InfoItem label="Phone" value={hospital.phone ?? '-'} />
            </Box>
          </Stack>
        </Paper>
      ) : null}

      <Drawer anchor="right" onClose={() => setOpen(false)} open={open}>
        <Stack
          component="form"
          onSubmit={form.handleSubmit((values) => saveHospital.mutate(values))}
          spacing={2}
          sx={{ p: 3, width: { xs: 320, sm: 420 } }}
        >
          <Typography sx={{ fontWeight: 700 }} variant="h6">
            Edit Hospital
          </Typography>
          {saveHospital.isError ? (
            <Alert severity="error">{getApiErrorMessage(saveHospital.error, 'Unable to save hospital.')}</Alert>
          ) : null}
          <HospitalField control={form.control} label="Name" name="name" />
          <HospitalField control={form.control} label="Legal Name" name="legal_name" />
          <HospitalField control={form.control} label="Registration Number" name="registration_number" />
          <HospitalField control={form.control} label="GSTIN" name="gstin" />
          <HospitalField control={form.control} label="Phone" name="phone" />
          <Controller
            control={form.control}
            name="status"
            render={({ field }) => (
              <TextField {...field} label="Status" select>
                <MenuItem value="active">Active</MenuItem>
                <MenuItem value="inactive">Inactive</MenuItem>
              </TextField>
            )}
          />
          <Button loading={saveHospital.isPending} type="submit" variant="contained">
            Save Hospital
          </Button>
        </Stack>
      </Drawer>
    </Stack>
  )
}

function InfoItem({ label, value }: { label: string; value: string }) {
  return (
    <Box>
      <Typography color="text.secondary" variant="caption">
        {label}
      </Typography>
      <Typography sx={{ fontWeight: 600 }}>{value}</Typography>
    </Box>
  )
}

function HospitalField({
  control,
  label,
  name,
}: {
  control: any
  label: string
  name: keyof HospitalPayload
}) {
  return (
    <Controller
      control={control}
      name={name}
      render={({ field, fieldState }) => (
        <TextField
          {...field}
          error={Boolean(fieldState.error)}
          helperText={fieldState.error?.message}
          label={label}
          value={field.value ?? ''}
        />
      )}
    />
  )
}

function defaultValues(): HospitalPayload {
  return {
    name: '',
    legal_name: '',
    registration_number: '',
    gstin: '',
    phone: '',
    status: 'active',
  }
}
