import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import type { ApiEnvelope } from '../../types/api'
import type { AuthUser } from '../../api/auth'
import { RequirePermission } from './RequirePermission'

vi.mock('../../api/auth', () => ({
  getCurrentUser: vi.fn(),
}))

const { getCurrentUser } = await import('../../api/auth')

function mockAuthUser(permissions: string[]) {
  const envelope: ApiEnvelope<AuthUser> = {
    success: true,
    message: 'ok',
    data: { id: 1, name: 'Test User', email: 'user@example.com', mobile: null, status: 'active', roles: [], permissions },
    meta: {},
    errors: null,
    request_id: 'test',
  }

  vi.mocked(getCurrentUser).mockResolvedValue(envelope)
}

function renderProtected(permission: string | string[]) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })

  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={['/protected']}>
        <Routes>
          <Route
            element={
              <RequirePermission permission={permission}>
                <div>Protected Content</div>
              </RequirePermission>
            }
            path="/protected"
          />
          <Route element={<div>Dashboard Page</div>} path="/dashboard" />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

it('renders children when the user has the required permission', async () => {
  mockAuthUser(['patients.manage'])

  renderProtected('patients.manage')

  expect(await screen.findByText('Protected Content')).toBeInTheDocument()
})

it('redirects to the dashboard when the user lacks the required permission', async () => {
  mockAuthUser(['billing.manage'])

  renderProtected('patients.manage')

  expect(await screen.findByText('Dashboard Page')).toBeInTheDocument()
})

it('allows access when any permission in the required list matches', async () => {
  mockAuthUser(['opd.consult'])

  renderProtected(['appointments.manage', 'opd.consult'])

  expect(await screen.findByText('Protected Content')).toBeInTheDocument()
})
