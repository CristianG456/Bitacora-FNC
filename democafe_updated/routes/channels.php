<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('caso.{id}', function ($user, $id) {
    // Basic auth: can check if user is involved in the case, but for now we allow any authenticated user to listen
    // Or add logic to check $user->casos()->where('id', $id)->exists() or if admin/juridica
    return true;
});

