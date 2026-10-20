<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

/**
 * Règle de validation pour les mots de passe forts.
 * Doit contenir :
 * - Au moins 8 caractères
 * - Une majuscule
 * - Une minuscule
 * - Un chiffre
 * - Un caractère spécial
 */
class StrongPassword implements Rule
{
    public function passes($attribute, $value): bool
    {
        return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z0-9]).{8,}$/', $value);
    }

    public function message(): string
    {
        return 'Le mot de passe doit contenir au moins 8 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial.';
    }
}
