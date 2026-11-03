<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class User
{
    public function __construct(private PDO $db) {}

    public function create(string $name, string $email, string $password): int
    {
        $statement = $this->db->prepare('INSERT INTO usuarios (nombre, correo, contrasena_hash) VALUES (:nombre, :correo, :hash)');
        $statement->execute(['nombre' => $name, 'correo' => $email, 'hash' => password_hash($password, PASSWORD_DEFAULT)]);
        return (int) $this->db->lastInsertId();
    }

    public function findByEmail(string $email): ?array
    {
        $statement = $this->db->prepare('SELECT id, nombre, correo, contrasena_hash FROM usuarios WHERE correo = :correo LIMIT 1');
        $statement->execute(['correo' => $email]);
        $user = $statement->fetch();
        return is_array($user) ? $user : null;
    }
}
