<?php
return $GLOBALS['API_ROUTES'] = [
    'POST /auth/login' => ['handler' => 'auth.login', 'public' => true, 'perm' => null],
    'POST /auth/logout' => ['handler' => 'auth.logout', 'public' => false, 'perm' => null],
    'GET /auth/me' => ['handler' => 'auth.me', 'public' => false, 'perm' => null],

    'GET /dashboard/stats' => ['handler' => 'dashboard.stats', 'public' => false, 'perm' => 'view_dashboard'],
    'GET /dashboard/recent_activity' => ['handler' => 'dashboard.activity', 'public' => false, 'perm' => 'view_dashboard'],

    'GET /bookings' => ['handler' => 'bookings.list', 'public' => false, 'perm' => 'view_bookings'],
    'GET /bookings/:id' => ['handler' => 'bookings.detail', 'public' => false, 'perm' => 'view_bookings'],
    'POST /bookings' => ['handler' => 'bookings.create', 'public' => false, 'perm' => 'create_bookings'],
    'PUT /bookings/:id' => ['handler' => 'bookings.update', 'public' => false, 'perm' => 'edit_bookings'],
    'POST /bookings/:id/cancel' => ['handler' => 'bookings.cancel', 'public' => false, 'perm' => 'cancel_bookings'],
    'POST /bookings/:id/status' => ['handler' => 'bookings.status', 'public' => false, 'perm' => 'edit_bookings'],
    'POST /bookings/:id/regenerate_token' => ['handler' => 'bookings.regenerate_token', 'public' => false, 'perm' => 'edit_bookings'],
    'GET /bookings/:id/invoice' => ['handler' => 'bookings.invoice', 'public' => false, 'perm' => 'view_bookings'],

    'GET /calendar' => ['handler' => 'calendar.list', 'public' => false, 'perm' => 'view_calendar'],
    'GET /calendar/download/:id' => ['handler' => 'calendar.download', 'public' => false, 'perm' => 'view_calendar'],

    'GET /clients' => ['handler' => 'clients.list', 'public' => false, 'perm' => 'view_clients'],
    'GET /clients/:id' => ['handler' => 'clients.detail', 'public' => false, 'perm' => 'view_clients'],
    'POST /clients' => ['handler' => 'clients.create', 'public' => false, 'perm' => 'manage_clients'],
    'PUT /clients/:id' => ['handler' => 'clients.update', 'public' => false, 'perm' => 'manage_clients'],
    'DELETE /clients/:id' => ['handler' => 'clients.delete', 'public' => false, 'perm' => 'manage_clients'],
    'GET /clients/:id/statement' => ['handler' => 'clients.statement', 'public' => false, 'perm' => 'view_financials'],

    'GET /categories' => ['handler' => 'inventory.categories_list', 'public' => false, 'perm' => null],
    'POST /categories' => ['handler' => 'inventory.categories_create', 'public' => false, 'perm' => 'manage_setup'],
    'PUT /categories/:id' => ['handler' => 'inventory.categories_update', 'public' => false, 'perm' => 'manage_setup'],
    'DELETE /categories/:id' => ['handler' => 'inventory.categories_delete', 'public' => false, 'perm' => 'manage_setup'],

    'GET /item-types' => ['handler' => 'inventory.item_types_list', 'public' => false, 'perm' => null],
    'POST /item-types' => ['handler' => 'inventory.item_types_create', 'public' => false, 'perm' => 'manage_setup'],
    'PUT /item-types/:id' => ['handler' => 'inventory.item_types_update', 'public' => false, 'perm' => 'manage_setup'],
    'DELETE /item-types/:id' => ['handler' => 'inventory.item_types_delete', 'public' => false, 'perm' => 'manage_setup'],
    'GET /item-types/:id/availability' => ['handler' => 'inventory.item_type_availability', 'public' => false, 'perm' => null],
    'GET /availability/item-types' => ['handler' => 'inventory.item_types_availability_bulk', 'public' => false, 'perm' => null],

    'GET /inventory-items' => ['handler' => 'inventory.items_list', 'public' => false, 'perm' => 'manage_inventory'],
    'POST /inventory-items' => ['handler' => 'inventory.items_create', 'public' => false, 'perm' => 'manage_inventory'],
    'PUT /inventory-items/:id' => ['handler' => 'inventory.items_update', 'public' => false, 'perm' => 'manage_inventory'],
    'DELETE /inventory-items/:id' => ['handler' => 'inventory.items_delete', 'public' => false, 'perm' => 'manage_inventory'],

    'GET /expense-types' => ['handler' => 'expenses.types_list', 'public' => false, 'perm' => null],
    'POST /expense-types' => ['handler' => 'expenses.types_create', 'public' => false, 'perm' => 'manage_setup'],
    'PUT /expense-types/:id' => ['handler' => 'expenses.types_update', 'public' => false, 'perm' => 'manage_setup'],
    'DELETE /expense-types/:id' => ['handler' => 'expenses.types_delete', 'public' => false, 'perm' => 'manage_setup'],

    'GET /expenses' => ['handler' => 'expenses.list', 'public' => false, 'perm' => 'view_expenses'],
    'POST /expenses' => ['handler' => 'expenses.create', 'public' => false, 'perm' => 'manage_expenses'],
    'PUT /expenses/:id' => ['handler' => 'expenses.update', 'public' => false, 'perm' => 'manage_expenses'],
    'DELETE /expenses/:id' => ['handler' => 'expenses.delete', 'public' => false, 'perm' => 'manage_expenses'],

    'GET /payments' => ['handler' => 'payments.list', 'public' => false, 'perm' => 'view_bookings'],
    'POST /payments' => ['handler' => 'payments.create', 'public' => false, 'perm' => 'record_payments'],
    'DELETE /payments/:id' => ['handler' => 'payments.delete', 'public' => false, 'perm' => 'record_payments'],

    'GET /reports/bookings' => ['handler' => 'reports.bookings', 'public' => false, 'perm' => 'view_reports'],
    'GET /reports/bookings/export/csv' => ['handler' => 'reports.bookings_csv', 'public' => false, 'perm' => 'view_reports'],
    'GET /reports/financial-summary' => ['handler' => 'reports.financial', 'public' => false, 'perm' => 'view_financials'],
    'GET /reports/expenses' => ['handler' => 'reports.expenses', 'public' => false, 'perm' => 'view_financials'],
    'GET /reports/expenses/export/csv' => ['handler' => 'reports.expenses_csv', 'public' => false, 'perm' => 'view_financials'],
    'GET /reports/inventory' => ['handler' => 'reports.inventory', 'public' => false, 'perm' => 'manage_inventory'],
    'GET /reports/client-statement/:id' => ['handler' => 'reports.client_statement', 'public' => false, 'perm' => 'view_financials'],

    'GET /users' => ['handler' => 'users.list', 'public' => false, 'perm' => 'manage_users'],
    'GET /users/:id' => ['handler' => 'users.detail', 'public' => false, 'perm' => 'manage_users'],
    'POST /users' => ['handler' => 'users.create', 'public' => false, 'perm' => 'manage_users'],
    'PUT /users/:id' => ['handler' => 'users.update', 'public' => false, 'perm' => 'manage_users'],
    'POST /users/:id/deactivate' => ['handler' => 'users.deactivate', 'public' => false, 'perm' => 'manage_users'],
    'GET /roles' => ['handler' => 'users.roles', 'public' => false, 'perm' => 'manage_users'],
    'GET /permissions' => ['handler' => 'users.permissions', 'public' => false, 'perm' => 'manage_users'],
    'GET /users/:id/permissions' => ['handler' => 'users.user_permissions', 'public' => false, 'perm' => 'manage_users'],

    'GET /settings' => ['handler' => 'settings.get', 'public' => false, 'perm' => 'manage_settings'],
    'PUT /settings' => ['handler' => 'settings.put', 'public' => false, 'perm' => 'manage_settings'],

    'GET /profile' => ['handler' => 'profile.get', 'public' => false, 'perm' => null],
    'PUT /profile' => ['handler' => 'profile.put', 'public' => false, 'perm' => null],
    'POST /profile/password' => ['handler' => 'profile.password', 'public' => false, 'perm' => null],

    'GET /public/confirm/:token' => ['handler' => 'public.confirm_get', 'public' => true, 'perm' => null],
    'POST /public/confirm/:token' => ['handler' => 'public.confirm_post', 'public' => true, 'perm' => null],

    'GET /search' => ['handler' => 'misc.search', 'public' => false, 'perm' => null],
    'GET /audit-logs' => ['handler' => 'misc.audit_logs', 'public' => false, 'perm' => 'view_audit_logs'],
    'GET /i18n/:lang' => ['handler' => 'misc.i18n_dict', 'public' => true, 'perm' => null],
    'POST /i18n/set' => ['handler' => 'misc.i18n_set', 'public' => false, 'perm' => null],
];
