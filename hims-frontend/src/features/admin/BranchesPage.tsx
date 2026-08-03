import AddIcon from '@mui/icons-material/Add'
import EditIcon from '@mui/icons-material/Edit'
import RestartAltIcon from '@mui/icons-material/RestartAlt'
import SearchIcon from '@mui/icons-material/Search'
import {
  Alert,
  Box,
  Button,
  Chip,
  Drawer,
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
import { useMemo, useState } from 'react'
import { Controller, useForm } from 'react-hook-form'
import { z } from 'zod'
import {
  type Branch,
  type BranchPayload,
  createBranch,
  getBranches,
  updateBranch,
} from '../../api/admin'
import { getApiErrorMessage } from '../../api/errors'
import { StatusFilterToggle, type StatusFilter } from './StatusFilterToggle'

type BranchTypeFilter = 'all' | Branch['facility_type']

const schema = z.object({
  name: z.string().min(1, 'Name is required'),
  code: z.string().min(1, 'Code is required').max(30),
  facility_type: z.enum(['hospital', 'clinic', 'diagnostic_centre']),
  address: z.string().optional(),
  city: z.string().optional(),
  state: z.string().optional(),
  pincode: z.string().optional(),
  phone: z.string().optional(),
  timezone: z.string().min(1),
  status: z.enum(['active', 'inactive']),
})

export function BranchesPage() {
  const queryClient = useQueryClient()
  const [editing, setEditing] = useState<Branch | null>(null)
  const [open, setOpen] = useState(false)
  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState<StatusFilter>('all')
  const [typeFilter, setTypeFilter] = useState<BranchTypeFilter>('all')
  const branchesQuery = useQuery({ queryKey: ['admin-branches'], queryFn: getBranches })
  const form = useForm<BranchPayload>({
    resolver: zodResolver(schema),
    defaultValues: defaultBranchValues(),
  })
  const branches = useMemo(() => {
    const records = branchesQuery.data?.data ?? []
    const term = search.trim().toLowerCase()

    return records.filter((branch) => {
      const matchesStatus = statusFilter === 'all' || branch.status === statusFilter
      const matchesType = typeFilter === 'all' || branch.facility_type === typeFilter
      const matchesSearch =
        !term ||
        [branch.name, branch.code, branch.city, branch.state, branch.pincode, branch.phone]
          .filter(Boolean)
          .some((value) => value!.toLowerCase().includes(term))

      return matchesStatus && matchesType && matchesSearch
    })
  }, [branchesQuery.data?.data, search, statusFilter, typeFilter])

  const saveBranch = useMutation({
    mutationFn: (values: BranchPayload) =>
      editing ? updateBranch(editing.id, values) : createBranch(values),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['admin-branches'] })
      setOpen(false)
      setEditing(null)
      form.reset(defaultBranchValues())
    },
  })

  const startCreate = () => {
    setEditing(null)
    form.reset(defaultBranchValues())
    setOpen(true)
  }

  const startEdit = (branch: Branch) => {
    setEditing(branch)
    form.reset({
      name: branch.name,
      code: branch.code,
      facility_type: branch.facility_type,
      address: '',
      city: branch.city ?? '',
      state: branch.state ?? '',
      pincode: branch.pincode ?? '',
      phone: branch.phone ?? '',
      timezone: branch.timezone,
      status: branch.status,
    })
    setOpen(true)
  }

  const resetFilters = () => {
    setSearch('')
    setStatusFilter('all')
    setTypeFilter('all')
  }

  return (
    <Stack spacing={3}>
      <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2} sx={{ justifyContent: 'space-between' }}>
        <Box>
          <Typography component="h1" sx={{ fontWeight: 700 }} variant="h4">
            Branches
          </Typography>
          <Typography color="text.secondary">Manage hospital facilities and branch settings.</Typography>
        </Box>
        <Button onClick={startCreate} startIcon={<AddIcon />} variant="contained">
          New Branch
        </Button>
      </Stack>

      {branchesQuery.isError ? <Alert severity="error">Unable to load branches.</Alert> : null}

      <Box
        sx={{
          alignItems: { xs: 'stretch', md: 'center' },
          display: 'grid',
          gap: 2,
          gridTemplateColumns: { xs: '1fr', md: 'minmax(240px, 1fr) 220px auto auto' },
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
        <TextField
          label="Facility Type"
          onChange={(event) => setTypeFilter(event.target.value as BranchTypeFilter)}
          select
          size="small"
          value={typeFilter}
        >
          <MenuItem value="all">All types</MenuItem>
          <MenuItem value="hospital">Hospital</MenuItem>
          <MenuItem value="clinic">Clinic</MenuItem>
          <MenuItem value="diagnostic_centre">Diagnostic Centre</MenuItem>
        </TextField>
        <StatusFilterToggle onChange={setStatusFilter} value={statusFilter} />
        <Button onClick={resetFilters} startIcon={<RestartAltIcon />} variant="outlined">
          Reset
        </Button>
      </Box>

      <Paper variant="outlined">
        <Table>
          <TableHead>
            <TableRow>
              <TableCell>Name</TableCell>
              <TableCell>Code</TableCell>
              <TableCell>Type</TableCell>
              <TableCell>City</TableCell>
              <TableCell>Status</TableCell>
              <TableCell align="right">Actions</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {branches.map((branch) => (
              <TableRow key={branch.id}>
                <TableCell>{branch.name}</TableCell>
                <TableCell>{branch.code}</TableCell>
                <TableCell>{branch.facility_type}</TableCell>
                <TableCell>{branch.city ?? '-'}</TableCell>
                <TableCell>
                  <Chip color={branch.status === 'active' ? 'success' : 'default'} label={branch.status} size="small" />
                </TableCell>
                <TableCell align="right">
                  <IconButton aria-label={`Edit ${branch.name}`} onClick={() => startEdit(branch)}>
                    <EditIcon />
                  </IconButton>
                </TableCell>
              </TableRow>
            ))}
            {branches.length === 0 ? (
              <TableRow>
                <TableCell colSpan={6}>
                  <Typography color="text.secondary" sx={{ py: 2, textAlign: 'center' }}>
                    No branches match the selected filters.
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
          onSubmit={form.handleSubmit((values) => saveBranch.mutate(values))}
          spacing={2}
          sx={{ p: 3, width: { xs: 320, sm: 420 } }}
        >
          <Typography sx={{ fontWeight: 700 }} variant="h6">
            {editing ? 'Edit Branch' : 'New Branch'}
          </Typography>
          {saveBranch.isError ? (
            <Alert severity="error">
              {getApiErrorMessage(saveBranch.error, 'Unable to save branch.')}
            </Alert>
          ) : null}
          <BranchField control={form.control} label="Name" name="name" />
          <BranchField control={form.control} label="Code" name="code" />
          <Controller
            control={form.control}
            name="facility_type"
            render={({ field }) => (
              <TextField {...field} label="Facility Type" select>
                <MenuItem value="hospital">Hospital</MenuItem>
                <MenuItem value="clinic">Clinic</MenuItem>
                <MenuItem value="diagnostic_centre">Diagnostic Centre</MenuItem>
              </TextField>
            )}
          />
          <BranchField control={form.control} label="City" name="city" />
          <BranchField control={form.control} label="State" name="state" />
          <BranchField control={form.control} label="Pincode" name="pincode" />
          <BranchField control={form.control} label="Phone" name="phone" />
          <BranchField control={form.control} label="Timezone" name="timezone" />
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
          <Button loading={saveBranch.isPending} type="submit" variant="contained">
            Save Branch
          </Button>
        </Stack>
      </Drawer>
    </Stack>
  )
}

function BranchField({ control, label, name }: { control: any; label: string; name: keyof BranchPayload }) {
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
        />
      )}
    />
  )
}

function defaultBranchValues(): BranchPayload {
  return {
    name: '',
    code: '',
    facility_type: 'hospital',
    address: '',
    city: '',
    state: '',
    pincode: '',
    phone: '',
    timezone: 'Asia/Kolkata',
    status: 'active',
  }
}
