import AddIcon from '@mui/icons-material/Add'
import EditIcon from '@mui/icons-material/Edit'
import {
  Alert,
  Box,
  Button,
  Checkbox,
  Chip,
  Drawer,
  FormControlLabel,
  FormGroup,
  IconButton,
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
import { useState } from 'react'
import { Controller, useForm } from 'react-hook-form'
import { z } from 'zod'
import { type Role, type RolePayload, createRole, getPermissions, getRoles, updateRole } from '../../api/admin'
import { getApiErrorMessage } from '../../api/errors'

const SYSTEM_ROLES = ['platform-admin', 'hospital-admin', 'reception', 'doctor', 'billing']

const schema = z.object({
  name: z.string().min(1, 'Name is required'),
  permissions: z.array(z.string()),
})

export function RolesPage() {
  const queryClient = useQueryClient()
  const [editing, setEditing] = useState<Role | null>(null)
  const [open, setOpen] = useState(false)
  const rolesQuery = useQuery({ queryKey: ['admin-roles'], queryFn: getRoles })
  const permissionsQuery = useQuery({ queryKey: ['admin-permissions'], queryFn: getPermissions })
  const form = useForm<RolePayload>({
    resolver: zodResolver(schema),
    defaultValues: { name: '', permissions: [] },
  })

  const saveRole = useMutation({
    mutationFn: (values: RolePayload) => (editing ? updateRole(editing.id, values) : createRole(values)),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['admin-roles'] })
      setOpen(false)
      setEditing(null)
      form.reset({ name: '', permissions: [] })
    },
  })

  const startCreate = () => {
    setEditing(null)
    form.reset({ name: '', permissions: [] })
    setOpen(true)
  }

  const startEdit = (role: Role) => {
    setEditing(role)
    form.reset({ name: role.name, permissions: role.permissions })
    setOpen(true)
  }

  const isSystemRole = editing ? SYSTEM_ROLES.includes(editing.name) : false
  const permissions = permissionsQuery.data?.data ?? []

  return (
    <Stack spacing={3}>
      <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2} sx={{ justifyContent: 'space-between' }}>
        <Box>
          <Typography component="h1" sx={{ fontWeight: 700 }} variant="h4">
            Roles &amp; Permissions
          </Typography>
          <Typography color="text.secondary">Manage custom roles and the permissions they grant.</Typography>
        </Box>
        <Button onClick={startCreate} startIcon={<AddIcon />} variant="contained">
          New Role
        </Button>
      </Stack>

      {rolesQuery.isError ? <Alert severity="error">Unable to load roles.</Alert> : null}

      <Paper variant="outlined">
        <Table>
          <TableHead>
            <TableRow>
              <TableCell>Role</TableCell>
              <TableCell>Permissions</TableCell>
              <TableCell align="right">Actions</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {(rolesQuery.data?.data ?? []).map((role) => (
              <TableRow key={role.id}>
                <TableCell>
                  <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
                    <Typography sx={{ fontWeight: 600 }}>{role.name}</Typography>
                    {SYSTEM_ROLES.includes(role.name) ? <Chip label="System" size="small" variant="outlined" /> : null}
                  </Stack>
                </TableCell>
                <TableCell>
                  <Stack direction="row" sx={{ flexWrap: 'wrap', gap: 0.5, maxWidth: 560 }}>
                    {role.permissions.length === 0 ? (
                      <Typography color="text.secondary" variant="body2">
                        No permissions
                      </Typography>
                    ) : (
                      role.permissions.map((permission) => <Chip key={permission} label={permission} size="small" />)
                    )}
                  </Stack>
                </TableCell>
                <TableCell align="right">
                  <IconButton aria-label={`Edit ${role.name}`} onClick={() => startEdit(role)}>
                    <EditIcon />
                  </IconButton>
                </TableCell>
              </TableRow>
            ))}
            {(rolesQuery.data?.data ?? []).length === 0 ? (
              <TableRow>
                <TableCell colSpan={3}>
                  <Typography color="text.secondary" sx={{ py: 2, textAlign: 'center' }}>
                    No roles found.
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
          onSubmit={form.handleSubmit((values) => saveRole.mutate(values))}
          spacing={2}
          sx={{ p: 3, width: { xs: 340, sm: 460 } }}
        >
          <Typography sx={{ fontWeight: 700 }} variant="h6">
            {editing ? 'Edit Role' : 'New Role'}
          </Typography>
          {saveRole.isError ? (
            <Alert severity="error">{getApiErrorMessage(saveRole.error, 'Unable to save role.')}</Alert>
          ) : null}
          {isSystemRole ? <Alert severity="info">System roles cannot be renamed.</Alert> : null}
          <Controller
            control={form.control}
            name="name"
            render={({ field, fieldState }) => (
              <TextField
                {...field}
                disabled={isSystemRole}
                error={Boolean(fieldState.error)}
                helperText={fieldState.error?.message}
                label="Role Name"
              />
            )}
          />
          <Typography color="text.secondary" sx={{ fontWeight: 700, textTransform: 'uppercase' }} variant="caption">
            Permissions
          </Typography>
          <Controller
            control={form.control}
            name="permissions"
            render={({ field }) => (
              <FormGroup>
                {permissions.map((permission) => (
                  <FormControlLabel
                    control={
                      <Checkbox
                        checked={field.value.includes(permission.name)}
                        onChange={(event) => {
                          if (event.target.checked) {
                            field.onChange([...field.value, permission.name])
                            return
                          }

                          field.onChange(field.value.filter((name) => name !== permission.name))
                        }}
                      />
                    }
                    key={permission.id}
                    label={permission.name}
                  />
                ))}
              </FormGroup>
            )}
          />
          <Button loading={saveRole.isPending} type="submit" variant="contained">
            Save Role
          </Button>
        </Stack>
      </Drawer>
    </Stack>
  )
}
