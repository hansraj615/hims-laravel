import { create } from 'zustand'
import { persist } from 'zustand/middleware'

export type TenantSelection = {
  hospitalId: number | null
  branchId: number | null
}

type TenantStore = TenantSelection & {
  setSelection: (selection: TenantSelection) => void
  clearSelection: () => void
}

export const useTenantStore = create<TenantStore>()(
  persist(
    (set) => ({
      hospitalId: null,
      branchId: null,
      setSelection: (selection) => set(selection),
      clearSelection: () => set({ hospitalId: null, branchId: null }),
    }),
    {
      name: 'hims-tenant-selection',
    },
  ),
)
