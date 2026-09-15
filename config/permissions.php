<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Role & Permission Matrix
    |--------------------------------------------------------------------------
    |
    | Setiap role memiliki daftar permission tetap ("matriks permission per
    | role"). Role dengan permissions = ['*'] memiliki akses penuh.
    |
    */

    'roles' => [
        'super_admin' => [
            'label' => 'Super Admin',
            'permissions' => ['*'],
        ],

        // Role lama (kompatibilitas)
        'admin' => [
            'label' => 'Admin',
            'permissions' => ['*'],
        ],

        'general_manager' => [
            'label' => 'General Manager',
            'permissions' => [
                'dashboard.view',
                'rooms.view', 'room_types.view',
                'guests.view',
                'reservations.view',
                'payments.view', 'charges.view',
                'housekeeping.view',
                'reports.view', 'reports.revenue', 'reports.occupancy', 'reports.export',
                'logs.view',
                'fb.view', 'purchasing.view', 'suppliers.view', 'inventory.view',
                'engineering.view', 'hr.view',
                'events.view', 'events.create', 'events.update', 'events.delete',
                'events.status', 'events.payment', 'venues.manage', 'ayce.manage',
                'approval.approve',
            ],
        ],

        // Role lama (kompatibilitas)
        'manager' => [
            'label' => 'Manager',
            'permissions' => [
                'dashboard.view',
                'rooms.view', 'room_types.view',
                'guests.view',
                'reservations.view',
                'payments.view', 'charges.view',
                'housekeeping.view',
                'reports.view', 'reports.revenue', 'reports.occupancy', 'reports.export',
                'logs.view',
                'fb.view', 'purchasing.view', 'suppliers.view', 'inventory.view',
                'engineering.view', 'hr.view',
                'events.view', 'events.create', 'events.update', 'events.delete',
                'events.status', 'events.payment', 'venues.manage', 'ayce.manage',
                'approval.approve',
            ],
        ],

        'front_office' => [
            'label' => 'Front Office',
            'permissions' => [
                'dashboard.view',
                'rooms.view', 'rooms.status',
                'room_types.view',
                'guests.view', 'guests.create', 'guests.update',
                'reservations.view', 'reservations.create', 'reservations.update',
                'reservations.assign', 'reservations.confirm', 'reservations.checkin',
                'reservations.checkout', 'reservations.cancel',
                'payments.view', 'payments.create',
                'charges.view', 'charges.create',
                'housekeeping.view',
                'events.view',
            ],
        ],

        // Role lama (kompatibilitas)
        'receptionist' => [
            'label' => 'Resepsionis',
'permissions' => [
                'dashboard.view',
                'rooms.view', 'rooms.status',
                'room_types.view',
                'guests.view', 'guests.create', 'guests.update',
                'reservations.view', 'reservations.create', 'reservations.update',
                'reservations.assign', 'reservations.confirm', 'reservations.checkin',
                'reservations.checkout', 'reservations.cancel',
                'payments.view', 'payments.create',
                'charges.view', 'charges.create',
                'housekeeping.view',
            ],
        ],

        // Role lama (kompatibilitas)
        'reservation_staff' => [
            'label' => 'Reservation Staff',
            'permissions' => [
                'dashboard.view',
                'rooms.view',
                'room_types.view',
                'guests.view', 'guests.create', 'guests.update',
                'reservations.view', 'reservations.create', 'reservations.update',
                'reservations.assign', 'reservations.confirm', 'reservations.cancel',
                'payments.view',
                'events.view',
            ],
        ],

        'housekeeping' => [
            'label' => 'Housekeeping',
            'permissions' => [
                'dashboard.view',
                'rooms.view', 'rooms.status',
                'housekeeping.view', 'housekeeping.create', 'housekeeping.update',
                'housekeeping.status',
                'engineering.view', 'engineering.create',
            ],
        ],

        'finance' => [
            'label' => 'Finance / Accounting',
            'permissions' => [
                'dashboard.view',
                'guests.view', 'reservations.view',
                'payments.view', 'payments.create', 'payments.delete',
                'charges.view', 'charges.void',
                'reports.view', 'reports.revenue', 'reports.export',
                'events.view', 'events.payment',
            ],
        ],

        'fb_staff' => [
            'label' => 'F&B Staff',
            'permissions' => [
                'dashboard.view',
                'rooms.view',
                'fb.view', 'fb.create', 'fb.update', 'fb.status',
                'charges.create',
            ],
        ],

        'fb_manager' => [
            'label' => 'F&B Manager',
            'permissions' => [
                'dashboard.view',
                'fb.view', 'fb.create', 'fb.update', 'fb.delete', 'fb.status',
                'reports.view', 'reports.revenue', 'reports.export',
                'inventory.view', 'purchasing.view', 'suppliers.view',
                'charges.create',
            ],
        ],

        'purchasing' => [
            'label' => 'Purchasing',
            'permissions' => [
                'dashboard.view',
                'purchasing.view', 'purchasing.create', 'purchasing.update',
                'purchasing.delete', 'purchasing.status',
                'suppliers.view', 'suppliers.manage',
                'inventory.view',
            ],
        ],

        'inventory' => [
            'label' => 'Inventory / Storekeeper',
            'permissions' => [
                'dashboard.view',
                'inventory.view', 'inventory.create', 'inventory.update',
                'inventory.delete', 'inventory.transfer',
                'purchasing.view',
                'fb.view',
            ],
        ],

        'engineering' => [
            'label' => 'Engineering / Maintenance',
            'permissions' => [
                'dashboard.view',
                'rooms.view', 'rooms.status',
                'engineering.view', 'engineering.create', 'engineering.update',
                'engineering.delete', 'engineering.status',
                'housekeeping.view',
            ],
        ],

        'hrd' => [
            'label' => 'HR / HRD',
            'permissions' => [
                'dashboard.view',
                'hr.view', 'hr.create', 'hr.update', 'hr.delete',
            ],
        ],

        'supervisor' => [
            'label' => 'Manager / Supervisor',
            'permissions' => [
                'dashboard.view',
                'rooms.view', 'guests.view', 'reservations.view',
                'housekeeping.view', 'engineering.view',
                'fb.view', 'purchasing.view', 'suppliers.view', 'inventory.view',
                'hr.view',
                'events.view', 'events.create', 'events.update', 'events.status',
                'venues.manage', 'ayce.manage',
                'reports.view', 'reports.revenue', 'reports.occupancy',
                'logs.view',
                'approval.approve',
            ],
        ],

        'guest' => [
            'label' => 'Guest / Customer',
            'permissions' => [
                'dashboard.view',
                'reservations.own',
            ],
        ],
    ],
];