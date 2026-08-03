import NotificationsNoneIcon from '@mui/icons-material/NotificationsNone'
import {
  Alert,
  Badge,
  Box,
  Divider,
  IconButton,
  List,
  ListItem,
  ListItemText,
  Popover,
  Typography,
} from '@mui/material'
import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { getNotifications } from '../../api/notifications'
import { formatDateTime } from '../../utils/format'

export function NotificationsMenu() {
  const [anchorEl, setAnchorEl] = useState<HTMLElement | null>(null)
  const open = Boolean(anchorEl)
  const notificationsQuery = useQuery({
    queryKey: ['notifications'],
    queryFn: getNotifications,
    refetchInterval: 60_000,
  })

  const notifications = notificationsQuery.data?.data ?? []
  const unreadCount = notifications.filter((item) => item.status === 'pending' || item.status === 'sent').length

  return (
    <>
      <IconButton
        aria-label="Open notifications"
        onClick={(event) => setAnchorEl(event.currentTarget)}
      >
        <Badge badgeContent={unreadCount > 0 ? Math.min(unreadCount, 9) : 0} color="primary">
          <NotificationsNoneIcon />
        </Badge>
      </IconButton>
      <Popover
        anchorEl={anchorEl}
        anchorOrigin={{ vertical: 'bottom', horizontal: 'right' }}
        onClose={() => setAnchorEl(null)}
        open={open}
        transformOrigin={{ vertical: 'top', horizontal: 'right' }}
      >
        <Box sx={{ width: 360, maxWidth: '90vw' }}>
          <Box sx={{ px: 2, py: 1.5 }}>
            <Typography sx={{ fontWeight: 700 }} variant="subtitle1">
              Notifications
            </Typography>
          </Box>
          <Divider />
          {notificationsQuery.isError ? (
            <Alert severity="error" sx={{ m: 1.5 }}>
              Unable to load notifications.
            </Alert>
          ) : null}
          <List dense sx={{ maxHeight: 360, overflowY: 'auto', py: 0 }}>
            {notifications.map((notification) => (
              <ListItem alignItems="flex-start" key={notification.id} sx={{ px: 2 }}>
                <ListItemText
                  primary={notification.subject || notification.template_code}
                  secondary={
                    <>
                      <Typography component="span" sx={{ display: 'block' }} variant="body2">
                        {notification.body}
                      </Typography>
                      <Typography color="text.secondary" component="span" variant="caption">
                        {formatDateTime(notification.created_at)} · {notification.status}
                      </Typography>
                    </>
                  }
                />
              </ListItem>
            ))}
            {notifications.length === 0 && !notificationsQuery.isError ? (
              <ListItem>
                <ListItemText primary="No notifications yet." />
              </ListItem>
            ) : null}
          </List>
        </Box>
      </Popover>
    </>
  )
}
