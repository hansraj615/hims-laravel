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
  type Department,
  type DepartmentPayload,
  createDepartment,
  getBranches,
  getDepartments,
  updateDepartment,
} from '../../api/admin'
import { getApiErrorMessage } from '../../api/errors'
import { StatusFilterToggle, type StatusFilter } from './StatusFilterToggle'

type BranchFilter = 'all' | 'global' | `${number}`
type DepartmentTypeFilter = 'all' | Department['department_type']

const schema = z.object({
  branch_id: z.number().nullable().optional(),
  name: z.string().min(1, 'Name is required'),
  code: z.string().min(1, 'Code is required').max(30),
  department_type: z.enum(['clinical', 'diagnostic', 'administrative', 'support']),
  status: z.enum(['active', 'inactive']),
})

export function DepartmentsPage() {
  const queryClient = useQueryClient()
  const [editing, setEditing] = useState<Department | null>(null)
  const [open, setOpen] = useState(false)
  const [search, setSearch] = useState('')
  const [branchFilter, setBranchFilter] = useState<BranchFilter>('all')
  const [departmentTypeFilter, setDepartmentTypeFilter] = useState<DepartmentTypeFilter>('all')
  const [statusFilter, setStatusFilter] = useState<StatusFilter>('all')
  const departmentsQuery = useQuery({ queryKey: ['admin-departments'], queryFn: getDepartments })
  const branchesQuery = useQuery({ queryKey: ['admin-branches'], queryFn: getBranches })
  const branchName = (branchId: number | null) =>
    branchesQuery.data?.data.find((branch) => branch.id === branchId)?.name ?? 'All branches'
  const form = useForm<DepartmentPayload>({
    resolver: zodResolver(schema),
    defaultValues: defaultDepartmentValues(),
  })
  const departments = useMemo(() => {
    const records = departmentsQuery.data?.data ?? []
    const term = search.trim().toLowerCase()

    return records.filter((department) => {
      const matchesStatus = statusFilter === 'all' || department.status === statusFilter
      const matchesType =
        departmentTypeFilter === 'all' || department.department_type === departmentTypeFilter
      const matchesBranch =
        branchFilter === 'all' ||
        (branchFilter === 'global' && department.branch_id === null) ||
        department.branch_id === Number(branchFilter)
      const matchesSearch =
        !term ||
        [department.name, department.code, branchName(department.branch_id), department.department_type]
          .filter(Boolean)
          .some((value) => value!.toLowerCase().includes(term))

      return matchesStatus && matchesType && matchesBranch && matchesSearch
    })
  }, [
    branchFilter,
    branchesQuery.data?.data,
    departmentsQuery.data?.data,
    departmentTypeFilter,
    search,
    statusFilter,
  ])

  const saveDepartment = useMutation({
    mutationFn: (values: DepartmentPayload) =>
      editing ? updateDepartment(editing.id, values) : createDepartment(values),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['admin-departments'] })
      setOpen(false)
      setEditing(null)
      form.reset(defaultDepartmentValues())
    },
  })

  const startCreate = () => {
    setEditing(null)
    form.reset(defaultDepartmentValues())
    setOpen(true)
  }

  const startEdit = (department: Department) => {
    setEditing(department)
    form.reset({
      branch_id: department.branch_id,
      name: department.name,
      code: department.code,
      department_type: department.department_type,
      status: department.status,
    })
    setOpen(true)
  }

  const resetFilters = () => {
    setSearch('')
    setBranchFilter('all')
    setDepartmentTypeFilter('all')
    setStatusFilter('all')
  }

  return (
    <Stack spacing={3}>
      <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2} sx={{ justifyContent: 'space-between' }}>
        <Box>
          <Typography component="h1" sx={{ fontWeight: 700 }} variant="h4">
            Departments
          </Typography>
          <Typography color="text.secondary">Manage clinical, diagnostic and administrative departments.</Typography>
        </Box>
        <Button onClick={startCreate} startIcon={<AddIcon />} variant="contained">
          New Department
        </Button>
      </Stack>

      {departmentsQuery.isError ? <Alert severity="error">Unable to load departments.</Alert> : null}

      <Box
        sx={{
          alignItems: { xs: 'stretch', lg: 'center' },
          display: 'grid',
          gap: 2,
          gridTemplateColumns: { xs: '1fr', lg: 'minmax(220px, 1fr) 220px 220px auto auto' },
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
          label="Branch"
          onChange={(event) => setBranchFilter(event.target.value as BranchFilter)}
          select
          size="small"
          value={branchFilter}
        >
          <MenuItem value="all">Any branch</MenuItem>
          <MenuItem value="global">All branches scope</MenuItem>
          {(branchesQuery.data?.data ?? []).map((branch) => (
            <MenuItem key={branch.id} value={String(branch.id)}>
              {branch.name}
            </MenuItem>
          ))}
        </TextField>
        <TextField
          label="Department Type"
          onChange={(event) => setDepartmentTypeFilter(event.target.value as DepartmentTypeFilter)}
          select
          size="small"
          value={departmentTypeFilter}
        >
          <MenuItem value="all">All types</MenuItem>
          <MenuItem value="clinical">Clinical</MenuItem>
          <MenuItem value="diagnostic">Diagnostic</MenuItem>
          <MenuItem value="administrative">Administrative</MenuItem>
          <MenuItem value="support">Support</MenuItem>
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
              <TableCell>Branch</TableCell>
              <TableCell>Type</TableCell>
              <TableCell>Status</TableCell>
              <TableCell align="right">Actions</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {departments.map((department) => (
              <TableRow key={department.id}>
                <TableCell>{department.name}</TableCell>
                <TableCell>{department.code}</TableCell>
                <TableCell>{branchName(department.branch_id)}</TableCell>
                <TableCell>{department.department_type}</TableCell>
                <TableCell>
                  <Chip color={department.status === 'active' ? 'success' : 'default'} label={department.status} size="small" />
                </TableCell>
                <TableCell align="right">
                  <IconButton aria-label={`Edit ${department.name}`} onClick={() => startEdit(department)}>
                    <EditIcon />
                  </IconButton>
                </TableCell>
              </TableRow>
            ))}
            {departments.length === 0 ? (
              <TableRow>
                <TableCell colSpan={6}>
                  <Typography color="text.secondary" sx={{ py: 2, textAlign: 'center' }}>
                    No departments match the selected filters.
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
          onSubmit={form.handleSubmit((values) => saveDepartment.mutate(values))}
          spacing={2}
          sx={{ p: 3, width: { xs: 320, sm: 420 } }}
        >
          <Typography sx={{ fontWeight: 700 }} variant="h6">
            {editing ? 'Edit Department' : 'New Department'}
          </Typography>
          {saveDepartment.isError ? (
            <Alert severity="error">
              {getApiErrorMessage(saveDepartment.error, 'Unable to save department.')}
            </Alert>
          ) : null}
          <Controller
            control={form.control}
            name="branch_id"
            render={({ field }) => (
              <TextField
                label="Branch"
                onChange={(event) => field.onChange(event.target.value ? Number(event.target.value) : null)}
                select
                value={field.value ?? ''}
              >
                <MenuItem value="">All branches</MenuItem>
                {(branchesQuery.data?.data ?? []).map((branch) => (
                  <MenuItem key={branch.id} value={branch.id}>
                    {branch.name}
                  </MenuItem>
                ))}
              </TextField>
            )}
          />
          <DepartmentField control={form.control} label="Name" name="name" />
          <DepartmentField control={form.control} label="Code" name="code" />
          <Controller
            control={form.control}
            name="department_type"
            render={({ field }) => (
              <TextField {...field} label="Department Type" select>
                <MenuItem value="clinical">Clinical</MenuItem>
                <MenuItem value="diagnostic">Diagnostic</MenuItem>
                <MenuItem value="administrative">Administrative</MenuItem>
                <MenuItem value="support">Support</MenuItem>
              </TextField>
            )}
          />
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
          <Button loading={saveDepartment.isPending} type="submit" variant="contained">
            Save Department
          </Button>
        </Stack>
      </Drawer>
    </Stack>
  )
}

function DepartmentField({ control, label, name }: { control: any; label: string; name: keyof DepartmentPayload }) {
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

function defaultDepartmentValues(): DepartmentPayload {
  return {
    branch_id: null,
    name: '',
    code: '',
    department_type: 'clinical',
    status: 'active',
  }
}
