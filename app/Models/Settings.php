<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class Settings
{
    public function __construct(private PDO $db) {}
    public function profile(int $id): ?array { $q = $this->db->prepare('SELECT id,nombre,correo,contrasena_hash FROM usuarios WHERE id=:id'); $q->execute(['id'=>$id]); $r=$q->fetch(); return is_array($r) ? $r : null; }
    public function emailExists(string $email, int $id): bool { $q=$this->db->prepare('SELECT 1 FROM usuarios WHERE correo=:correo AND id<>:id LIMIT 1'); $q->execute(['correo'=>$email,'id'=>$id]); return (bool)$q->fetchColumn(); }
    public function updateProfile(int $id,string $name,string $email): void { $q=$this->db->prepare('UPDATE usuarios SET nombre=:nombre,correo=:correo WHERE id=:id'); $q->execute(['nombre'=>$name,'correo'=>$email,'id'=>$id]); }
    public function updatePassword(int $id,string $hash): void { $q=$this->db->prepare('UPDATE usuarios SET contrasena_hash=:hash WHERE id=:id'); $q->execute(['hash'=>$hash,'id'=>$id]); }
    public function theme(int $id): string { $q=$this->db->prepare("SELECT valor FROM configuraciones WHERE usuario_id=:id AND clave='tema' LIMIT 1"); $q->execute(['id'=>$id]); return $q->fetchColumn()==='oscuro'?'oscuro':'claro'; }
    public function saveTheme(int $id,string $theme): void { $q=$this->db->prepare("INSERT INTO configuraciones (usuario_id,clave,valor) VALUES (:id,'tema',:tema) ON DUPLICATE KEY UPDATE valor=VALUES(valor)"); $q->execute(['id'=>$id,'tema'=>$theme]); }
}
