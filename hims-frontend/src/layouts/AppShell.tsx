import AdminPanelSettingsIcon from '@mui/icons-material/AdminPanelSettings'
import CalendarMonthIcon from '@mui/icons-material/CalendarMonth'
import DashboardIcon from '@mui/icons-material/Dashboard'
import EventNoteIcon from '@mui/icons-material/EventNote'
import LogoutIcon from '@mui/icons-material/Logout'
import MenuIcon from '@mui/icons-material/Menu'
import PersonSearchIcon from '@mui/icons-material/PersonSearch'
import PeopleIcon from '@mui/icons-material/People'
import ReceiptLongIcon from '@mui/icons-material/ReceiptLong'
import QueueIcon from '@mui/icons-material/FormatListNumbered'
import ScienceIcon from '@mui/icons-material/Science'
import HealthAndSafetyIcon from '@mui/icons-material/HealthAndSafety'
import LocalHospitalIcon from '@mui/icons-material/LocalHospital'
import {
  AppBar,
  Box,
  Divider,
  Drawer,
  IconButton,
  InputAdornment,
  List,
  ListItemButton,
  ListItemIcon,
  ListItemText,
  MenuItem,
  TextField,
  Toolbar,
  Typography,
  useMediaQuery,
} from '@mui/material'
import { useTheme } from '@mui/material/styles'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import { useEffect, useState } from 'react'
import { NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom'
import { logout } from '../api/auth'
import { getCurrentContext } from '../api/context'
import { NotificationsMenu } from '../features/notifications/NotificationsMenu'
import { usePermissions } from '../features/auth/usePermissions'
import { useTenantStore } from '../store/tenantStore'

const drawerWidth = 272

type NavItem = {
  label: string
  path: string
  icon: ReactNode
  permission?: string | string[]
}

const navigation: NavItem[] = [
  { label: 'Dashboard', path: '/dashboard', icon: <DashboardIcon /> },
  { label: 'Patients', path: '/patients', icon: <PeopleIcon />, permission: 'patients.manage' },
  { label: 'Appointments', path: '/appointments', icon: <CalendarMonthIcon />, permission: 'appointments.manage' },
  {
    label: 'OPD Queue',
    path: '/opd/queue',
    icon: <QueueIcon />,
    permission: ['appointments.manage', 'opd.consult', 'opd.vitals'],
  },
  { label: 'Consultations', path: '/opd/consultations', icon: <EventNoteIcon />, permission: 'opd.consult' },
  {
    label: 'Diagnostics',
    path: '/diagnostics',
    icon: <ScienceIcon />,
    permission: ['diagnostics.order', 'diagnostics.result', 'billing.manage'],
  },
  { label: 'IPD', path: '/ipd', icon: <LocalHospitalIcon />, permission: 'ipd.manage' },
  { label: 'ABDM', path: '/abdm', icon: <HealthAndSafetyIcon />, permission: 'abdm.manage' },
  { label: 'Billing', path: '/billing', icon: <ReceiptLongIcon />, permission: 'billing.manage' },
  {
    label: 'Admin',
    path: '/admin',
    icon: <AdminPanelSettingsIcon />,
    permission: [
      'admin.hospitals.view',
      'admin.users.manage',
      'admin.roles.view',
      'admin.branches.view',
      'admin.departments.view',
    ],
  },
]

export function AppShell() {
  const theme = useTheme()
  const location = useLocation()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const isDesktop = useMediaQuery(theme.breakpoints.up('lg'))
  const [mobileOpen, setMobileOpen] = useState(false)
  const [searchTerm, setSearchTerm] = useState('')
  const { can } = usePermissions()
  const contextQuery = useQuery({
    queryKey: ['current-context'],
    queryFn: getCurrentContext,
    retry: false,
  })

  const context = contextQuery.data?.data
  const visibleNavigation = navigation.filter((item) => can(item.permission))
  const setSelection = useTenantStore((state) => state.setSelection)
  const clearSelection = useTenantStore((state) => state.clearSelection)
  const selectedHospitalId = useTenantStore((state) => state.hospitalId)
  const selectedBranchId = useTenantStore((state) => state.branchId)

  useEffect(() => {
    if (!context) {
      return
    }

    if (!selectedHospitalId || !selectedBranchId) {
      setSelection({
        hospitalId: context.hospital.id,
        branchId: context.branch?.id ?? null,
      })
    }
  }, [context, selectedBranchId, selectedHospitalId, setSelection])

  const logoutMutation = useMutation({
    mutationFn: logout,
    onSettled: async () => {
      clearSelection()
      queryClient.clear()
      navigate('/login', { replace: true })
    },
  })

  const assignmentOptions = context?.available_assignments ?? []
  const selectedAssignmentValue =
    assignmentOptions.find(
      (item) => item.hospital?.id === (selectedHospitalId ?? context?.hospital.id) && item.branch?.id === (selectedBranchId ?? context?.branch?.id),
    )?.id ??
    context?.assignment.id ??
    ''

  const switchAssignment = async (assignmentId: number) => {
    const assignment = assignmentOptions.find((item) => item.id === assignmentId)
    if (!assignment?.hospital) {
      return
    }

    setSelection({
      hospitalId: assignment.hospital.id,
      branchId: assignment.branch?.id ?? null,
    })
    await queryClient.invalidateQueries()
  }
  const submitSearch = () => {
    const term = searchTerm.trim()
    if (term) {
      navigate(`/patients?q=${encodeURIComponent(term)}`)
    }
  }

  const drawer = (
    <Box>
      <Toolbar sx={{ alignItems: 'flex-start', flexDirection: 'column', py: 2 }}>
        <Typography color="primary" sx={{ fontWeight: 800 }} variant="h6">
          HIMS
        </Typography>
        <Typography color="text.secondary" variant="caption">
          OPD Release 1
        </Typography>
      </Toolbar>
      <Divider />
      <List sx={{ px: 1.5, py: 2 }}>
        {visibleNavigation.map((item) => (
          <ListItemButton
            component={NavLink}
            key={item.path}
            to={item.path}
            selected={location.pathname === item.path || location.pathname.startsWith(`${item.path}/`)}
            sx={{ borderRadius: 1, mb: 0.5 }}
          >
            <ListItemIcon>{item.icon}</ListItemIcon>
            <ListItemText primary={item.label} />
          </ListItemButton>
        ))}
      </List>
    </Box>
  )

  return (
    <Box sx={{ display: 'flex', minHeight: '100vh' }}>
      <AppBar
        color="inherit"
        elevation={0}
        position="fixed"
        sx={{
          borderBottom: '1px solid',
          borderColor: 'divider',
          ml: { lg: `${drawerWidth}px` },
          width: { lg: `calc(100% - ${drawerWidth}px)` },
        }}
      >
        <Toolbar sx={{ gap: 2, minHeight: { xs: 72, sm: 76 } }}>
          {!isDesktop ? (
            <IconButton
              aria-label="Open navigation"
              edge="start"
              onClick={() => setMobileOpen(true)}
            >
              <MenuIcon />
            </IconButton>
          ) : null}
          <TextField
            fullWidth
            onChange={(event) => setSearchTerm(event.target.value)}
            onKeyDown={(event) => {
              if (event.key === 'Enter') {
                event.preventDefault()
                submitSearch()
              }
            }}
            placeholder="Search patient by UHID, name or mobile — press Enter"
            size="small"
            slotProps={{
              htmlInput: { 'aria-label': 'Search patient by UHID, name or mobile' },
              input: {
                startAdornment: (
                  <InputAdornment position="start">
                    <PersonSearchIcon color="action" />
                  </InputAdornment>
                ),
              },
            }}
            value={searchTerm}
          />
          <Box sx={{ display: { xs: 'none', md: 'block' }, minWidth: 240 }}>
            {assignmentOptions.length > 1 ? (
              <TextField
                fullWidth
                label="Hospital / Branch"
                onChange={(event) => {
                  void switchAssignment(Number(event.target.value))
                }}
                select
                size="small"
                value={selectedAssignmentValue}
              >
                {assignmentOptions.map((assignment) => (
                  <MenuItem key={assignment.id} value={assignment.id}>
                    {assignment.hospital?.name ?? 'Hospital'}
                    {assignment.branch ? ` · ${assignment.branch.name}` : ''}
                  </MenuItem>
                ))}
              </TextField>
            ) : (
              <>
                <Typography sx={{ fontWeight: 700 }} variant="body2">
                  {context?.hospital.name ?? 'Sign in required'}
                </Typography>
                <Typography color="text.secondary" variant="caption">
                  {context?.branch?.name ?? 'Hospital context unavailable'}
                </Typography>
              </>
            )}
          </Box>
          <NotificationsMenu />
          <IconButton
            aria-label="Log out"
            disabled={logoutMutation.isPending}
            onClick={() => logoutMutation.mutate()}
          >
            <LogoutIcon />
          </IconButton>
        </Toolbar>
      </AppBar>

      <Box component="nav" sx={{ flexShrink: { lg: 0 }, width: { lg: drawerWidth } }}>
        <Drawer
          ModalProps={{ keepMounted: true }}
          onClose={() => setMobileOpen(false)}
          open={mobileOpen}
          sx={{
            display: { xs: 'block', lg: 'none' },
            '& .MuiDrawer-paper': { width: drawerWidth },
          }}
          variant="temporary"
        >
          {drawer}
        </Drawer>
        <Drawer
          open
          sx={{
            display: { xs: 'none', lg: 'block' },
            '& .MuiDrawer-paper': {
              borderRight: '1px solid',
              borderColor: 'divider',
              width: drawerWidth,
            },
          }}
          variant="permanent"
        >
          {drawer}
        </Drawer>
      </Box>

      <Box component="main" sx={{ flexGrow: 1, minWidth: 0 }}>
        <Toolbar sx={{ minHeight: { xs: 72, sm: 76 } }} />
        <Box sx={{ p: { xs: 2, md: 3 } }}>
          <Outlet />
        </Box>
      </Box>
    </Box>
  )
}
