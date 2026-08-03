<?php

namespace Database\Seeders;

use App\Domain\Appointments\Models\DoctorFeeMaster;
use App\Domain\Appointments\Models\DoctorSchedule;
use App\Domain\Billing\Models\Service;
use App\Domain\Hospitals\Models\Branch;
use App\Domain\Hospitals\Models\Department;
use App\Domain\Hospitals\Models\Hospital;
use App\Domain\Hospitals\Models\HospitalGroup;
use App\Domain\Hospitals\Models\UserHospitalBranchAssignment;
use App\Domain\IPD\Models\Bed;
use App\Domain\IPD\Models\Ward;
use App\Domain\Notifications\Models\NotificationTemplate;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class HimsDemoSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissionNames = [
            'context.view',
            'admin.hospitals.view',
            'admin.hospitals.manage',
            'admin.branches.view',
            'admin.branches.manage',
            'admin.departments.view',
            'admin.departments.manage',
            'admin.users.manage',
            'admin.roles.view',
            'admin.roles.manage',
            'patients.manage',
            'appointments.manage',
            'opd.consult',
            'opd.vitals',
            'billing.manage',
            'diagnostics.order',
            'diagnostics.result',
            'ipd.manage',
            'abdm.manage',
        ];

        $permissions = collect($permissionNames)->mapWithKeys(fn (string $name) => [
            $name => Permission::findOrCreate($name, 'web'),
        ]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $roles = [
            'platform-admin' => $permissionNames,
            'hospital-admin' => [
                'context.view',
                'admin.hospitals.view',
                'admin.hospitals.manage',
                'admin.branches.view',
                'admin.branches.manage',
                'admin.departments.view',
                'admin.departments.manage',
                'admin.users.manage',
                'admin.roles.view',
                'admin.roles.manage',
                'patients.manage',
                'appointments.manage',
                'billing.manage',
                'diagnostics.order',
                'diagnostics.result',
                'ipd.manage',
                'abdm.manage',
            ],
            'reception' => ['context.view', 'patients.manage', 'appointments.manage', 'diagnostics.order', 'ipd.manage', 'abdm.manage'],
            'nurse' => ['context.view', 'opd.vitals', 'ipd.manage'],
            'compounder' => ['context.view', 'opd.vitals'],
            'doctor' => ['context.view', 'opd.consult', 'opd.vitals', 'diagnostics.order', 'ipd.manage'],
            'lab' => ['context.view', 'diagnostics.order', 'diagnostics.result'],
            'billing' => ['context.view', 'billing.manage', 'diagnostics.order'],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            Role::findOrCreate($roleName, 'web')
                ->syncPermissions($permissions->only($rolePermissions)->values()->all());
        }

        $group = HospitalGroup::firstOrCreate(
            ['name' => 'Demo Health Group'],
            ['legal_name' => 'Demo Health Group Private Limited', 'status' => 'active'],
        );

        $hospital = Hospital::firstOrCreate(
            ['code' => 'DEMOHIMS'],
            [
                'hospital_group_id' => $group->id,
                'name' => 'Demo HIMS Hospital',
                'legal_name' => 'Demo HIMS Hospital Private Limited',
                'registration_number' => 'MH-DEMO-001',
                'gstin' => '27ABCDE1234F1Z5',
                'phone' => '+912212345678',
                'status' => 'active',
            ],
        );

        $branch = Branch::firstOrCreate(
            ['hospital_id' => $hospital->id, 'code' => 'MAIN'],
            [
                'name' => 'Main Branch',
                'facility_type' => 'hospital',
                'address' => 'Demo Road',
                'city' => 'Mumbai',
                'state' => 'Maharashtra',
                'pincode' => '400001',
                'phone' => '+912212345678',
                'timezone' => 'Asia/Kolkata',
                'status' => 'active',
            ],
        );

        $departments = [
            ['name' => 'General Medicine', 'code' => 'GENMED', 'department_type' => 'clinical'],
            ['name' => 'Laboratory', 'code' => 'LAB', 'department_type' => 'diagnostic'],
            ['name' => 'Billing', 'code' => 'BILLING', 'department_type' => 'administrative'],
        ];

        foreach ($departments as $department) {
            Department::firstOrCreate(
                ['hospital_id' => $hospital->id, 'code' => $department['code']],
                [
                    'branch_id' => $branch->id,
                    'name' => $department['name'],
                    'department_type' => $department['department_type'],
                    'status' => 'active',
                ],
            );
        }

        $users = [
            ['name' => 'Platform Admin', 'email' => 'platform@example.com', 'mobile' => '+919900000001', 'role' => 'platform-admin'],
            ['name' => 'Hospital Admin', 'email' => 'admin@example.com', 'mobile' => '+919900000002', 'role' => 'hospital-admin'],
            ['name' => 'Reception User', 'email' => 'reception@example.com', 'mobile' => '+919900000003', 'role' => 'reception'],
            ['name' => 'Doctor User', 'email' => 'doctor@example.com', 'mobile' => '+919900000004', 'role' => 'doctor'],
            ['name' => 'Billing User', 'email' => 'billing@example.com', 'mobile' => '+919900000005', 'role' => 'billing'],
            ['name' => 'Nurse User', 'email' => 'nurse@example.com', 'mobile' => '+919900000006', 'role' => 'nurse'],
            ['name' => 'Compounder User', 'email' => 'compounder@example.com', 'mobile' => '+919900000007', 'role' => 'compounder'],
            ['name' => 'Lab Technician', 'email' => 'lab@example.com', 'mobile' => '+919900000008', 'role' => 'lab'],
        ];

        foreach ($users as $userData) {
            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'mobile' => $userData['mobile'],
                    'password' => Hash::make('password'),
                    'status' => 'active',
                ],
            );

            $user->syncRoles([$userData['role']]);

            UserHospitalBranchAssignment::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'hospital_id' => $hospital->id,
                    'branch_id' => $branch->id,
                    'department_id' => null,
                ],
                [
                    'assignment_type' => 'staff',
                    'is_default' => true,
                    'status' => 'active',
                    'approved_at' => now(),
                ],
            );
        }

        Service::firstOrCreate(
            ['hospital_id' => $hospital->id, 'code' => 'OPDCONSULT'],
            [
                'department_id' => Department::where('hospital_id', $hospital->id)->where('code', 'GENMED')->value('id'),
                'name' => 'OPD Consultation',
                'service_type' => 'consultation',
                'category' => 'consultant_fee',
                'base_rate' => 500,
                'is_tax_exempt' => true,
                'status' => 'active',
            ],
        );

        $catalogSeed = [
            ['code' => 'OPDREG', 'name' => 'OPD Registration Fee', 'service_type' => 'registration', 'category' => 'opd', 'base_rate' => 100, 'dept' => 'GENMED'],
            ['code' => 'IPDDAY', 'name' => 'IPD Bed Day Charge', 'service_type' => 'room', 'category' => 'ipd', 'base_rate' => 2500, 'dept' => 'GENMED'],
            ['code' => 'CBC', 'name' => 'Complete Blood Count', 'service_type' => 'lab', 'category' => 'pathology', 'base_rate' => 350, 'dept' => 'LAB'],
            ['code' => 'XRAYCHEST', 'name' => 'X-Ray Chest PA', 'service_type' => 'imaging', 'category' => 'radiology', 'base_rate' => 450, 'dept' => 'LAB'],
            ['code' => 'DRESSING', 'name' => 'Wound Dressing', 'service_type' => 'procedure', 'category' => 'procedure', 'base_rate' => 300, 'dept' => 'GENMED'],
        ];

        foreach ($catalogSeed as $item) {
            Service::firstOrCreate(
                ['hospital_id' => $hospital->id, 'code' => $item['code']],
                [
                    'department_id' => Department::where('hospital_id', $hospital->id)->where('code', $item['dept'])->value('id'),
                    'name' => $item['name'],
                    'service_type' => $item['service_type'],
                    'category' => $item['category'],
                    'base_rate' => $item['base_rate'],
                    'is_tax_exempt' => true,
                    'status' => 'active',
                ],
            );
        }

        // Ensure legacy OPDCONSULT rows created before category column get the owner category.
        Service::query()
            ->where('hospital_id', $hospital->id)
            ->where('code', 'OPDCONSULT')
            ->update(['category' => 'consultant_fee']);

        $generalWard = Ward::firstOrCreate(
            ['hospital_id' => $hospital->id, 'code' => 'GEN'],
            [
                'branch_id' => $branch->id,
                'name' => 'General Ward',
                'ward_type' => 'general',
                'status' => 'active',
            ],
        );

        $icuWard = Ward::firstOrCreate(
            ['hospital_id' => $hospital->id, 'code' => 'ICU'],
            [
                'branch_id' => $branch->id,
                'name' => 'ICU',
                'ward_type' => 'icu',
                'status' => 'active',
            ],
        );

        foreach (range(1, 6) as $bedNo) {
            Bed::firstOrCreate(
                ['ward_id' => $generalWard->id, 'bed_number' => sprintf('G-%02d', $bedNo)],
                [
                    'hospital_id' => $hospital->id,
                    'branch_id' => $branch->id,
                    'bed_type' => 'general',
                    'status' => 'available',
                ],
            );
        }

        foreach (range(1, 2) as $bedNo) {
            Bed::firstOrCreate(
                ['ward_id' => $icuWard->id, 'bed_number' => sprintf('ICU-%02d', $bedNo)],
                [
                    'hospital_id' => $hospital->id,
                    'branch_id' => $branch->id,
                    'bed_type' => 'icu',
                    'status' => 'available',
                ],
            );
        }

        $doctor = User::where('email', 'doctor@example.com')->firstOrFail();
        $genMedId = Department::where('hospital_id', $hospital->id)->where('code', 'GENMED')->value('id');

        foreach (range(0, 6) as $dayOfWeek) {
            DoctorSchedule::firstOrCreate(
                [
                    'hospital_id' => $hospital->id,
                    'doctor_user_id' => $doctor->id,
                    'day_of_week' => $dayOfWeek,
                    'start_time' => '09:00:00',
                    'end_time' => '13:00:00',
                ],
                [
                    'branch_id' => $branch->id,
                    'department_id' => $genMedId,
                    'slot_duration_minutes' => 30,
                    'status' => 'active',
                ],
            );

            DoctorSchedule::firstOrCreate(
                [
                    'hospital_id' => $hospital->id,
                    'doctor_user_id' => $doctor->id,
                    'day_of_week' => $dayOfWeek,
                    'start_time' => '14:00:00',
                    'end_time' => '17:00:00',
                ],
                [
                    'branch_id' => $branch->id,
                    'department_id' => $genMedId,
                    'slot_duration_minutes' => 30,
                    'status' => 'active',
                ],
            );
        }

        foreach (
            [
                'first_visit' => 500,
                'follow_up' => 300,
                'emergency' => 800,
            ] as $visitType => $feeAmount
        ) {
            DoctorFeeMaster::updateOrCreate(
                [
                    'hospital_id' => $hospital->id,
                    'doctor_user_id' => $doctor->id,
                    'visit_type' => $visitType,
                ],
                [
                    'fee_amount' => $feeAmount,
                    'status' => 'active',
                ],
            );
        }

        $notificationTemplates = [
            [
                'code' => 'patient.registered',
                'channel' => 'in_app',
                'subject' => 'New patient registered',
                'body' => 'Patient {{patient_name}} ({{uhid}}) has been registered.',
            ],
            [
                'code' => 'payment.received',
                'channel' => 'in_app',
                'subject' => 'Payment received',
                'body' => 'Payment of Rs. {{amount}} received against invoice {{invoice_number}} (receipt {{receipt_number}}).',
            ],
            [
                'code' => 'auth.login_otp',
                'channel' => 'email',
                'subject' => 'Your HIMS login OTP',
                'body' => "Hello {{user_name}},\n\nYour login OTP is {{otp}}. It expires in {{ttl_minutes}} minutes.\n\nIf you did not request this, ignore this email.",
            ],
            [
                'code' => 'prescription.ready',
                'channel' => 'email',
                'subject' => 'Prescription {{prescription_number}}',
                'body' => "Dear {{patient_name}} (UHID {{uhid}}),\n\nDr. {{doctor_name}} has issued prescription {{prescription_number}}:\n\n{{items_summary}}\n\nPlease follow the advised dosage. Contact the hospital if you have questions.",
            ],
        ];

        foreach ($notificationTemplates as $template) {
            NotificationTemplate::firstOrCreate(
                ['hospital_id' => $hospital->id, 'code' => $template['code'], 'channel' => $template['channel']],
                [
                    'subject' => $template['subject'],
                    'body' => $template['body'],
                    'status' => 'active',
                ],
            );
        }
    }
}
