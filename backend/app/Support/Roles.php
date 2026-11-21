<?php

namespace App\Support;

final class Roles
{
    public const ADMIN = 'admin';

    public const MANAGER = 'manager';

    public const STOREKEEPER = 'storekeeper';

    public const CASHIER = 'cashier';

    public const ACCOUNTANT = 'accountant';

    public const VIEWER = 'viewer';

    /**
     * @return array<int, string>
     */
    public static function names(): array
    {
        return [
            self::ADMIN,
            self::MANAGER,
            self::STOREKEEPER,
            self::CASHIER,
            self::ACCOUNTANT,
            self::VIEWER,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::ADMIN => 'Branch administrator',
            self::MANAGER => 'Branch manager',
            self::STOREKEEPER => 'Storekeeper',
            self::CASHIER => 'Cashier',
            self::ACCOUNTANT => 'Accountant',
            self::VIEWER => 'Read-only viewer',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function permissions(string $role): array
    {
        return match ($role) {
            self::ADMIN => Permissions::branch(),
            self::MANAGER => [
                Permissions::BRANCH_USERS_VIEW,
                Permissions::BRANCH_ROLES_VIEW,
                Permissions::BRANCH_SETTINGS_VIEW,
                Permissions::BRANCH_SETTINGS_MANAGE,
                Permissions::BRANCH_AUDIT_VIEW,
                Permissions::BRANCH_REPORTS_VIEW,
                Permissions::BRANCH_SALES_VIEW,
                Permissions::BRANCH_SALES_CREATE,
                Permissions::BRANCH_SALES_VOID,
                Permissions::BRANCH_SALES_APPROVE_OVERRIDE,
                Permissions::BRANCH_RESERVATIONS_VIEW,
                Permissions::BRANCH_RESERVATIONS_MANAGE,
                Permissions::BRANCH_INVENTORY_VIEW,
                Permissions::BRANCH_INVENTORY_MANAGE,
                Permissions::BRANCH_CUSTOMERS_VIEW,
                Permissions::BRANCH_CUSTOMERS_MANAGE,
                Permissions::BRANCH_PURCHASING_VIEW,
                Permissions::BRANCH_PURCHASING_MANAGE,
                Permissions::BRANCH_FINANCE_VIEW,
                Permissions::BRANCH_HR_VIEW,
                Permissions::BRANCH_HR_MANAGE,
                Permissions::BRANCH_HR_SENSITIVE_VIEW,
                Permissions::BRANCH_HR_LEAVE_APPROVE,
                Permissions::BRANCH_PAYROLL_VIEW,
                Permissions::BRANCH_PAYROLL_MANAGE,
                Permissions::BRANCH_PAYROLL_APPROVE,
                Permissions::BRANCH_PAYROLL_EXPORT,
                Permissions::BRANCH_TASKS_VIEW,
                Permissions::BRANCH_TASKS_MANAGE,
                Permissions::BRANCH_CATALOGUE_VIEW,
                Permissions::BRANCH_CATALOGUE_MANAGE,
                Permissions::BRANCH_SERVICES_VIEW,
                Permissions::BRANCH_SERVICES_MANAGE,
                Permissions::BRANCH_JOBS_VIEW,
                Permissions::BRANCH_JOBS_MANAGE,
                Permissions::BRANCH_TRANSFERS_VIEW,
                Permissions::BRANCH_TRANSFERS_MANAGE,
                Permissions::BRANCH_STOCKTAKES_VIEW,
                Permissions::BRANCH_STOCKTAKES_MANAGE,
                Permissions::BRANCH_STOCKTAKES_APPROVE,
                Permissions::BRANCH_LOYALTY_VIEW,
                Permissions::BRANCH_LOYALTY_MANAGE,
                Permissions::BRANCH_LOYALTY_APPROVE,
                Permissions::BRANCH_ASSETS_VIEW,
                Permissions::BRANCH_ASSETS_MANAGE,
            ],
            self::STOREKEEPER => [
                Permissions::BRANCH_SALES_VIEW,
                Permissions::BRANCH_INVENTORY_VIEW,
                Permissions::BRANCH_INVENTORY_MANAGE,
                Permissions::BRANCH_CUSTOMERS_VIEW,
                Permissions::BRANCH_CUSTOMERS_MANAGE,
                Permissions::BRANCH_PURCHASING_VIEW,
                Permissions::BRANCH_PURCHASING_MANAGE,
                Permissions::BRANCH_TASKS_VIEW,
                Permissions::BRANCH_TASKS_MANAGE,
                Permissions::BRANCH_CATALOGUE_VIEW,
                Permissions::BRANCH_CATALOGUE_MANAGE,
                Permissions::BRANCH_SERVICES_VIEW,
                Permissions::BRANCH_JOBS_VIEW,
                Permissions::BRANCH_JOBS_MANAGE,
                Permissions::BRANCH_TRANSFERS_VIEW,
                Permissions::BRANCH_TRANSFERS_MANAGE,
                Permissions::BRANCH_STOCKTAKES_VIEW,
                Permissions::BRANCH_STOCKTAKES_MANAGE,
                Permissions::BRANCH_ASSETS_VIEW,
            ],
            self::CASHIER => [
                Permissions::BRANCH_SALES_VIEW,
                Permissions::BRANCH_SALES_CREATE,
                Permissions::BRANCH_RESERVATIONS_VIEW,
                Permissions::BRANCH_INVENTORY_VIEW,
                Permissions::BRANCH_CUSTOMERS_VIEW,
                Permissions::BRANCH_CUSTOMERS_MANAGE,
                Permissions::BRANCH_TASKS_VIEW,
                Permissions::BRANCH_CATALOGUE_VIEW,
                Permissions::BRANCH_SERVICES_VIEW,
                Permissions::BRANCH_JOBS_VIEW,
                Permissions::BRANCH_TRANSFERS_VIEW,
                Permissions::BRANCH_STOCKTAKES_VIEW,
            ],
            self::ACCOUNTANT => [
                Permissions::BRANCH_SALES_VIEW,
                Permissions::BRANCH_PURCHASING_VIEW,
                Permissions::BRANCH_FINANCE_VIEW,
                Permissions::BRANCH_FINANCE_MANAGE,
                Permissions::BRANCH_CUSTOMERS_VIEW,
                Permissions::BRANCH_REPORTS_VIEW,
                Permissions::BRANCH_AUDIT_VIEW,
                Permissions::BRANCH_PAYROLL_VIEW,
                Permissions::BRANCH_PAYROLL_MANAGE,
                Permissions::BRANCH_PAYROLL_EXPORT,
                Permissions::BRANCH_JOBS_VIEW,
                Permissions::BRANCH_TRANSFERS_VIEW,
                Permissions::BRANCH_STOCKTAKES_VIEW,
                Permissions::BRANCH_ASSETS_VIEW,
                Permissions::BRANCH_ASSETS_DISPOSE,
            ],
            self::VIEWER => [
                Permissions::BRANCH_SALES_VIEW,
                Permissions::BRANCH_RESERVATIONS_VIEW,
                Permissions::BRANCH_INVENTORY_VIEW,
                Permissions::BRANCH_CUSTOMERS_VIEW,
                Permissions::BRANCH_PURCHASING_VIEW,
                Permissions::BRANCH_FINANCE_VIEW,
                Permissions::BRANCH_REPORTS_VIEW,
                Permissions::BRANCH_TASKS_VIEW,
                Permissions::BRANCH_CATALOGUE_VIEW,
                Permissions::BRANCH_SERVICES_VIEW,
                Permissions::BRANCH_JOBS_VIEW,
                Permissions::BRANCH_TRANSFERS_VIEW,
                Permissions::BRANCH_STOCKTAKES_VIEW,
                Permissions::BRANCH_ASSETS_VIEW,
            ],
            default => [],
        };
    }

    public static function exists(string $role): bool
    {
        return in_array($role, self::names(), true);
    }

    /**
     * @return array<int, array{name: string, label: string, permissions: array<int, string>}>
     */
    public static function catalogue(): array
    {
        $labels = self::labels();

        return array_map(
            fn (string $role): array => [
                'name' => $role,
                'label' => $labels[$role],
                'permissions' => self::permissions($role),
            ],
            self::names(),
        );
    }
}
