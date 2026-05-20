<?php

namespace App\Helpers;

use App\Models\User;

class DashboardSearchHelper
{
    public static function getItemsForUser(User $user): array
    {
        $items = [];
        $type = (int) $user->user_type;

        // General — all users
        $items[] = ['name' => 'Dashboard', 'path' => '/dashboard', 'icon' => 'dashboard', 'category' => 'General', 'keywords' => 'home overview main'];
        $items[] = ['name' => 'Calendar', 'path' => '/calendar', 'icon' => 'calendar', 'category' => 'General', 'keywords' => 'events schedule'];

        // Account — all users
        $items[] = ['name' => 'Profile', 'path' => '/profile', 'icon' => 'user-profile', 'category' => 'Account', 'keywords' => 'user personal info account'];

        // Officer/President forms
        $isOfficerOrPresident = $user->officers()->whereIn('role', ['officer', 'president'])->exists();

        if ($isOfficerOrPresident) {
            $items[] = ['name' => 'Event Plans', 'path' => '/event-plans', 'icon' => 'calendar', 'category' => 'Organization', 'keywords' => 'event plans activities calendar submit'];

            $publishedForms = \App\Models\Form::whereNotNull('route_name')
                ->where('is_published', true)
                ->orderBy('name')
                ->get(['name', 'route_name']);

            foreach ($publishedForms as $form) {
                $items[] = [
                    'name' => $form->name,
                    'path' => '/forms/' . $form->route_name,
                    'icon' => 'forms',
                    'category' => 'Forms',
                    'keywords' => 'form submit organization ' . $form->route_name,
                ];
            }
        }

        // Admin items — type 2
        if ($type === 2) {
            $items[] = ['name' => 'Template Manager', 'path' => '/admin/templates', 'icon' => 'forms', 'category' => 'Admin', 'keywords' => 'templates documents manage'];
            $items[] = ['name' => 'Promotion Requests', 'path' => '/promotion-requests', 'icon' => 'task', 'category' => 'Admin', 'keywords' => 'promotions requests pending approve'];
        }

        // Superadmin items — type 1
        if ($type === 1) {
            $items[] = ['name' => 'Profile Manager', 'path' => '/superadmin/profiles', 'icon' => 'user-profile', 'category' => 'Superadmin', 'keywords' => 'users profiles edit manage'];
            $items[] = ['name' => 'Profile Requests', 'path' => '/superadmin/profile-requests', 'icon' => 'task', 'category' => 'Superadmin', 'keywords' => 'profile submissions pending review'];
            $items[] = ['name' => 'Dashboard Builder', 'path' => '/superadmin/dashboard-builder', 'icon' => 'charts', 'category' => 'Superadmin', 'keywords' => 'widgets layout builder customize'];
            $items[] = ['name' => 'Request Types', 'path' => '/superadmin/request-types', 'icon' => 'forms', 'category' => 'Superadmin', 'keywords' => 'action types configuration'];
            $items[] = ['name' => 'Template Manager', 'path' => '/admin/templates', 'icon' => 'forms', 'category' => 'Superadmin', 'keywords' => 'templates documents manage'];
            $items[] = ['name' => 'Data Sync', 'path' => '/superadmin/data-sync', 'icon' => 'tables', 'category' => 'Superadmin', 'keywords' => 'import export sync data'];
            $items[] = ['name' => 'Export Data', 'path' => '/superadmin/export', 'icon' => 'tables', 'category' => 'Superadmin', 'keywords' => 'export json pdf org officers requests records'];
        }

        return $items;
    }

    public static function search(User $user, string $query, int $limit = 10): array
    {
        if (strlen(trim($query)) < 1) {
            return [];
        }

        $q = mb_strtolower(trim($query));
        $results = [];

        foreach (self::getItemsForUser($user) as $item) {
            $haystack = mb_strtolower($item['name'] . ' ' . ($item['keywords'] ?? '') . ' ' . $item['category']);
            if (str_contains($haystack, $q)) {
                $results[] = $item;
            }
            if (count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }
}
