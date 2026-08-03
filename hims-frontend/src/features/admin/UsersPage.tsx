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
  type AdminUser,
  type UserPayload,
  createUser,
  getBranches,
  getRoles,
  getUsers,
  updateUser,
} from '../../api/admin'
import { getApiErrorMessage } from '../../api/errors'
import { StatusFilterToggle, type StatusFilter } from './StatusFilterToggle'

const baseSchema = {
  name: z.string().min(1, 'Name is required'),
  email: z.string().email('Enter a valid email'),
  mobile: z.string().nullable().optional(),
  password: z.string().optional(),
  status: z.enum(['active', 'inactive']),
  role: z.string().min(1, 'Role is required'),
  branch_id: z.number().nullable().optional(),
  department_id: z.number().nullable().optional(),
  assignment_type: z.enum(['staff', 'consultant', 'visiting', 'contract']).nullable().optional(),
  is_default: z.boolean().optional(),
}

const createSchema = z.object({
  ...baseSchema,
  password: z.string().min(8, 'Password must be at least 8 characters'),
})

const editSchema = z.object(baseSchema)

type UserFormValues = z.infer<typeof editSchema>

export function UsersPage() {
  const queryClient = useQueryClient()
  const [editing, setEditing] = useState<AdminUser | null>(null)
  const [open, setOpen] = useState(false)
  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState<StatusFilter>('all')
  const usersQuery = useQuery({ queryKey: ['admin-users'], queryFn: getUsers })
  const rolesQuery = useQuery({ queryKey: ['admin-roles'], queryFn: getRoles })
  const branchesQuery = useQuery({ queryKey: ['admin-branches'], queryFn: getBranches })
  const form = useForm<UserFormValues>({
    resolver: zodResolver(editing ? editSchema : createSchema),
    defaultValues: defaultValues(),
  })

  const users = useMemo(() => {
    const records = usersQuery.data?.data ?? []
    const term = search.trim().toLowerCase()

    return records.filter((user) => {
      const matchesStatus = statusFilter === 'all' || user.status === statusFilter
      const matchesSearch =
        !term ||
        [user.name, user.email, user.mobile, user.roles.join(', ')]
          .filter(Boolean)
          .some((value) => value!.toLowerCase().includes(term))

      return matchesStatus && matchesSearch
    })
  }, [search, statusFilter, usersQuery.data?.data])

  const saveUser = useMutation({
    mutationFn: (values: UserFormValues) => {
      const payload: UserPayload = {
        name: values.name,
        email: values.email,
        mobile: values.mobile || null,
        status: values.status,
        role: values.role,
        branch_id: values.branch_id ?? null,
        department_id: values.department_id ?? null,
        assignment_type: values.assignment_type ?? undefined,
        is_default: values.is_default ?? true,
        ...(values.password ? { password: values.password } : {}),
      }

      return editing ? updateUser(editing.id, payload) : createUser(payload)
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['admin-users'] })
      setOpen(false)
      setEditing(null)
      form.reset(defaultValues())
    },
  })

  const startCreate = () => {
    setEditing(null)
    form.reset(defaultValues())
    setOpen(true)
  }

  const startEdit = (user: AdminUser) => {
    setEditing(user)
    const assignment = user.assignments?.[0]
    form.reset({
      name: user.name,
      email: user.email,
      mobile: user.mobile ?? '',
      password: '',
      status: user.status,
      role: user.roles[0] ?? '',
      branch_id: assignment?.branch_id ?? null,
      department_id: assignment?.department_id ?? null,
      assignment_type: assignment?.assignment_type ?? 'staff',
      is_default: assignment?.is_default ?? true,
    })
    setOpen(true)
  }

  const resetFilters = () => {
    setSearch('')
    setStatusFilter('all')
  }

  return (
    <Stack spacing={3}>
      <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2} sx={{ justifyContent: 'space-between' }}>
        <Box>
          <Typography component="h1" sx={{ fontWeight: 700 }} variant="h4">
            Users
          </Typography>
          <Typography color="text.secondary">Manage staff accounts, roles and branch assignments.</Typography>
        </Box>
        <Button onClick={startCreate} startIcon={<AddIcon />} variant="contained">
          New User
        </Button>
      </Stack>

      {usersQuery.isError ? <Alert severity="error">Unable to load users.</Alert> : null}

      <Box
        sx={{
          alignItems: { xs: 'stretch', md: 'center' },
          display: 'grid',
          gap: 2,
          gridTemplateColumns: { xs: '1fr', md: 'minmax(240px, 1fr) auto auto' },
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
              <TableCell>Email</TableCell>
              <TableCell>Mobile</TableCell>
              <TableCell>Roles</TableCell>
              <TableCell>Status</TableCell>
              <TableCell align="right">Actions</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {users.map((user) => (
              <TableRow key={user.id}>
                <TableCell>{user.name}</TableCell>
                <TableCell>{user.email}</TableCell>
                <TableCell>{user.mobile ?? '-'}</TableCell>
                <TableCell>
                  <Stack direction="row" sx={{ flexWrap: 'wrap', gap: 0.5 }}>
                    {user.roles.map((role) => (
                      <Chip key={role} label={role} size="small" />
                    ))}
                  </Stack>
                </TableCell>
                <TableCell>
                  <Chip color={user.status === 'active' ? 'success' : 'default'} label={user.status} size="small" />
                </TableCell>
                <TableCell align="right">
                  <IconButton aria-label={`Edit ${user.name}`} onClick={() => startEdit(user)}>
                    <EditIcon />
                  </IconButton>
                </TableCell>
              </TableRow>
            ))}
            {users.length === 0 ? (
              <TableRow>
                <TableCell colSpan={6}>
                  <Typography color="text.secondary" sx={{ py: 2, textAlign: 'center' }}>
                    No users match the selected filters.
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
          onSubmit={form.handleSubmit((values) => saveUser.mutate(values))}
          spacing={2}
          sx={{ p: 3, width: { xs: 320, sm: 440 } }}
        >
          <Typography sx={{ fontWeight: 700 }} variant="h6">
            {editing ? 'Edit User' : 'New User'}
          </Typography>
          {saveUser.isError ? (
            <Alert severity="error">{getApiErrorMessage(saveUser.error, 'Unable to save user.')}</Alert>
          ) : null}
          <UserField control={form.control} label="Name" name="name" />
          <UserField control={form.control} label="Email" name="email" type="email" />
          <UserField control={form.control} label="Mobile" name="mobile" />
          <UserField
            control={form.control}
            label={editing ? 'New Password (optional)' : 'Password'}
            name="password"
            type="password"
          />
          <Controller
            control={form.control}
            name="role"
            render={({ field, fieldState }) => (
              <TextField
                {...field}
                error={Boolean(fieldState.error)}
                helperText={fieldState.error?.message}
                label="Role"
                select
              >
                {(rolesQuery.data?.data ?? []).map((role) => (
                  <MenuItem key={role.id} value={role.name}>
                    {role.name}
                  </MenuItem>
                ))}
              </TextField>
            )}
          />
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
          <Controller
            control={form.control}
            name="assignment_type"
            render={({ field }) => (
              <TextField {...field} label="Assignment Type" select value={field.value ?? 'staff'}>
                <MenuItem value="staff">Staff</MenuItem>
                <MenuItem value="consultant">Consultant</MenuItem>
                <MenuItem value="visiting">Visiting</MenuItem>
                <MenuItem value="contract">Contract</MenuItem>
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
          <Button loading={saveUser.isPending} type="submit" variant="contained">
            Save User
          </Button>
        </Stack>
      </Drawer>
    </Stack>
  )
}

function UserField({
  control,
  label,
  name,
  type = 'text',
}: {
  control: any
  label: string
  name: keyof UserFormValues
  type?: string
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
          type={type}
          value={field.value ?? ''}
        />
      )}
    />
  )
}

function defaultValues(): UserFormValues {
  return {
    name: '',
    email: '',
    mobile: '',
    password: '',
    status: 'active',
    role: '',
    branch_id: null,
    department_id: null,
    assignment_type: 'staff',
    is_default: true,
  }
}
