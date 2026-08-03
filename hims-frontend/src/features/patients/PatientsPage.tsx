import AddIcon from '@mui/icons-material/Add'
import EditIcon from '@mui/icons-material/Edit'
import RestartAltIcon from '@mui/icons-material/RestartAlt'
import SearchIcon from '@mui/icons-material/Search'
import VisibilityIcon from '@mui/icons-material/Visibility'
import {
  Alert,
  Box,
  Button,
  Checkbox,
  Chip,
  Drawer,
  FormControlLabel,
  IconButton,
  InputAdornment,
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
import { useEffect, useMemo, useState } from 'react'
import { Controller, useForm, useWatch } from 'react-hook-form'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { z } from 'zod'
import { type Branch, getBranches } from '../../api/admin'
import { getApiErrorMessage } from '../../api/errors'
import {
  type Patient,
  type PatientPayload,
  createPatient,
  getPatientDuplicates,
  getPatients,
  updatePatient,
} from '../../api/patients'

type BranchFilter = 'all' | `${number}`
type GenderFilter = 'all' | Patient['gender']
type PatientStatusFilter = 'all' | Patient['status']
type PatientCategoryFilter = 'all' | Patient['patient_category']

const nullableText = z.string().nullable().optional()

const schema = z
  .object({
    branch_id: z.number().nullable().optional(),
    salutation: z.enum(['mr', 'mrs', 'ms', 'miss', 'master', 'baby', 'dr', 'prof']).nullable().optional(),
    patient_category: z.enum(['general', 'emergency', 'vip', 'staff', 'camp', 'unknown']),
    registration_source: z.enum(['walk_in', 'referral', 'online', 'camp', 'transfer']),
    referred_by: nullableText,
    first_name: z.string().min(1, 'First name is required'),
    middle_name: nullableText,
    last_name: nullableText,
    gender: z.enum(['male', 'female', 'other', 'unknown']),
    blood_group: z
      .enum([
        'a_positive',
        'a_negative',
        'b_positive',
        'b_negative',
        'ab_positive',
        'ab_negative',
        'o_positive',
        'o_negative',
        'unknown',
      ])
      .nullable()
      .optional(),
    marital_status: z.enum(['single', 'married', 'widowed', 'divorced', 'separated', 'unknown']).nullable().optional(),
    occupation: nullableText,
    nationality: nullableText,
    preferred_language: nullableText,
    date_of_birth: nullableText,
    age_years: z.number().int().min(0).max(130).nullable().optional(),
    age_months: z.number().int().min(0).max(11).nullable().optional(),
    age_days: z.number().int().min(0).max(30).nullable().optional(),
    mobile: nullableText,
    alternate_mobile: nullableText,
    email: z.string().email('Enter a valid email').nullable().or(z.literal('')).optional(),
    address: nullableText,
    city: nullableText,
    district: nullableText,
    state: nullableText,
    pincode: nullableText,
    country: nullableText,
    identity_type: z.enum(['aadhaar', 'passport', 'driving_license', 'voter_id', 'other']).nullable().optional(),
    identity_number: nullableText,
    abha_id: nullableText,
    guardian_name: nullableText,
    guardian_relation: nullableText,
    guardian_mobile: nullableText,
    emergency_contact_name: nullableText,
    emergency_contact_mobile: nullableText,
    emergency_contact_relation: nullableText,
    consent_sms: z.boolean(),
    consent_email: z.boolean(),
    consent_whatsapp: z.boolean(),
    remarks: nullableText,
    status: z.enum(['active', 'inactive', 'deceased']),
  })
  .refine((values) => values.date_of_birth || values.age_years !== null, {
    message: 'Enter date of birth or age',
    path: ['age_years'],
  })
  .refine((values) => !values.identity_type || Boolean(values.identity_number), {
    message: 'Identity number is required',
    path: ['identity_number'],
  })

type PatientFormValues = z.infer<typeof schema>

export function PatientsPage() {
  const queryClient = useQueryClient()
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const [editing, setEditing] = useState<Patient | null>(null)
  const [open, setOpen] = useState(false)
  const [search, setSearch] = useState(searchParams.get('q') ?? '')
  const [branchFilter, setBranchFilter] = useState<BranchFilter>('all')
  const [genderFilter, setGenderFilter] = useState<GenderFilter>('all')
  const [categoryFilter, setCategoryFilter] = useState<PatientCategoryFilter>('all')
  const [statusFilter, setStatusFilter] = useState<PatientStatusFilter>('all')
  const patientsQuery = useQuery({ queryKey: ['patients'], queryFn: getPatients })
  const branchesQuery = useQuery({ queryKey: ['admin-branches'], queryFn: getBranches })

  useEffect(() => {
    const query = searchParams.get('q')
    if (query !== null) {
      setSearch(query)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [searchParams])
  const form = useForm<PatientFormValues>({
    resolver: zodResolver(schema),
    defaultValues: defaultPatientValues(),
  })
  const watchedMobile = useWatch({ control: form.control, name: 'mobile' })
  const watchedFirstName = useWatch({ control: form.control, name: 'first_name' })
  const watchedLastName = useWatch({ control: form.control, name: 'last_name' })
  const watchedIdentityType = useWatch({ control: form.control, name: 'identity_type' })
  const watchedIdentityNumber = useWatch({ control: form.control, name: 'identity_number' })
  const watchedAbhaId = useWatch({ control: form.control, name: 'abha_id' })
  const branchName = (branchId: number | null) =>
    branchesQuery.data?.data.find((branch) => branch.id === branchId)?.name ?? '-'
  const patients = useMemo(() => {
    const records = patientsQuery.data?.data ?? []
    const term = search.trim().toLowerCase()

    return records.filter((patient) => {
      const matchesStatus = statusFilter === 'all' || patient.status === statusFilter
      const matchesGender = genderFilter === 'all' || patient.gender === genderFilter
      const matchesCategory = categoryFilter === 'all' || patient.patient_category === categoryFilter
      const matchesBranch = branchFilter === 'all' || patient.branch_id === Number(branchFilter)
      const matchesSearch =
        !term ||
        [
          patient.uhid,
          patient.full_name,
          patient.mobile,
          patient.abha_id,
          patient.identity_number,
          patient.city,
          branchName(patient.branch_id),
        ]
          .filter(Boolean)
          .some((value) => value!.toLowerCase().includes(term))

      return matchesStatus && matchesGender && matchesCategory && matchesBranch && matchesSearch
    })
  }, [
    branchFilter,
    branchesQuery.data?.data,
    categoryFilter,
    genderFilter,
    patientsQuery.data?.data,
    search,
    statusFilter,
  ])
  const duplicateQuery = useQuery({
    enabled:
      open &&
      !editing &&
      (Boolean(watchedMobile && watchedMobile.length >= 6) ||
        Boolean(watchedAbhaId && watchedAbhaId.length >= 3) ||
        Boolean(watchedIdentityType && watchedIdentityNumber && watchedIdentityNumber.length >= 3)),
    queryKey: [
      'patient-duplicates',
      watchedMobile,
      watchedFirstName,
      watchedLastName,
      watchedIdentityType,
      watchedIdentityNumber,
      watchedAbhaId,
    ],
    queryFn: () =>
      getPatientDuplicates({
        mobile: watchedMobile,
        name: [watchedFirstName, watchedLastName].filter(Boolean).join(' '),
        identity_type: watchedIdentityType,
        identity_number: watchedIdentityNumber,
        abha_id: watchedAbhaId,
      }),
  })

  const savePatient = useMutation({
    mutationFn: (values: PatientFormValues) =>
      editing ? updatePatient(editing.id, cleanPayload(values)) : createPatient(cleanPayload(values)),
    onSuccess: async (response) => {
      const wasCreating = !editing
      await queryClient.invalidateQueries({ queryKey: ['patients'] })
      setOpen(false)
      setEditing(null)
      form.reset(defaultPatientValues())

      if (wasCreating) {
        navigate(`/patients/${response.data.id}?registered=1`)
      }
    },
  })

  const startCreate = () => {
    setEditing(null)
    form.reset(defaultPatientValues())
    setOpen(true)
  }

  const startEdit = (patient: Patient) => {
    setEditing(patient)
    form.reset(patientToForm(patient))
    setOpen(true)
  }

  const resetFilters = () => {
    setSearch('')
    setBranchFilter('all')
    setGenderFilter('all')
    setCategoryFilter('all')
    setStatusFilter('all')
  }

  return (
    <Stack spacing={3}>
      <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2} sx={{ justifyContent: 'space-between' }}>
        <Box>
          <Typography component="h1" sx={{ fontWeight: 700 }} variant="h4">
            Patients
          </Typography>
          <Typography color="text.secondary">Search, register and update complete patient demographics.</Typography>
        </Box>
        <Button onClick={startCreate} startIcon={<AddIcon />} variant="contained">
          Register Patient
        </Button>
      </Stack>

      {patientsQuery.isError ? <Alert severity="error">Unable to load patients.</Alert> : null}

      <Box
        sx={{
          alignItems: { xs: 'stretch', xl: 'center' },
          display: 'grid',
          gap: 2,
          gridTemplateColumns: { xs: '1fr', xl: 'minmax(220px, 1fr) 200px 170px 180px 160px auto' },
        }}
      >
        <TextField
          label="Search"
          onChange={(event) => setSearch(event.target.value)}
          size="small"
          slotProps={{
            input: {
              startAdornment: (
                <InputAdornment position="start">
                  <SearchIcon color="action" fontSize="small" />
                </InputAdornment>
              ),
            },
          }}
          value={search}
        />
        <BranchFilterField branches={branchesQuery.data?.data ?? []} onChange={setBranchFilter} value={branchFilter} />
        <GenderFilterField onChange={setGenderFilter} value={genderFilter} />
        <CategoryFilterField onChange={setCategoryFilter} value={categoryFilter} />
        <StatusFilterField onChange={setStatusFilter} value={statusFilter} />
        <Button onClick={resetFilters} startIcon={<RestartAltIcon />} variant="outlined">
          Reset
        </Button>
      </Box>

      <Paper variant="outlined">
        <Table>
          <TableHead>
            <TableRow>
              <TableCell>UHID</TableCell>
              <TableCell>Name</TableCell>
              <TableCell>Mobile</TableCell>
              <TableCell>Gender/Age</TableCell>
              <TableCell>Category</TableCell>
              <TableCell>Branch</TableCell>
              <TableCell>Status</TableCell>
              <TableCell align="right">Actions</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {patients.map((patient) => (
              <TableRow key={patient.id}>
                <TableCell>{patient.uhid}</TableCell>
                <TableCell>{patient.full_name}</TableCell>
                <TableCell>{patient.mobile ?? '-'}</TableCell>
                <TableCell>
                  {patient.gender} / {formatAge(patient)}
                </TableCell>
                <TableCell>{patient.patient_category}</TableCell>
                <TableCell>{branchName(patient.branch_id)}</TableCell>
                <TableCell>
                  <Chip color={patient.status === 'active' ? 'success' : 'default'} label={patient.status} size="small" />
                </TableCell>
                <TableCell align="right">
                  <IconButton
                    aria-label={`View ${patient.full_name}`}
                    onClick={() => navigate(`/patients/${patient.id}`)}
                  >
                    <VisibilityIcon />
                  </IconButton>
                  <IconButton aria-label={`Edit ${patient.full_name}`} onClick={() => startEdit(patient)}>
                    <EditIcon />
                  </IconButton>
                </TableCell>
              </TableRow>
            ))}
            {patients.length === 0 ? (
              <TableRow>
                <TableCell colSpan={8}>
                  <Typography color="text.secondary" sx={{ py: 2, textAlign: 'center' }}>
                    No patients match the selected filters.
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
          onSubmit={form.handleSubmit((values) => savePatient.mutate(values))}
          spacing={2.5}
          sx={{ p: 3, width: { xs: 360, sm: 720 } }}
        >
          <Typography sx={{ fontWeight: 700 }} variant="h6">
            {editing ? 'Edit Patient' : 'Register Patient'}
          </Typography>
          {editing ? <Alert severity="info">UHID {editing.uhid}</Alert> : null}
          {savePatient.isError ? (
            <Alert severity="error">{getApiErrorMessage(savePatient.error, 'Unable to save patient.')}</Alert>
          ) : null}
          {!editing && (duplicateQuery.data?.data.length ?? 0) > 0 ? (
            <Alert severity="warning">
              Possible duplicate:{' '}
              {duplicateQuery.data?.data.map((patient) => `${patient.uhid} ${patient.full_name}`).join(', ')}
            </Alert>
          ) : null}

          <SectionTitle title="Registration" />
          <ResponsiveGrid>
            <SelectField control={form.control} label="Branch" name="branch_id">
              <MenuItem value="">Current branch</MenuItem>
              {(branchesQuery.data?.data ?? []).map((branch: Branch) => (
                <MenuItem key={branch.id} value={branch.id}>
                  {branch.name}
                </MenuItem>
              ))}
            </SelectField>
            <SelectField control={form.control} label="Patient Category" name="patient_category">
              <MenuItem value="general">General</MenuItem>
              <MenuItem value="emergency">Emergency</MenuItem>
              <MenuItem value="vip">VIP</MenuItem>
              <MenuItem value="staff">Staff</MenuItem>
              <MenuItem value="camp">Camp</MenuItem>
              <MenuItem value="unknown">Unknown</MenuItem>
            </SelectField>
            <SelectField control={form.control} label="Registration Source" name="registration_source">
              <MenuItem value="walk_in">Walk-in</MenuItem>
              <MenuItem value="referral">Referral</MenuItem>
              <MenuItem value="online">Online</MenuItem>
              <MenuItem value="camp">Camp</MenuItem>
              <MenuItem value="transfer">Transfer</MenuItem>
            </SelectField>
            <PatientField control={form.control} label="Referred By" name="referred_by" />
          </ResponsiveGrid>

          <SectionTitle title="Demographics" />
          <ResponsiveGrid>
            <SelectField control={form.control} label="Title" name="salutation">
              <MenuItem value="">None</MenuItem>
              <MenuItem value="mr">Mr</MenuItem>
              <MenuItem value="mrs">Mrs</MenuItem>
              <MenuItem value="ms">Ms</MenuItem>
              <MenuItem value="miss">Miss</MenuItem>
              <MenuItem value="master">Master</MenuItem>
              <MenuItem value="baby">Baby</MenuItem>
              <MenuItem value="dr">Dr</MenuItem>
              <MenuItem value="prof">Prof</MenuItem>
            </SelectField>
            <PatientField control={form.control} label="First Name" name="first_name" />
            <PatientField control={form.control} label="Middle Name" name="middle_name" />
            <PatientField control={form.control} label="Last Name" name="last_name" />
            <SelectField control={form.control} label="Gender" name="gender">
              <MenuItem value="unknown">Unknown</MenuItem>
              <MenuItem value="male">Male</MenuItem>
              <MenuItem value="female">Female</MenuItem>
              <MenuItem value="other">Other</MenuItem>
            </SelectField>
            <PatientField control={form.control} label="Date of Birth" name="date_of_birth" type="date" />
            <PatientField control={form.control} label="Age Years" name="age_years" type="number" />
            <PatientField control={form.control} label="Age Months" name="age_months" type="number" />
            <PatientField control={form.control} label="Age Days" name="age_days" type="number" />
            <SelectField control={form.control} label="Blood Group" name="blood_group">
              <MenuItem value="">Not recorded</MenuItem>
              <MenuItem value="a_positive">A+</MenuItem>
              <MenuItem value="a_negative">A-</MenuItem>
              <MenuItem value="b_positive">B+</MenuItem>
              <MenuItem value="b_negative">B-</MenuItem>
              <MenuItem value="ab_positive">AB+</MenuItem>
              <MenuItem value="ab_negative">AB-</MenuItem>
              <MenuItem value="o_positive">O+</MenuItem>
              <MenuItem value="o_negative">O-</MenuItem>
              <MenuItem value="unknown">Unknown</MenuItem>
            </SelectField>
            <SelectField control={form.control} label="Marital Status" name="marital_status">
              <MenuItem value="">Not recorded</MenuItem>
              <MenuItem value="single">Single</MenuItem>
              <MenuItem value="married">Married</MenuItem>
              <MenuItem value="widowed">Widowed</MenuItem>
              <MenuItem value="divorced">Divorced</MenuItem>
              <MenuItem value="separated">Separated</MenuItem>
              <MenuItem value="unknown">Unknown</MenuItem>
            </SelectField>
            <PatientField control={form.control} label="Occupation" name="occupation" />
            <PatientField control={form.control} label="Nationality" name="nationality" />
            <PatientField control={form.control} label="Preferred Language" name="preferred_language" />
          </ResponsiveGrid>

          <SectionTitle title="Contact and Address" />
          <ResponsiveGrid>
            <PatientField control={form.control} label="Mobile" name="mobile" />
            <PatientField control={form.control} label="Alternate Mobile" name="alternate_mobile" />
            <PatientField control={form.control} label="Email" name="email" />
            <PatientField control={form.control} label="City" name="city" />
            <PatientField control={form.control} label="District" name="district" />
            <PatientField control={form.control} label="State" name="state" />
            <PatientField control={form.control} label="Pincode" name="pincode" />
            <PatientField control={form.control} label="Country" name="country" />
          </ResponsiveGrid>
          <PatientField control={form.control} label="Address" multiline name="address" rows={2} />

          <SectionTitle title="Identity" />
          <ResponsiveGrid>
            <SelectField control={form.control} label="Identity Type" name="identity_type">
              <MenuItem value="">None</MenuItem>
              <MenuItem value="aadhaar">Aadhaar</MenuItem>
              <MenuItem value="passport">Passport</MenuItem>
              <MenuItem value="driving_license">Driving License</MenuItem>
              <MenuItem value="voter_id">Voter ID</MenuItem>
              <MenuItem value="other">Other</MenuItem>
            </SelectField>
            <PatientField control={form.control} label="Identity Number" name="identity_number" />
            <PatientField control={form.control} label="ABHA / Health ID" name="abha_id" />
          </ResponsiveGrid>

          <SectionTitle title="Emergency and Guardian" />
          <ResponsiveGrid>
            <PatientField control={form.control} label="Guardian Name" name="guardian_name" />
            <PatientField control={form.control} label="Guardian Relation" name="guardian_relation" />
            <PatientField control={form.control} label="Guardian Mobile" name="guardian_mobile" />
            <PatientField control={form.control} label="Emergency Contact" name="emergency_contact_name" />
            <PatientField control={form.control} label="Emergency Mobile" name="emergency_contact_mobile" />
            <PatientField control={form.control} label="Emergency Relation" name="emergency_contact_relation" />
          </ResponsiveGrid>

          <SectionTitle title="Consent and Status" />
          <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1}>
            <ConsentField control={form.control} label="SMS" name="consent_sms" />
            <ConsentField control={form.control} label="Email" name="consent_email" />
            <ConsentField control={form.control} label="WhatsApp" name="consent_whatsapp" />
          </Stack>
          <ResponsiveGrid>
            <SelectField control={form.control} label="Status" name="status">
              <MenuItem value="active">Active</MenuItem>
              <MenuItem value="inactive">Inactive</MenuItem>
              <MenuItem value="deceased">Deceased</MenuItem>
            </SelectField>
          </ResponsiveGrid>
          <PatientField control={form.control} label="Remarks" multiline name="remarks" rows={2} />

          <Button loading={savePatient.isPending} type="submit" variant="contained">
            Save Patient
          </Button>
        </Stack>
      </Drawer>
    </Stack>
  )
}

function BranchFilterField({
  branches,
  value,
  onChange,
}: {
  branches: Branch[]
  value: BranchFilter
  onChange: (value: BranchFilter) => void
}) {
  return (
    <TextField label="Branch" onChange={(event) => onChange(event.target.value as BranchFilter)} select size="small" value={value}>
      <MenuItem value="all">Any branch</MenuItem>
      {branches.map((branch) => (
        <MenuItem key={branch.id} value={String(branch.id)}>
          {branch.name}
        </MenuItem>
      ))}
    </TextField>
  )
}

function GenderFilterField({ value, onChange }: { value: GenderFilter; onChange: (value: GenderFilter) => void }) {
  return (
    <TextField label="Gender" onChange={(event) => onChange(event.target.value as GenderFilter)} select size="small" value={value}>
      <MenuItem value="all">Any gender</MenuItem>
      <MenuItem value="male">Male</MenuItem>
      <MenuItem value="female">Female</MenuItem>
      <MenuItem value="other">Other</MenuItem>
      <MenuItem value="unknown">Unknown</MenuItem>
    </TextField>
  )
}

function CategoryFilterField({
  value,
  onChange,
}: {
  value: PatientCategoryFilter
  onChange: (value: PatientCategoryFilter) => void
}) {
  return (
    <TextField label="Category" onChange={(event) => onChange(event.target.value as PatientCategoryFilter)} select size="small" value={value}>
      <MenuItem value="all">Any category</MenuItem>
      <MenuItem value="general">General</MenuItem>
      <MenuItem value="emergency">Emergency</MenuItem>
      <MenuItem value="vip">VIP</MenuItem>
      <MenuItem value="staff">Staff</MenuItem>
      <MenuItem value="camp">Camp</MenuItem>
      <MenuItem value="unknown">Unknown</MenuItem>
    </TextField>
  )
}

function StatusFilterField({
  value,
  onChange,
}: {
  value: PatientStatusFilter
  onChange: (value: PatientStatusFilter) => void
}) {
  return (
    <TextField label="Status" onChange={(event) => onChange(event.target.value as PatientStatusFilter)} select size="small" value={value}>
      <MenuItem value="all">All status</MenuItem>
      <MenuItem value="active">Active</MenuItem>
      <MenuItem value="inactive">Inactive</MenuItem>
      <MenuItem value="deceased">Deceased</MenuItem>
    </TextField>
  )
}

function SectionTitle({ title }: { title: string }) {
  return (
    <Typography color="text.secondary" sx={{ fontWeight: 700, textTransform: 'uppercase' }} variant="caption">
      {title}
    </Typography>
  )
}

function ResponsiveGrid({ children }: { children: React.ReactNode }) {
  return (
    <Box sx={{ display: 'grid', gap: 2, gridTemplateColumns: { xs: '1fr', sm: 'repeat(2, minmax(0, 1fr))' } }}>
      {children}
    </Box>
  )
}

function SelectField({
  control,
  label,
  name,
  children,
}: {
  control: any
  label: string
  name: keyof PatientFormValues
  children: React.ReactNode
}) {
  return (
    <Controller
      control={control}
      name={name}
      render={({ field, fieldState }) => (
        <TextField
          error={Boolean(fieldState.error)}
          fullWidth
          helperText={fieldState.error?.message}
          label={label}
          onChange={(event) => {
            if (name === 'branch_id') {
              field.onChange(event.target.value ? Number(event.target.value) : null)
              return
            }

            field.onChange(event.target.value || null)
          }}
          select
          value={field.value ?? ''}
        >
          {children}
        </TextField>
      )}
    />
  )
}

function PatientField({
  control,
  label,
  name,
  type = 'text',
  multiline = false,
  rows,
}: {
  control: any
  label: string
  name: keyof PatientFormValues
  type?: string
  multiline?: boolean
  rows?: number
}) {
  return (
    <Controller
      control={control}
      name={name}
      render={({ field, fieldState }) => (
        <TextField
          {...field}
          error={Boolean(fieldState.error)}
          fullWidth
          helperText={fieldState.error?.message}
          label={label}
          multiline={multiline}
          onChange={(event) => {
            if (type === 'number') {
              field.onChange(event.target.value === '' ? null : Number(event.target.value))
              return
            }

            field.onChange(event.target.value)
          }}
          rows={rows}
          slotProps={type === 'date' ? { inputLabel: { shrink: true } } : undefined}
          type={type}
          value={field.value ?? ''}
        />
      )}
    />
  )
}

function ConsentField({ control, label, name }: { control: any; label: string; name: keyof PatientFormValues }) {
  return (
    <Controller
      control={control}
      name={name}
      render={({ field }) => (
        <FormControlLabel
          control={<Checkbox checked={Boolean(field.value)} onChange={(event) => field.onChange(event.target.checked)} />}
          label={label}
        />
      )}
    />
  )
}

function cleanPayload(values: PatientFormValues): PatientPayload {
  return Object.fromEntries(
    Object.entries(values).map(([key, value]) => [key, value === '' ? null : value]),
  ) as PatientPayload
}

function patientToForm(patient: Patient): PatientFormValues {
  return {
    branch_id: patient.branch_id,
    salutation: patient.salutation,
    patient_category: patient.patient_category,
    registration_source: patient.registration_source,
    referred_by: patient.referred_by,
    first_name: patient.first_name,
    middle_name: patient.middle_name,
    last_name: patient.last_name,
    gender: patient.gender,
    blood_group: patient.blood_group,
    marital_status: patient.marital_status,
    occupation: patient.occupation,
    nationality: patient.nationality,
    preferred_language: patient.preferred_language,
    date_of_birth: patient.date_of_birth,
    age_years: patient.age_years,
    age_months: patient.age_months,
    age_days: patient.age_days,
    mobile: patient.mobile,
    alternate_mobile: patient.alternate_mobile,
    email: patient.email,
    address: patient.address,
    city: patient.city,
    district: patient.district,
    state: patient.state,
    pincode: patient.pincode,
    country: patient.country,
    identity_type: patient.identity_type,
    identity_number: patient.identity_number,
    abha_id: patient.abha_id,
    guardian_name: patient.guardian_name,
    guardian_relation: patient.guardian_relation,
    guardian_mobile: patient.guardian_mobile,
    emergency_contact_name: patient.emergency_contact_name,
    emergency_contact_mobile: patient.emergency_contact_mobile,
    emergency_contact_relation: patient.emergency_contact_relation,
    consent_sms: patient.consent_sms,
    consent_email: patient.consent_email,
    consent_whatsapp: patient.consent_whatsapp,
    remarks: patient.remarks,
    status: patient.status,
  }
}

function defaultPatientValues(): PatientFormValues {
  return {
    branch_id: null,
    salutation: null,
    patient_category: 'general',
    registration_source: 'walk_in',
    referred_by: '',
    first_name: '',
    middle_name: '',
    last_name: '',
    gender: 'unknown',
    blood_group: null,
    marital_status: null,
    occupation: '',
    nationality: 'Indian',
    preferred_language: 'English',
    date_of_birth: null,
    age_years: null,
    age_months: null,
    age_days: null,
    mobile: '',
    alternate_mobile: '',
    email: '',
    address: '',
    city: '',
    district: '',
    state: '',
    pincode: '',
    country: 'India',
    identity_type: null,
    identity_number: '',
    abha_id: '',
    guardian_name: '',
    guardian_relation: '',
    guardian_mobile: '',
    emergency_contact_name: '',
    emergency_contact_mobile: '',
    emergency_contact_relation: '',
    consent_sms: true,
    consent_email: false,
    consent_whatsapp: false,
    remarks: '',
    status: 'active',
  }
}

function formatAge(patient: Patient): string {
  return [
    patient.age_years !== null ? `${patient.age_years}y` : null,
    patient.age_months !== null ? `${patient.age_months}m` : null,
    patient.age_days !== null ? `${patient.age_days}d` : null,
  ]
    .filter(Boolean)
    .join(' ') || '-'
}
