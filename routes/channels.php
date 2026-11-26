<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (string) $user->id === (string) $id;
});

Broadcast::channel('vouchers', function (User $user): bool {
    return $user->hasPermission('vouchers.view')
        || $user->hasPermission('vouchers.edit');
});

Broadcast::channel('projects', function (User $user): bool {
    return $user->hasPermission('vouchers.view')
        || $user->hasPermission('vouchers.create')
        || $user->hasPermission('vouchers.edit')
        || $user->hasPermission('projects.manage');
});
