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
        'donor.delete_bulk' => [User::ROLE_ADMIN],

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
        'school.view' => [User::ROLE_ADMIN, User::ROLE_STUFF, 10],
        'school.manage' => [User::ROLE_ADMIN],
        'schoolType.manage' => [User::ROLE_ADMIN],
        'project.manage' => [User::ROLE_ADMIN],
        'city.manage' => [User::ROLE_ADMIN],

        // Email list permissions
        'emailList.view_list' => [User::ROLE_ADMIN],
        'emailList.view' => [User::ROLE_ADMIN],
        'emailList.create' => [User::ROLE_ADMIN],
        'emailList.edit' => [User::ROLE_ADMIN],
        'emailList.delete' => [User::ROLE_ADMIN],

        // Page permissions
        'page.view_list' => [User::ROLE_ADMIN],
        'page.view' => [User::ROLE_ADMIN],
        'page.create' => [User::ROLE_ADMIN],
        'page.edit' => [User::ROLE_ADMIN],
        'page.delete' => [User::ROLE_ADMIN],
        'page.create_translation' => [User::ROLE_ADMIN],

        // Post permissions
        'post.view_list' => [User::ROLE_ADMIN],
        'post.view' => [User::ROLE_ADMIN],
        'post.create' => [User::ROLE_ADMIN],
        'post.edit' => [User::ROLE_ADMIN],
        'post.delete' => [User::ROLE_ADMIN],
        'post.create_translation' => [User::ROLE_ADMIN],

        // Image permissions
        'image.view_list' => [User::ROLE_ADMIN],
        'image.view' => [User::ROLE_ADMIN],
        'image.create' => [User::ROLE_ADMIN],
        'image.edit' => [User::ROLE_ADMIN],
        'image.delete' => [User::ROLE_ADMIN],

        // File permissions
        'file.view_list' => [User::ROLE_ADMIN],
        'file.view' => [User::ROLE_ADMIN],
        'file.create' => [User::ROLE_ADMIN],
        'file.edit' => [User::ROLE_ADMIN],
        'file.delete' => [User::ROLE_ADMIN],

        // Theme settings permissions
        'theme.all' => [User::ROLE_ADMIN],

        // Navigation settings permissions
        'navigation.all' => [User::ROLE_ADMIN],

        // Social settings permissions
        'social.all' => [User::ROLE_ADMIN],


        // System permissions
        'cache.manage' => [User::ROLE_ADMIN],
        'period.manage' => [User::ROLE_ADMIN],
        'template.manage' => [User::ROLE_ADMIN],
        'translator.manage' => [User::ROLE_ADMIN],
        'activity.view' => [User::ROLE_ADMIN],
        'createTransactions' => [User::ROLE_ADMIN],
        'statistics.view' => [User::ROLE_ADMIN, User::ROLE_STUFF],
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
        '/donor/deleteBulk/' => 'donor.delete_bulk',

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
        '/transaction/getPaymentMethodPreview/' => 'transaction.create',
        '/transaction/getEntityData/*' => 'transaction.view',
        '/transaction/delete/*' => 'transaction.delete',

        // School/City routes
        '/school/view/' => 'school.view',
        '/school/view/*' => 'school.view',
        '/school/tableHandler/' => 'school.view',
        '/school/form/*' => 'school.view',
        '/school/*' => 'school.manage',
        '/period/*' => 'period.manage',
        '/schoolType/*' => 'schoolType.manage',
        '/city/*' => 'city.manage',

        // Page routes
        '/page/view/' => 'page.view_list',
        '/page/view/*' => 'page.view',
        '/page/tableHandler/' => 'page.view_list',
        '/page/create/' => 'page.create',
        '/page/form/' => 'page.create',
        '/page/form/*' => 'page.edit',
        '/page/update/*' => 'page.edit',
        '/page/delete/*' => 'page.delete',
        '/page/createTranslation/*' => 'page.create_translation',

        // Post routes
        '/post/view/' => 'post.view_list',
        '/post/view/*' => 'post.view',
        '/post/tableHandler/' => 'post.view_list',
        '/post/create/' => 'post.create',
        '/post/form/' => 'post.create',
        '/post/form/*' => 'post.edit',
        '/post/update/*' => 'post.edit',
        '/post/delete/*' => 'post.delete',

        // Email list routes
        '/emails/view/' => 'emailList.view_list',
        '/emails/view/*' => 'emailList.view',
        '/emails/tableHandler/' => 'emailList.view_list',
        '/emails/create/' => 'emailList.create',
        '/emails/form/' => 'emailList.create',
        '/emails/form/*' => 'emailList.edit',
        '/emails/update/*' => 'emailList.edit',
        '/emails/delete/*' => 'emailList.delete',

        // Image routes
        '/image/view/' => 'page.view_list',
        '/image/view/*' => 'page.view',
        '/image/tableHandler/' => 'page.view_list',
        '/image/create/' => 'page.create',
        '/image/form/' => 'page.create',
        '/image/form/*' => 'page.edit',
        '/image/update/*' => 'page.edit',
        '/image/delete/*' => 'page.delete',

        // File routes
        '/file/view/' => 'page.view_list',
        '/file/view/*' => 'page.view',
        '/file/tableHandler/' => 'page.view_list',
        '/file/create/' => 'page.create',
        '/file/form/' => 'page.create',
        '/file/form/*' => 'page.edit',
        '/file/update/*' => 'page.edit',
        '/file/delete/*' => 'page.delete',

        // Theme settings routes
        '/theme/*' => 'theme.all',

        // Navigation settings routes
        '/navigation/*' => 'navigation.all',

        // Social routes
        '/social/*' => 'social.all',

        // System routes
        '/cache/*' => 'cache.manage',
        '/template/*' => 'template.manage',
        '/translator/*' => 'translator.manage',
        '/activity/*' => 'activity.view',
        '/createTransactions' => 'createTransactions',
        '/statistics' => 'statistics.view',
    ],

    // Role hierarchy (roles inherit permissions from other roles)
    'roles' => [
        User::ROLE_ADMIN => [10], // Admin inherits all delegate permissions
        10 => [], // Delegate is base role
    ],
];
