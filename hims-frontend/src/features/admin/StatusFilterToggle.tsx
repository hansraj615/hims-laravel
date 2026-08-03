import { ToggleButton, ToggleButtonGroup } from '@mui/material'

export type StatusFilter = 'all' | 'active' | 'inactive'

type StatusFilterToggleProps = {
  value: StatusFilter
  onChange: (value: StatusFilter) => void
}

export function StatusFilterToggle({ value, onChange }: StatusFilterToggleProps) {
  return (
    <ToggleButtonGroup
      aria-label="Status filter"
      exclusive
      onChange={(_, nextValue: StatusFilter | null) => {
        if (nextValue) {
          onChange(nextValue)
        }
      }}
      size="small"
      value={value}
    >
      <ToggleButton value="all">All</ToggleButton>
      <ToggleButton value="active">Active</ToggleButton>
      <ToggleButton value="inactive">Inactive</ToggleButton>
    </ToggleButtonGroup>
  )
}
