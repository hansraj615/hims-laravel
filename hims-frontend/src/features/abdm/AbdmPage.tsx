import BadgeIcon from '@mui/icons-material/Badge'
import QrCodeScannerIcon from '@mui/icons-material/QrCodeScanner'
import VerifiedUserIcon from '@mui/icons-material/VerifiedUser'
import {
  Alert,
  Autocomplete,
  Box,
  Button,
  Paper,
  Stack,
  Tab,
  Tabs,
  TextField,
  Typography,
} from '@mui/material'
import { useMutation, useQuery } from '@tanstack/react-query'
import { useMemo, useState } from 'react'
import { Link as RouterLink } from 'react-router-dom'
import {
  confirmAbhaCreate,
  confirmAbhaVerify,
  getAbdmStatus,
  initAbhaCreate,
  initAbhaVerify,
  resolveScanShare,
} from '../../api/abdm'
import { getApiErrorMessage } from '../../api/errors'
import { getPatients } from '../../api/patients'
import { usePermissions } from '../auth/usePermissions'

export function AbdmPage() {
  const { can } = usePermissions()
  const canManage = can('abdm.manage')
  const [tab, setTab] = useState(0)

  const statusQuery = useQuery({
    queryKey: ['abdm-status'],
    queryFn: getAbdmStatus,
    enabled: canManage,
    retry: false,
  })

  const patientsQuery = useQuery({
    queryKey: ['patients-for-abdm'],
    queryFn: getPatients,
    enabled: canManage && can('patients.manage'),
  })

  const patients = patientsQuery.data?.data ?? []
  const status = statusQuery.data?.data

  const [patientId, setPatientId] = useState<number | null>(null)
  const [abhaNumber, setAbhaNumber] = useState('')
  const [mobile, setMobile] = useState('')
  const [verifyTxn, setVerifyTxn] = useState('')
  const [verifyOtp, setVerifyOtp] = useState('')

  const [aadhaar, setAadhaar] = useState('')
  const [createMobile, setCreateMobile] = useState('')
  const [firstName, setFirstName] = useState('')
  const [lastName, setLastName] = useState('')
  const [createTxn, setCreateTxn] = useState('')
  const [createOtp, setCreateOtp] = useState('')

  const [shareCode, setShareCode] = useState('')
  const [lastProfile, setLastProfile] = useState<string>('')

  const selectedPatient = useMemo(
    () => patients.find((patient) => patient.id === patientId) ?? null,
    [patients, patientId],
  )

  const verifyInitMutation = useMutation({
    mutationFn: () =>
      initAbhaVerify({
        abha_number: abhaNumber || undefined,
        mobile: mobile || undefined,
        patient_id: patientId ?? undefined,
      }),
    onSuccess: (response) => setVerifyTxn(response.data.external_txn_id),
  })

  const verifyConfirmMutation = useMutation({
    mutationFn: () =>
      confirmAbhaVerify({
        external_txn_id: verifyTxn,
        otp: verifyOtp,
        abha_number: abhaNumber || undefined,
        mobile: mobile || undefined,
        patient_id: patientId ?? undefined,
        link_patient: Boolean(patientId),
      }),
    onSuccess: (response) => setLastProfile(JSON.stringify(response.data.profile ?? {}, null, 2)),
  })

  const createInitMutation = useMutation({
    mutationFn: () =>
      initAbhaCreate({
        aadhaar_number: aadhaar,
        mobile: createMobile,
        first_name: firstName || undefined,
        last_name: lastName || undefined,
        patient_id: patientId ?? undefined,
      }),
    onSuccess: (response) => setCreateTxn(response.data.external_txn_id),
  })

  const createConfirmMutation = useMutation({
    mutationFn: () =>
      confirmAbhaCreate({
        external_txn_id: createTxn,
        otp: createOtp,
        aadhaar_number: aadhaar,
        mobile: createMobile,
        first_name: firstName || undefined,
        last_name: lastName || undefined,
        patient_id: patientId ?? undefined,
        link_patient: Boolean(patientId),
      }),
    onSuccess: (response) => setLastProfile(JSON.stringify(response.data.profile ?? {}, null, 2)),
  })

  const scanMutation = useMutation({
    mutationFn: () => resolveScanShare({ share_code: shareCode, register_patient: true }),
    onSuccess: (response) => setLastProfile(JSON.stringify(response.data, null, 2)),
  })

  const actionError =
    verifyInitMutation.error ||
    verifyConfirmMutation.error ||
    createInitMutation.error ||
    createConfirmMutation.error ||
    scanMutation.error ||
    statusQuery.error

  return (
    <Stack spacing={3}>
      <Box>
        <Typography component="h1" sx={{ fontWeight: 700 }} variant="h4">
          ABDM M1
        </Typography>
        <Typography color="text.secondary">
          ABHA verify / create and Scan & Share OPD intake (sandbox-ready gateway).
        </Typography>
      </Box>

      {statusQuery.isError ? (
        <Alert severity="warning">{getApiErrorMessage(statusQuery.error, 'ABDM status unavailable. Enable ABDM_ENABLED and abdm.manage.')}</Alert>
      ) : null}

      {status ? (
        <Alert severity={status.enabled ? 'success' : 'info'}>
          Gateway {status.enabled ? 'enabled' : 'disabled'} · provider <strong>{status.provider}</strong>
          {status.demo_otp_hint ? ` · ${status.demo_otp_hint}` : ''}
        </Alert>
      ) : null}

      {actionError ? <Alert severity="error">{getApiErrorMessage(actionError, 'ABDM action failed.')}</Alert> : null}

      <Paper variant="outlined" sx={{ p: 2 }}>
        <Autocomplete
          options={patients}
          value={selectedPatient}
          onChange={(_, value) => setPatientId(value?.id ?? null)}
          getOptionLabel={(option) => `${option.uhid} — ${option.full_name}`}
          renderInput={(params) => (
            <TextField {...params} label="Link to patient (optional for verify/create)" helperText="Required only when linking to an existing UHID" />
          )}
        />
      </Paper>

      <Paper variant="outlined">
        <Tabs value={tab} onChange={(_, value) => setTab(value)} variant="scrollable">
          <Tab icon={<VerifiedUserIcon />} iconPosition="start" label="Verify ABHA" />
          <Tab icon={<BadgeIcon />} iconPosition="start" label="Create ABHA" />
          <Tab icon={<QrCodeScannerIcon />} iconPosition="start" label="Scan & Share" />
        </Tabs>

        <Box sx={{ p: 2 }}>
          {tab === 0 ? (
            <Stack spacing={2}>
              <TextField label="ABHA number" value={abhaNumber} onChange={(e) => setAbhaNumber(e.target.value)} />
              <TextField label="Mobile" value={mobile} onChange={(e) => setMobile(e.target.value)} />
              <Button
                variant="contained"
                disabled={verifyInitMutation.isPending || (!abhaNumber && !mobile)}
                onClick={() => verifyInitMutation.mutate()}
              >
                Send OTP
              </Button>
              <TextField label="Gateway txn id" value={verifyTxn} onChange={(e) => setVerifyTxn(e.target.value)} />
              <TextField label="OTP" value={verifyOtp} onChange={(e) => setVerifyOtp(e.target.value)} />
              <Button
                variant="outlined"
                disabled={!verifyTxn || !verifyOtp || verifyConfirmMutation.isPending}
                onClick={() => verifyConfirmMutation.mutate()}
              >
                Confirm & {patientId ? 'link patient' : 'fetch profile'}
              </Button>
            </Stack>
          ) : null}

          {tab === 1 ? (
            <Stack spacing={2}>
              <TextField label="Aadhaar number" value={aadhaar} onChange={(e) => setAadhaar(e.target.value)} />
              <TextField label="Mobile" value={createMobile} onChange={(e) => setCreateMobile(e.target.value)} />
              <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2}>
                <TextField label="First name" value={firstName} onChange={(e) => setFirstName(e.target.value)} fullWidth />
                <TextField label="Last name" value={lastName} onChange={(e) => setLastName(e.target.value)} fullWidth />
              </Stack>
              <Button
                variant="contained"
                disabled={!aadhaar || !createMobile || createInitMutation.isPending}
                onClick={() => createInitMutation.mutate()}
              >
                Send create OTP
              </Button>
              <TextField label="Gateway txn id" value={createTxn} onChange={(e) => setCreateTxn(e.target.value)} />
              <TextField label="OTP" value={createOtp} onChange={(e) => setCreateOtp(e.target.value)} />
              <Button
                variant="outlined"
                disabled={!createTxn || !createOtp || createConfirmMutation.isPending}
                onClick={() => createConfirmMutation.mutate()}
              >
                Confirm create
              </Button>
            </Stack>
          ) : null}

          {tab === 2 ? (
            <Stack spacing={2}>
              <TextField
                label="Scan & Share token / share code"
                value={shareCode}
                onChange={(e) => setShareCode(e.target.value)}
                helperText="Paste the token from the patient’s ABHA app QR / share payload"
              />
              <Button
                variant="contained"
                startIcon={<QrCodeScannerIcon />}
                disabled={!shareCode || scanMutation.isPending}
                onClick={() => scanMutation.mutate()}
              >
                Resolve & register OPD patient
              </Button>
              {scanMutation.data?.data.patient ? (
                <Alert severity="success">
                  Registered {scanMutation.data.data.patient.full_name} ({scanMutation.data.data.patient.uhid}).{' '}
                  <Button component={RouterLink} to={`/patients/${scanMutation.data.data.patient.id}`} size="small">
                    Open profile
                  </Button>
                </Alert>
              ) : null}
            </Stack>
          ) : null}
        </Box>
      </Paper>

      {lastProfile ? (
        <Paper variant="outlined" sx={{ p: 2 }}>
          <Typography variant="subtitle1" sx={{ mb: 1 }}>
            Last gateway profile / response
          </Typography>
          <Box component="pre" sx={{ m: 0, whiteSpace: 'pre-wrap', fontSize: 12 }}>
            {lastProfile}
          </Box>
        </Paper>
      ) : null}
    </Stack>
  )
}
