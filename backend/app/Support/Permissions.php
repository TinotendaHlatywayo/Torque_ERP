<?php

namespace App\Support;

final class Permissions
{
    public const BRANCH_USERS_VIEW = 'branch.users.view';

    public const BRANCH_USERS_MANAGE = 'branch.users.manage';

    public const BRANCH_ROLES_VIEW = 'branch.roles.view';

    public const BRANCH_SETTINGS_VIEW = 'branch.settings.view';

    public const BRANCH_SETTINGS_MANAGE = 'branch.settings.manage';

    public const BRANCH_AUDIT_VIEW = 'branch.audit.view';

    public const BRANCH_REPORTS_VIEW = 'branch.reports.view';

    public const BRANCH_SALES_VIEW = 'branch.sales.view';

    public const BRANCH_SALES_CREATE = 'branch.sales.create';

    public const BRANCH_SALES_VOID = 'branch.sales.void';

    public const BRANCH_SALES_APPROVE_OVERRIDE = 'branch.sales.approve_override';

    public const BRANCH_RESERVATIONS_VIEW = 'branch.reservations.view';

    public const BRANCH_RESERVATIONS_MANAGE = 'branch.reservations.manage';

    public const BRANCH_INVENTORY_VIEW = 'branch.inventory.view';

    public const BRANCH_CUSTOMERS_VIEW = 'branch.customers.view';

    public const BRANCH_CUSTOMERS_MANAGE = 'branch.customers.manage';

    public const BRANCH_INVENTORY_MANAGE = 'branch.inventory.manage';

    public const BRANCH_PURCHASING_VIEW = 'branch.purchasing.view';

    public const BRANCH_PURCHASING_MANAGE = 'branch.purchasing.manage';

    public const BRANCH_FINANCE_VIEW = 'branch.finance.view';

    public const BRANCH_FINANCE_MANAGE = 'branch.finance.manage';

    public const BRANCH_HR_VIEW = 'branch.hr.view';

    public const BRANCH_HR_MANAGE = 'branch.hr.manage';

    public const BRANCH_HR_SENSITIVE_VIEW = 'branch.hr.sensitive.view';

    public const BRANCH_HR_LEAVE_APPROVE = 'branch.hr.leave.approve';

    public const BRANCH_PAYROLL_VIEW = 'branch.payroll.view';

    public const BRANCH_PAYROLL_MANAGE = 'branch.payroll.manage';

    public const BRANCH_PAYROLL_APPROVE = 'branch.payroll.approve';

    public const BRANCH_PAYROLL_EXPORT = 'branch.payroll.export';

    public const BRANCH_TASKS_VIEW = 'branch.tasks.view';

    public const BRANCH_TASKS_MANAGE = 'branch.tasks.manage';

    public const BRANCH_CATALOGUE_VIEW = 'branch.catalogue.view';

    public const BRANCH_CATALOGUE_MANAGE = 'branch.catalogue.manage';

    public const BRANCH_SERVICES_VIEW = 'branch.services.view';

    public const BRANCH_SERVICES_MANAGE = 'branch.services.manage';

    public const BRANCH_JOBS_VIEW = 'branch.jobs.view';

    public const BRANCH_JOBS_MANAGE = 'branch.jobs.manage';

    public const BRANCH_TRANSFERS_VIEW = 'branch.transfers.view';

    public const BRANCH_TRANSFERS_MANAGE = 'branch.transfers.manage';

    public const BRANCH_STOCKTAKES_VIEW = 'branch.stocktakes.view';

    public const BRANCH_STOCKTAKES_MANAGE = 'branch.stocktakes.manage';

    public const BRANCH_STOCKTAKES_APPROVE = 'branch.stocktakes.approve';

    public const BRANCH_LOYALTY_VIEW = 'branch.loyalty.view';

    public const BRANCH_LOYALTY_MANAGE = 'branch.loyalty.manage';

    public const BRANCH_LOYALTY_APPROVE = 'branch.loyalty.approve';

    public const BRANCH_ASSETS_VIEW = 'branch.assets.view';

    public const BRANCH_ASSETS_MANAGE = 'branch.assets.manage';

    public const BRANCH_ASSETS_DISPOSE = 'branch.assets.dispose';

    public const PLATFORM_BRANCHES_VIEW = 'platform.branches.view';

    public const PLATFORM_BRANCHES_MANAGE = 'platform.branches.manage';

    public const PLATFORM_USERS_VIEW = 'platform.users.view';

    public const PLATFORM_USERS_MANAGE = 'platform.users.manage';

    public const PLATFORM_SETTINGS_MANAGE = 'platform.settings.manage';

    public const PLATFORM_AUDIT_VIEW = 'platform.audit.view';

    public const PLATFORM_REPORTS_VIEW = 'platform.reports.view';

    public const PLATFORM_CONTENT_MANAGE = 'platform.content.manage';

    /**
     * @return array<int, string>
     */
    public static function branch(): array
    {
        return [
            self::BRANCH_USERS_VIEW,
            self::BRANCH_USERS_MANAGE,
            self::BRANCH_ROLES_VIEW,
            self::BRANCH_SETTINGS_VIEW,
            self::BRANCH_SETTINGS_MANAGE,
            self::BRANCH_AUDIT_VIEW,
            self::BRANCH_REPORTS_VIEW,
            self::BRANCH_SALES_VIEW,
            self::BRANCH_SALES_CREATE,
            self::BRANCH_SALES_VOID,
            self::BRANCH_SALES_APPROVE_OVERRIDE,
            self::BRANCH_RESERVATIONS_VIEW,
            self::BRANCH_RESERVATIONS_MANAGE,
            self::BRANCH_INVENTORY_VIEW,
            self::BRANCH_INVENTORY_MANAGE,
            self::BRANCH_CUSTOMERS_VIEW,
            self::BRANCH_CUSTOMERS_MANAGE,
            self::BRANCH_PURCHASING_VIEW,
            self::BRANCH_PURCHASING_MANAGE,
            self::BRANCH_FINANCE_VIEW,
            self::BRANCH_FINANCE_MANAGE,
            self::BRANCH_HR_VIEW,
            self::BRANCH_HR_MANAGE,
            self::BRANCH_HR_SENSITIVE_VIEW,
            self::BRANCH_HR_LEAVE_APPROVE,
            self::BRANCH_PAYROLL_VIEW,
            self::BRANCH_PAYROLL_MANAGE,
            self::BRANCH_PAYROLL_APPROVE,
            self::BRANCH_PAYROLL_EXPORT,
            self::BRANCH_TASKS_VIEW,
            self::BRANCH_TASKS_MANAGE,
            self::BRANCH_CATALOGUE_VIEW,
            self::BRANCH_CATALOGUE_MANAGE,
            self::BRANCH_SERVICES_VIEW,
            self::BRANCH_SERVICES_MANAGE,
            self::BRANCH_JOBS_VIEW,
            self::BRANCH_JOBS_MANAGE,
            self::BRANCH_TRANSFERS_VIEW,
            self::BRANCH_TRANSFERS_MANAGE,
            self::BRANCH_STOCKTAKES_VIEW,
            self::BRANCH_STOCKTAKES_MANAGE,
            self::BRANCH_STOCKTAKES_APPROVE,
            self::BRANCH_LOYALTY_VIEW,
            self::BRANCH_LOYALTY_MANAGE,
            self::BRANCH_LOYALTY_APPROVE,
            self::BRANCH_ASSETS_VIEW,
            self::BRANCH_ASSETS_MANAGE,
            self::BRANCH_ASSETS_DISPOSE,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function platform(): array
    {
        return [
            self::PLATFORM_BRANCHES_VIEW,
            self::PLATFORM_BRANCHES_MANAGE,
            self::PLATFORM_USERS_VIEW,
            self::PLATFORM_USERS_MANAGE,
            self::PLATFORM_SETTINGS_MANAGE,
            self::PLATFORM_AUDIT_VIEW,
            self::PLATFORM_REPORTS_VIEW,
            self::PLATFORM_CONTENT_MANAGE,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function branchLabels(): array
    {
        return [
            self::BRANCH_USERS_VIEW => 'View branch users',
            self::BRANCH_USERS_MANAGE => 'Manage branch users',
            self::BRANCH_ROLES_VIEW => 'View branch roles',
            self::BRANCH_SETTINGS_VIEW => 'View branch settings',
            self::BRANCH_SETTINGS_MANAGE => 'Manage branch settings',
            self::BRANCH_AUDIT_VIEW => 'View branch audit log',
            self::BRANCH_REPORTS_VIEW => 'View branch reports',
            self::BRANCH_SALES_VIEW => 'View sales',
            self::BRANCH_SALES_CREATE => 'Create sales',
            self::BRANCH_SALES_VOID => 'Void sales',
            self::BRANCH_SALES_APPROVE_OVERRIDE => 'Approve discount and floor-price overrides',
            self::BRANCH_RESERVATIONS_VIEW => 'View online reservations',
            self::BRANCH_RESERVATIONS_MANAGE => 'Manage online reservations',
            self::BRANCH_INVENTORY_VIEW => 'View inventory',
            self::BRANCH_INVENTORY_MANAGE => 'Manage inventory',
            self::BRANCH_CUSTOMERS_VIEW => 'View customers',
            self::BRANCH_CUSTOMERS_MANAGE => 'Manage customers',
            self::BRANCH_PURCHASING_VIEW => 'View purchasing',
            self::BRANCH_PURCHASING_MANAGE => 'Manage purchasing',
            self::BRANCH_FINANCE_VIEW => 'View finance',
            self::BRANCH_FINANCE_MANAGE => 'Manage finance',
            self::BRANCH_HR_VIEW => 'View employee records',
            self::BRANCH_HR_MANAGE => 'Manage employee records',
            self::BRANCH_HR_SENSITIVE_VIEW => 'View sensitive employee data',
            self::BRANCH_HR_LEAVE_APPROVE => 'Approve leave requests',
            self::BRANCH_PAYROLL_VIEW => 'View payroll and payslips',
            self::BRANCH_PAYROLL_MANAGE => 'Manage payroll configuration and runs',
            self::BRANCH_PAYROLL_APPROVE => 'Approve and lock payroll runs',
            self::BRANCH_PAYROLL_EXPORT => 'Export payslips',
            self::BRANCH_TASKS_VIEW => 'View tasks and notifications',
            self::BRANCH_TASKS_MANAGE => 'Create and complete tasks',
            self::BRANCH_CATALOGUE_VIEW => 'View catalogue and fitment search',
            self::BRANCH_CATALOGUE_MANAGE => 'Import catalogue fitment/OEM data',
            self::BRANCH_SERVICES_VIEW => 'View service catalogue',
            self::BRANCH_SERVICES_MANAGE => 'Manage service catalogue',
            self::BRANCH_JOBS_VIEW => 'View job cards',
            self::BRANCH_JOBS_MANAGE => 'Manage job cards',
            self::BRANCH_TRANSFERS_VIEW => 'View stock transfers',
            self::BRANCH_TRANSFERS_MANAGE => 'Manage stock transfers',
            self::BRANCH_STOCKTAKES_VIEW => 'View stocktakes',
            self::BRANCH_STOCKTAKES_MANAGE => 'Manage stocktakes',
            self::BRANCH_STOCKTAKES_APPROVE => 'Approve stocktake adjustments',
            self::BRANCH_LOYALTY_VIEW => 'View loyalty qualification and win-back candidates',
            self::BRANCH_LOYALTY_MANAGE => 'Manage loyalty configuration and recalculation',
            self::BRANCH_LOYALTY_APPROVE => 'Approve combined loyalty and manual discounts',
            self::BRANCH_ASSETS_VIEW => 'View assets, maintenance and depreciation estimates',
            self::BRANCH_ASSETS_MANAGE => 'Acquire assets and record maintenance',
            self::BRANCH_ASSETS_DISPOSE => 'Dispose of assets and record financial effects',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function platformLabels(): array
    {
        return [
            self::PLATFORM_BRANCHES_VIEW => 'View all branches',
            self::PLATFORM_BRANCHES_MANAGE => 'Manage branches',
            self::PLATFORM_USERS_VIEW => 'View platform users',
            self::PLATFORM_USERS_MANAGE => 'Manage platform users',
            self::PLATFORM_SETTINGS_MANAGE => 'Manage platform settings',
            self::PLATFORM_AUDIT_VIEW => 'View platform audit log',
            self::PLATFORM_REPORTS_VIEW => 'View platform reports',
            self::PLATFORM_CONTENT_MANAGE => 'Manage public website content',
        ];
    }

    public static function isBranch(string $permission): bool
    {
        return in_array($permission, self::branch(), true);
    }

    public static function isPlatform(string $permission): bool
    {
        return in_array($permission, self::platform(), true);
    }
}
