<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Modelo de usuario autenticable conservado del andamiaje inicial de Laravel.
 *
 * No participa en el flujo del juego: los jugadores se identifican con una cookie
 * mediante EnsurePlayer y el modelo Player. Se mantiene por si más adelante se decide
 * vincular el progreso anónimo a una cuenta real.
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Define los casts propios de la autenticación: fecha de verificación y clave hasheada.
     *
     * El cast 'hashed' aplica el hash automáticamente al asignar la contraseña,
     * evitando que se guarde texto plano por descuido.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
