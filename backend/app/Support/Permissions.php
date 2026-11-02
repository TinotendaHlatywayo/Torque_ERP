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

    public const BRANCH_INVENTORY_VIEW = 'branch.inventory.view';

    public const BRANCH_CUSTOMERS_VIEW = 'branch.customers.view';

    public const BRANCH_CUSTOMERS_MANAGE = 'branch.customers.manage';

    public const BRANCH_INVENTORY_MANAGE = 'branch.inventory.manage';

    public const BRANCH_PURCHASING_VIEW = 'branch.purchasing.view';

    public const BRANCH_PURCHASING_MANAGE = 'branch.purchasing.manage';

    public const BRANCH_FINANCE_VIEW = 'branch.finance.view';

    public const BRANCH_FINANCE_MANAGE = 'branch.finance.manage';

    public const PLATFORM_BRANCHES_VIEW = 'platform.branches.view';

    public const PLATFORM_BRANCHES_MANAGE = 'platform.branches.manage';

    public const PLATFORM_USERS_VIEW = 'platform.users.view';

    public const PLATFORM_USERS_MANAGE = 'platform.users.manage';

    public const PLATFORM_SETTINGS_MANAGE = 'platform.settings.manage';

    public const PLATFORM_AUDIT_VIEW = 'platform.audit.view';

    public const PLATFORM_REPORTS_VIEW = 'platform.reports.view';

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
            self::BRANCH_INVENTORY_VIEW,
            self::BRANCH_INVENTORY_MANAGE,
            self::BRANCH_CUSTOMERS_VIEW,
            self::BRANCH_CUSTOMERS_MANAGE,
            self::BRANCH_PURCHASING_VIEW,
            self::BRANCH_PURCHASING_MANAGE,
            self::BRANCH_FINANCE_VIEW,
            self::BRANCH_FINANCE_MANAGE,
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
            self::BRANCH_INVENTORY_VIEW => 'View inventory',
            self::BRANCH_INVENTORY_MANAGE => 'Manage inventory',
            self::BRANCH_CUSTOMERS_VIEW => 'View customers',
            self::BRANCH_CUSTOMERS_MANAGE => 'Manage customers',
            self::BRANCH_PURCHASING_VIEW => 'View purchasing',
            self::BRANCH_PURCHASING_MANAGE => 'Manage purchasing',
            self::BRANCH_FINANCE_VIEW => 'View finance',
            self::BRANCH_FINANCE_MANAGE => 'Manage finance',
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
