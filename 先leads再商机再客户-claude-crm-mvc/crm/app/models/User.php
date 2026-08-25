<?php

class User extends Model
{
    protected string $table = 'users';

    public function findByEmail(string $email)
    {
        return $this->findBy('email', $email);
    }

    public function register(string $name, string $email, string $password, string $role = 'sales'): int
    {
        return $this->create([
            'name'     => $name,
            'email'    => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'role'     => $role,
        ]);
    }

    public function verifyPassword(string $plainPassword, string $hash): bool
    {
        return password_verify($plainPassword, $hash);
    }
}
