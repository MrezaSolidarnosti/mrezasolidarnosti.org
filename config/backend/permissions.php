<?php
/**
 * Permission-based authorization configuration
 * Maps permissions to roles and routes to permissions
 */

use Solidarity\User\Entity\User;

return [
    // Permission to role mappings
    'permissions' => [
        // User permissions
        'user.view_list' => [User::ROLE_ADMIN],
        'user.view' => [User::ROLE_ADMIN],
        'user.create' => [User::ROLE_ADMIN],
        'user.edit' => [User::ROLE_ADMIN],
        'user.delete' => [User::ROLE_ADMIN],

        // Delegate permissions
        'delegate.view_list' => [User::ROLE_ADMIN, User::ROLE_STUFF, 10],
        'delegate.view' => [User::ROLE_ADMIN, User::ROLE_STUFF, 10],
        'delegate.create' => [User::ROLE_ADMIN, User::ROLE_STUFF],
        'delegate.edit' => [User::ROLE_ADMIN, User::ROLE_STUFF, 10],
        'delegate.delete' => [User::ROLE_ADMIN],

        // Donor permissions
        'donor.view_list' => [User::ROLE_ADMIN, User::ROLE_STUFF],
        'donor.view' => [User::ROLE_ADMIN, User::ROLE_STUFF],
        'donor.create' => [User::ROLE_ADMIN, User::ROLE_STUFF],
        'donor.edit' => [User::ROLE_ADMIN, User::ROLE_STUFF],
        'donor.delete' => [User::ROLE_ADMIN],

        // Educator permissions
        'educator.view_list' => [User::ROLE_ADMIN, User::ROLE_STUFF, 10],
        'educator.view' => [User::ROLE_ADMIN, User::ROLE_STUFF, 10],
        'educator.create' => [User::ROLE_ADMIN, User::ROLE_STUFF, 10],
        'educator.edit' => [User::ROLE_ADMIN, User::ROLE_STUFF, 10],
        'educator.delete' => [User::ROLE_ADMIN],

        // beneficiary permissions
        'beneficiary.view_list' => [User::ROLE_ADMIN, User::ROLE_STUFF, 10],
        'beneficiary.view' => [User::ROLE_ADMIN, User::ROLE_STUFF, 10],
        'beneficiary.create' => [User::ROLE_ADMIN, User::ROLE_STUFF, 10],
        'beneficiary.edit' => [User::ROLE_ADMIN, User::ROLE_STUFF, 10],
        'beneficiary.delete' => [User::ROLE_ADMIN],

        // Transaction permissions
        'transaction.view_list' => [User::ROLE_ADMIN, User::ROLE_STUFF, 10],
        'transaction.view' => [User::ROLE_ADMIN, User::ROLE_STUFF, 10],
        'transaction.create' => [User::ROLE_ADMIN, User::ROLE_STUFF],
        'transaction.edit' => [User::ROLE_ADMIN, User::ROLE_STUFF, 10],
        'transaction.delete' => [User::ROLE_ADMIN],

        // School/City permissions
        'school.manage' => [User::ROLE_ADMIN],
        'schoolType.manage' => [User::ROLE_ADMIN],
        'project.manage' => [User::ROLE_ADMIN],
        'city.manage' => [User::ROLE_ADMIN],

        // Import permissions
        'import.educator' => [User::ROLE_ADMIN],
        'import.transaction' => [User::ROLE_ADMIN],

        // System permissions
        'cache.manage' => [User::ROLE_ADMIN],
        'period.manage' => [User::ROLE_ADMIN],
        'template.manage' => [User::ROLE_ADMIN],
        'translator.manage' => [User::ROLE_ADMIN],
        'activity.view' => [User::ROLE_ADMIN],
        'createTransactions' => [User::ROLE_ADMIN],
    ],

    // Route to permission mappings
    'routes' => [
        // User routes
        '/user/view/' => 'user.view_list',
        '/user/tableHandler/' => 'user.view_list',
        '/user/form/' => 'user.create',
        '/user/create/' => 'user.create',
        '/user/form/*' => 'user.edit',
        '/user/update/*' => 'user.edit',
        '/user/delete/*' => 'user.delete',

        // Delegate routes
        '/delegate/view/' => 'delegate.view_list',
        '/delegate/view/*' => 'delegate.view',
        '/delegate/tableHandler/' => 'delegate.view_list',
        '/delegate/create/' => 'delegate.create',
        '/delegate/form/' => 'delegate.create',
        '/delegate/form/*/' => 'delegate.edit',
        '/delegate/update/*' => 'delegate.edit',
        '/delegate/delete/*' => 'delegate.delete',

        // Donor routes
        '/donor/view/' => 'donor.view',
        '/donor/view/*' => 'donor.view',
        '/donor/tableHandler/' => 'donor.view_list',
        '/donor/create/' => 'donor.create',
        '/donor/form/' => 'donor.create',
        '/donor/form/*' => 'donor.edit',
        '/donor/update/*' => 'donor.edit',
        '/donor/delete/*' => 'donor.delete',

        // beneficiary routes
        '/beneficiary/view/' => 'beneficiary.view_list',
        '/beneficiary/view/*' => 'beneficiary.view',
        '/beneficiary/tableHandler/' => 'beneficiary.view_list',
        '/beneficiary/create/' => 'beneficiary.create',
        '/beneficiary/form/' => 'beneficiary.create',
        '/beneficiary/form/*' => 'beneficiary.edit',
        '/beneficiary/update/*' => 'beneficiary.edit',
        '/beneficiary/delete/*' => 'beneficiary.delete',

        // Transaction routes
        '/transaction/view/' => 'transaction.view',
        '/transaction/view/*' => 'transaction.view',
        '/transaction/tableHandler/' => 'transaction.view_list',
        '/transaction/create/' => 'transaction.create',
        '/transaction/form/' => 'transaction.create',
        '/transaction/form/*' => 'transaction.edit',
        '/transaction/updateStatus/*' => 'transaction.edit',
        '/transaction/update/*' => 'transaction.edit',
        '/transaction/updateStatusBulk*' => 'transaction.edit',
        '/transaction/delete/*' => 'transaction.delete',

        // School/City routes
        '/school/*' => 'school.manage',
        '/period/*' => 'period.manage',
        '/schoolType/*' => 'schoolType.manage',
        '/city/*' => 'city.manage',

        // Import routes
        '/educatorImport/*' => 'import.educator',
        '/transactionImport/*' => 'import.transaction',

        // System routes
        '/cache/*' => 'cache.manage',
        '/template/*' => 'template.manage',
        '/translator/*' => 'translator.manage',
        '/activity/*' => 'activity.view',
        '/createTransactions' => 'createTransactions',
    ],

    // Role hierarchy (roles inherit permissions from other roles)
    'roles' => [
        User::ROLE_ADMIN => [10], // Admin inherits all delegate permissions
        10 => [], // Delegate is base role
    ],
];
