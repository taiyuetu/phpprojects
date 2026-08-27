<?php
namespace App\Models;

use App\Core\Model;

class User extends Model
{
    protected static string $table = 'users';
    protected static array $fillable = ['name', 'email', 'password', 'role'];
}
