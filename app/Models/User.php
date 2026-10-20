<?php
// app/Models/User.php

namespace App\Models;

use App\Mail\PasswordResetMail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'role',
        'is_active',
        'google2fa_secret',
        'google2fa_enabled',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'google2fa_secret',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
            'google2fa_secret'  => 'encrypted',
            'google2fa_enabled' => 'boolean',
        ];
    }

    // ========== RELATIONS ==========
    public function articles()   { return $this->hasMany(Article::class); }
    public function videos()     { return $this->hasMany(Video::class); }

    // ========== HELPERS ==========
    public function estAdmin(): bool     { return $this->role === 'admin'; }
    public function estRedacteur(): bool { return $this->role === 'redacteur'; }
    public function estActif(): bool     { return $this->is_active; }
    public function necessite2FA(): bool { return $this->estAdmin() || $this->estRedacteur(); }

    /**
     * Envoie le mail de réinitialisation vers une page du site public (SPA),
     * pas vers une route web Laravel (cette API est stateless / sans vues).
     */
    public function sendPasswordResetNotification($token): void
    {
        $resetUrl = rtrim(config('app.frontend_url'), '/')
            . '/management/septentrional/reinitialiser-mot-de-passe'
            . '?token=' . $token
            . '&email=' . urlencode($this->email);

        Mail::to($this->email)->send(new PasswordResetMail($resetUrl, $this->name));
    }
}