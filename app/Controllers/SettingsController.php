<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Middleware\AuthMiddleware;
use App\Models\Settings;
use App\Services\Session;
use PDOException;

final class SettingsController extends Controller
{
    private function model(): Settings { return new Settings(Database::connection()); }
    private function userId(): int { return (int)$_SESSION['user']['id']; }
    public function index(): void { AuthMiddleware::requireUser(); $m=$this->model(); $profile=$m->profile($this->userId()); if(!$profile){http_response_code(401);header('Location: /login',true,303);return;} $this->view('settings/index',['title'=>'Configuración','profile'=>$profile,'theme'=>$m->theme($this->userId()),'csrf'=>Session::csrfToken(),'success'=>Session::flash('success'),'error'=>Session::flash('error')]); }
    public function profile(): void
    {
        AuthMiddleware::requireUser(); if(!$this->validCsrf())return;
        $name=trim($this->scalar($_POST['nombre']??'')); $email=strtolower(trim($this->scalar($_POST['correo']??''))); $len=function_exists('mb_strlen')?mb_strlen($name):strlen($name); $errors=[];
        if($name===''||$len>120)$errors[]='El nombre es obligatorio y debe tener máximo 120 caracteres.';
        if(filter_var($email,FILTER_VALIDATE_EMAIL)===false||strlen($email)>254)$errors[]='Ingresa un correo electrónico válido.';
        if(!$errors&&$this->model()->emailExists($email,$this->userId()))$errors[]='Ya existe una cuenta con ese correo.';
        if($errors){$this->showErrors($errors,['nombre'=>$name,'correo'=>$email]);return;}
        try{$this->model()->updateProfile($this->userId(),$name,$email);}catch(PDOException $e){if($e->getCode()!=='23000')throw $e;$this->showErrors(['Ya existe una cuenta con ese correo.'],['nombre'=>$name,'correo'=>$email]);return;}
        $_SESSION['user']['nombre']=$name;$_SESSION['user']['correo']=$email; Session::flash('success','Tu perfil fue actualizado.');header('Location: /configuracion',true,303);
    }
    public function password(): void
    {
        AuthMiddleware::requireUser();if(!$this->validCsrf())return;$m=$this->model();$p=$m->profile($this->userId());$current=$this->scalar($_POST['contrasena_actual']??'');$new=$this->scalar($_POST['contrasena_nueva']??'');$confirm=$this->scalar($_POST['confirmacion']??'');$errors=[];
        if(!$p||!password_verify($current,$p['contrasena_hash']))$errors[]='La contraseña actual no es correcta.';
        if(strlen($new)<8||strlen($new)>72)$errors[]='La nueva contraseña debe tener entre 8 y 72 caracteres.';
        if($new!==$confirm)$errors[]='La confirmación no coincide con la nueva contraseña.';
        if($errors){$this->showErrors($errors,$p??[]);return;}$m->updatePassword($this->userId(),password_hash($new,PASSWORD_DEFAULT));session_regenerate_id(true);unset($_SESSION['_csrf']);Session::flash('success','Tu contraseña fue actualizada.');header('Location: /configuracion',true,303);
    }
    public function theme(): void { AuthMiddleware::requireUser();if(!$this->validCsrf())return;$theme=$this->scalar($_POST['tema']??'');if(!in_array($theme,['claro','oscuro'],true)){Session::flash('error','Selecciona un tema válido.');header('Location: /configuracion',true,303);return;}$this->model()->saveTheme($this->userId(),$theme);Session::flash('success','La preferencia de tema fue guardada.');header('Location: /configuracion',true,303); }
    private function scalar(mixed $v): string{return is_scalar($v)?(string)$v:'';}
    private function validCsrf(): bool{if(Session::verifyCsrf(is_string($_POST['_csrf']??null)?$_POST['_csrf']:null))return true;http_response_code(419);$this->view('errors/419',['title'=>'Formulario vencido']);return false;}
    private function showErrors(array $errors,array $profile): void{http_response_code(422);$m=$this->model();$this->view('settings/index',['title'=>'Configuración','profile'=>$profile,'theme'=>$m->theme($this->userId()),'csrf'=>Session::csrfToken(),'errors'=>$errors]);}
}
