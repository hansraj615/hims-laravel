import { Navigate, Route, Routes } from 'react-router-dom'
import { LoginPage } from '../features/auth/LoginPage'
import { ForgotPasswordPage } from '../features/auth/ForgotPasswordPage'
import { ResetPasswordPage } from '../features/auth/ResetPasswordPage'
import { RequireAuth } from '../features/auth/RequireAuth'
import { RequirePermission } from '../features/auth/RequirePermission'
import { AbdmPage } from '../features/abdm/AbdmPage'
import { AdminHomePage } from '../features/admin/AdminHomePage'
import { BranchesPage } from '../features/admin/BranchesPage'
import { DepartmentsPage } from '../features/admin/DepartmentsPage'
import { DoctorOpsPage } from '../features/admin/DoctorOpsPage'
import { HospitalsPage } from '../features/admin/HospitalsPage'
import { RolesPage } from '../features/admin/RolesPage'
import { UsersPage } from '../features/admin/UsersPage'
import { AppointmentsPage } from '../features/appointments/AppointmentsPage'
import { BillingPage } from '../features/billing/BillingPage'
import { ConsultationWorkspacePage } from '../features/consultations/ConsultationWorkspacePage'
import { ConsultationsListPage } from '../features/consultations/ConsultationsListPage'
import { DashboardPage } from '../features/dashboard/DashboardPage'
import { DiagnosticsPage } from '../features/diagnostics/DiagnosticsPage'
import { IpdPage } from '../features/ipd/IpdPage'
import { OpdQueuePage } from '../features/opd/OpdQueuePage'
import { PatientProfilePage } from '../features/patients/PatientProfilePage'
import { PatientsPage } from '../features/patients/PatientsPage'
import { AppShell } from '../layouts/AppShell'

export function App() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route path="/forgot-password" element={<ForgotPasswordPage />} />
      <Route path="/reset-password" element={<ResetPasswordPage />} />
      <Route
        element={
          <RequireAuth>
            <AppShell />
          </RequireAuth>
        }
      >
        <Route index element={<Navigate to="/dashboard" replace />} />
        <Route path="/dashboard" element={<DashboardPage />} />

        <Route
          path="/patients"
          element={
            <RequirePermission permission="patients.manage">
              <PatientsPage />
            </RequirePermission>
          }
        />
        <Route
          path="/patients/:id"
          element={
            <RequirePermission permission="patients.manage">
              <PatientProfilePage />
            </RequirePermission>
          }
        />

        <Route
          path="/appointments"
          element={
            <RequirePermission permission="appointments.manage">
              <AppointmentsPage />
            </RequirePermission>
          }
        />

        <Route
          path="/opd/queue"
          element={
            <RequirePermission permission={['appointments.manage', 'opd.consult', 'opd.vitals']}>
              <OpdQueuePage />
            </RequirePermission>
          }
        />
        <Route
          path="/opd/consultations"
          element={
            <RequirePermission permission="opd.consult">
              <ConsultationsListPage />
            </RequirePermission>
          }
        />
        <Route
          path="/opd/consultations/:id"
          element={
            <RequirePermission permission="opd.consult">
              <ConsultationWorkspacePage />
            </RequirePermission>
          }
        />

        <Route
          path="/ipd"
          element={
            <RequirePermission permission="ipd.manage">
              <IpdPage />
            </RequirePermission>
          }
        />

        <Route
          path="/diagnostics"
          element={
            <RequirePermission permission={['diagnostics.order', 'diagnostics.result', 'billing.manage']}>
              <DiagnosticsPage />
            </RequirePermission>
          }
        />

        <Route
          path="/abdm"
          element={
            <RequirePermission permission="abdm.manage">
              <AbdmPage />
            </RequirePermission>
          }
        />

        <Route
          path="/billing"
          element={
            <RequirePermission permission="billing.manage">
              <BillingPage />
            </RequirePermission>
          }
        />

        <Route
          path="/admin"
          element={
            <RequirePermission
              permission={[
                'admin.hospitals.view',
                'admin.users.manage',
                'admin.roles.view',
                'admin.branches.view',
                'admin.departments.view',
              ]}
            >
              <AdminHomePage />
            </RequirePermission>
          }
        />
        <Route
          path="/admin/hospital"
          element={
            <RequirePermission permission="admin.hospitals.view">
              <HospitalsPage />
            </RequirePermission>
          }
        />
        <Route
          path="/admin/users"
          element={
            <RequirePermission permission="admin.users.manage">
              <UsersPage />
            </RequirePermission>
          }
        />
        <Route
          path="/admin/roles"
          element={
            <RequirePermission permission="admin.roles.view">
              <RolesPage />
            </RequirePermission>
          }
        />
        <Route
          path="/admin/branches"
          element={
            <RequirePermission permission="admin.branches.view">
              <BranchesPage />
            </RequirePermission>
          }
        />
        <Route
          path="/admin/departments"
          element={
            <RequirePermission permission="admin.departments.view">
              <DepartmentsPage />
            </RequirePermission>
          }
        />
        <Route
          path="/admin/doctor-ops"
          element={
            <RequirePermission permission="admin.users.manage">
              <DoctorOpsPage />
            </RequirePermission>
          }
        />
      </Route>
    </Routes>
  )
}
