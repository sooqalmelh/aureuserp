<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Table Columns
    |--------------------------------------------------------------------------
    */

    'column.name' => '角色名',
    'column.guard_name' => '守衛',
    'column.roles' => '角色',
    'column.permissions' => '權限',
    'column.updated_at' => '更新時間',

    /*
    |--------------------------------------------------------------------------
    | Form Fields
    |--------------------------------------------------------------------------
    */

    'field.name' => '角色名',
    'field.guard_name' => '守衛',
    'field.permissions' => '權限',
    'field.select_all.name' => '全選',
    'field.select_all.message' => '啟用當前為該角色 <span class="text-primary font-medium">啟用的</span> 所有權限',

    /*
    |--------------------------------------------------------------------------
    | Navigation & Resource
    |--------------------------------------------------------------------------
    */

    'nav.group' => '設定',
    'nav.role.label' => '角色',
    'nav.role.icon' => 'heroicon-o-shield-check',
    'resource.label.role' => '角色',
    'resource.label.roles' => '角色',

    /*
    |--------------------------------------------------------------------------
    | Section & Tabs
    |--------------------------------------------------------------------------
    */

    'section' => '實體',
    'resources' => '資源',
    'widgets' => '小工具',
    'pages' => '頁面',
    'custom' => '自訂',

    /*
    |--------------------------------------------------------------------------
    | Messages
    |--------------------------------------------------------------------------
    */

    'forbidden' => '無權訪問',

    /*
    |--------------------------------------------------------------------------
    | Resource Permissions' Labels
    |--------------------------------------------------------------------------
    */

    'resource_permission_prefixes_labels' => [
        'view' => '檢視',
        'view_any' => '列表',
        'create' => '建立',
        'update' => '編輯',
        'delete' => '刪除',
        'delete_any' => '批量刪除',
        'force_delete' => '永久刪除',
        'force_delete_any' => '批量永久刪除',
        'restore' => '還原',
        'reorder' => '重新排序',
        'restore_any' => '批量還原',
        'replicate' => '複製',
    ],
];
