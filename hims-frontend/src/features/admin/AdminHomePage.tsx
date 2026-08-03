import EventAvailableIcon from '@mui/icons-material/EventAvailable'
import BadgeIcon from '@mui/icons-material/Badge'
import BusinessIcon from '@mui/icons-material/Business'
import GroupIcon from '@mui/icons-material/Group'
import LocalHospitalIcon from '@mui/icons-material/LocalHospital'
import WorkspacesIcon from '@mui/icons-material/Workspaces'
import { Box, Card, CardActionArea, CardContent, Stack, Typography } from '@mui/material'
import type { ReactNode } from 'react'
import { useNavigate } from 'react-router-dom'
import { usePermissions } from '../auth/usePermissions'

type AdminCard = {
  title: string
  description: string
  icon: ReactNode
  path: string
  permission: string
}

const cards: AdminCard[] = [
  {
    title: 'Hospital',
    description: 'Registration, GSTIN and compliance details.',
    icon: <LocalHospitalIcon color="primary" fontSize="large" />,
    path: '/admin/hospital',
    permission: 'admin.hospitals.view',
  },
  {
    title: 'Users',
    description: 'Staff accounts, roles and branch assignments.',
    icon: <GroupIcon color="primary" fontSize="large" />,
    path: '/admin/users',
    permission: 'admin.users.manage',
  },
  {
    title: 'Roles & Permissions',
    description: 'Custom roles and access control.',
    icon: <BadgeIcon color="primary" fontSize="large" />,
    path: '/admin/roles',
    permission: 'admin.roles.view',
  },
  {
    title: 'Branches',
    description: 'Facilities, addresses and timezone settings.',
    icon: <BusinessIcon color="primary" fontSize="large" />,
    path: '/admin/branches',
    permission: 'admin.branches.view',
  },
  {
    title: 'Departments',
    description: 'Clinical, diagnostic and administrative departments.',
    icon: <WorkspacesIcon color="primary" fontSize="large" />,
    path: '/admin/departments',
    permission: 'admin.departments.view',
  },
  {
    title: 'Doctor Schedule & Fees',
    description: 'Weekly slots, leaves and consultation pricing.',
    icon: <EventAvailableIcon color="primary" fontSize="large" />,
    path: '/admin/doctor-ops',
    permission: 'admin.users.manage',
  },
]

export function AdminHomePage() {
  const navigate = useNavigate()
  const { can } = usePermissions()
  const visibleCards = cards.filter((card) => can(card.permission))

  return (
    <Stack spacing={3}>
      <Box>
        <Typography component="h1" sx={{ fontWeight: 700 }} variant="h4">
          Administration
        </Typography>
        <Typography color="text.secondary">Configure your hospital, staff, roles and facilities.</Typography>
      </Box>

      <Box
        sx={{
          display: 'grid',
          gap: 2,
          gridTemplateColumns: { xs: '1fr', sm: 'repeat(2, minmax(0, 1fr))', lg: 'repeat(3, minmax(0, 1fr))' },
        }}
      >
        {visibleCards.map((card) => (
          <Card key={card.path} variant="outlined">
            <CardActionArea onClick={() => navigate(card.path)} sx={{ height: '100%' }}>
              <CardContent>
                <Stack spacing={1.5}>
                  {card.icon}
                  <Typography sx={{ fontWeight: 700 }} variant="h6">
                    {card.title}
                  </Typography>
                  <Typography color="text.secondary" variant="body2">
                    {card.description}
                  </Typography>
                </Stack>
              </CardContent>
            </CardActionArea>
          </Card>
        ))}
        {visibleCards.length === 0 ? (
          <Typography color="text.secondary">You do not have access to any administration areas.</Typography>
        ) : null}
      </Box>
    </Stack>
  )
}
