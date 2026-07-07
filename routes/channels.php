<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('kitchen', function ($user) {
    return in_array($user->role, ['kitchen', 'admin']);
});
