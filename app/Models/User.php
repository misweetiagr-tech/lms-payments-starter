<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'permissions'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'permissions' => 'array',
        ];
    }

    /**
     * Permission tokens:
     *   "*"               everything
     *   "orders"          legacy flat token: every action in that section
     *   "orders:*"        every action in that section
     *   "orders:view"     one action
     *
     * The legacy flat token keeps working so a new permission model never
     * locks out accounts that were created before it existed.
     */
    public function hasPermission(string $section, string $action): bool
    {
        $tokens = $this->permissions ?? [];

        return in_array('*', $tokens, true)
            || in_array($section, $tokens, true)
            || in_array("$section:*", $tokens, true)
            || in_array("$section:$action", $tokens, true);
    }
}
