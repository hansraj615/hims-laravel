import { render, screen } from '@testing-library/react'
import { App } from './App'
import { AppProviders } from './AppProviders'

it('renders the HIMS application shell', () => {
  window.history.pushState({}, '', '/login')

  render(
    <AppProviders>
      <App />
    </AppProviders>,
  )

  expect(screen.getAllByText('HIMS')).not.toHaveLength(0)
  expect(screen.getByText('Sign in to continue')).toBeInTheDocument()
  expect(screen.getByRole('button', { name: 'Sign in' })).toBeInTheDocument()
})
