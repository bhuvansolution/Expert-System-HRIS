<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            // User
            'user.view',
            'user.create',
            'user.update',
            'user.delete',

            // Role
            'role.view',
            'role.create',
            'role.update',
            'role.delete',

            // Permission
            'permission.view',

            // Department
            'department.view',
            'department.create',
            'department.update',
            'department.delete',

            // Position
            'position.view',
            'position.create',
            'position.update',
            'position.delete',

            // Employee
            'employee.view',
            'employee.create',
            'employee.update',
            'employee.delete',

            // Attendance
            'attendance.view',
            'attendance.clock_in',
            'attendance.clock_out',
            'attendance.view_all',
            'attendance.report',

            // Leave
            'leave_type.view',
            'leave_type.create',
            'leave_type.update',
            'leave_type.delete',

            'leave_balance.view',
            'leave_balance.view_all',

            'leave_request.view',
            'leave_request.create',
            'leave_request.approve',
            'leave_request.reject',
            'leave_request.cancel',

            'leave_report.view',

            // Performance Period
            'performance_period.view',
            'performance_period.create',
            'performance_period.update',
            'performance_period.delete',

            // Performance Indicator
            'performance_indicator.view',
            'performance_indicator.create',
            'performance_indicator.update',
            'performance_indicator.delete',

            // Performance Review
            'performance_review.view',
            'performance_review.create',
            'performance_review.update',
            'performance_review.delete',
            'performance_review.submit',
            'performance_review.approve',
            'performance_review.reject',

            // Performance Report
            'performance_report.view',

            // Competency
            'competency.view',
            'competency.create',
            'competency.update',
            'competency.delete',

            // Competency Level
            'competency-level.view',
            'competency-level.create',
            'competency-level.update',
            'competency-level.delete',

            // Employee Competency
            'employee-competency.view',
            'employee-competency.create',
            'employee-competency.update',
            'employee-competency.delete',

            // Training
            'training.view',
            'training.create',
            'training.update',
            'training.delete',


            'training.participant.view',
            'training.participant.register',
            'training.participant.update',
            'training.participant.delete',

            'training.status.update',
            'training.history.view',

            'training.evaluation.view',
            'training.evaluation.create',
            'training.evaluation.update',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $superAdmin = Role::firstOrCreate([
            'name' => 'super-admin',
            'guard_name' => 'web',
        ]);

        $admin = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);

        $hrAdmin = Role::firstOrCreate([
            'name' => 'hr-admin',
            'guard_name' => 'web',
        ]);

        $manager = Role::firstOrCreate([
            'name' => 'manager',
            'guard_name' => 'web',
        ]);

        $employee = Role::firstOrCreate([
            'name' => 'employee',
            'guard_name' => 'web',
        ]);

        $superAdmin->syncPermissions($permissions);

        $admin->syncPermissions($permissions);

        $hrAdmin->syncPermissions([
            // User
            'user.view',
            'user.create',
            'user.update',

            // Role
            'role.view',

            // Permission
            'permission.view',

            // Department
            'department.view',
            'department.create',
            'department.update',
            'department.delete',

            // Position
            'position.view',
            'position.create',
            'position.update',
            'position.delete',

            // Employee
            'employee.view',
            'employee.create',
            'employee.update',
            'employee.delete',

            // Attendance
            'attendance.view',
            'attendance.clock_in',
            'attendance.clock_out',
            'attendance.view_all',
            'attendance.report',

            // Leave
            'leave_type.view',
            'leave_type.create',
            'leave_type.update',
            'leave_type.delete',

            'leave_balance.view',
            'leave_balance.view_all',

            'leave_request.view',
            'leave_request.approve',
            'leave_request.reject',
            'leave_request.cancel',

            'leave_report.view',

            // Performance Period
            'performance-period.view',
            'performance-period.create',
            'performance-period.update',
            'performance-period.delete',

            // Performance Indicator
            'performance-indicator.view',
            'performance-indicator.create',
            'performance-indicator.update',
            'performance-indicator.delete',

            // Performance Review
            'performance-review.view',
            'performance-review.create',
            'performance-review.update',
            'performance-review.delete',
            'performance-review.submit',
            'performance-review.approve',
            'performance-review.reject',

            // Performance Report
            'performance-report.view',

            // Competency
            'competency.view',
            'competency.create',
            'competency.update',
            'competency.delete',

            // Competency Level
            'competency-level.view',
            'competency-level.create',
            'competency-level.update',
            'competency-level.delete',

            // Employee Competency
            'employee-competency.view',
            'employee-competency.create',
            'employee-competency.update',
            'employee-competency.delete',
            // Training

            'training.view',
            'training.create',
            'training.update',
            'training.delete',

            'training.participant.view',
            'training.participant.register',
            'training.participant.update',
            'training.participant.delete',

            'training.status.update',

            'training.history.view',

            'training.evaluation.view',
            'training.evaluation.create',
            'training.evaluation.update',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Manager
        |--------------------------------------------------------------------------
        */

        $manager->syncPermissions([
            // User
            'user.view',

            // Employee
            'employee.view',

            // Performance Period
            'performance-period.view',

            // Performance Indicator
            'performance-indicator.view',

            // Performance Review
            'performance-review.view',
            'performance-review.create',
            'performance-review.update',
            'performance-review.submit',
            'performance-review.approve',
            'performance-review.reject',

            // Performance Report
            'performance-report.view',
            // Training
            'training.view',
            'training.participant.view',
            'training.history.view',
            'training.evaluation.view',
            'training.evaluation.create',
            'training.evaluation.update',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Employee
        |--------------------------------------------------------------------------
        */

        $employee->syncPermissions([
            // User
            'user.view',

            // Employee
            'employee.view',

            // Attendance
            'attendance.view',
            'attendance.clock_in',
            'attendance.clock_out',

            // Leave
            'leave_balance.view',

            'leave_request.view',
            'leave_request.create',
            'leave_request.cancel',

            // Performance Period
            'performance-period.view',

            // Performance Indicator
            'performance-indicator.view',

            // Performance Review
            'performance-review.view',
            'performance-review.create',
            'performance-review.update',
            'performance-review.submit',
            // Training
            'training.view',
            'training.history.view',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
