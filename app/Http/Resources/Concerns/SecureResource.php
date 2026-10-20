<?php

namespace App\Http\Resources\Concerns;

/**
 * Trait pour sécuriser les ressources API.
 * Ce trait fournit des méthodes utilitaires pour éviter l'exposition de champs sensibles.
 */
trait SecureResource
{
    /**
     * Liste des champs qui NE DOIVENT JAMAIS être exposés dans les réponses API.
     * Ces champs contiennent des informations sensibles comme les mots de passe,
     * tokens, secrets, etc.
     *
     * @var array<string>
     */
    protected static array $sensitiveFields = [
        'password',
        'remember_token',
        'api_token',
        'google2fa_secret',
        'challenge_token',
        'secret',
        'token',
    ];

    /**
     * Filtre un tableau pour supprimer les champs sensibles.
     *
     * @param array $data Les données à filtrer
     * @return array Les données sans champs sensibles
     */
    protected function filterSensitiveFields(array $data): array
    {
        foreach (self::$sensitiveFields as $field) {
            unset($data[$field]);
        }

        return $data;
    }

    /**
     * Vérifie si un nom de champ est sensible.
     *
     * @param string $fieldName Le nom du champ à vérifier
     * @return bool True si le champ est sensible
     */
    protected function isSensitiveField(string $fieldName): bool
    {
        return in_array($fieldName, self::$sensitiveFields, true);
    }

    /**
     * Ajoute un champ à la liste des champs sensibles.
     *
     * @param string $fieldName Le nom du champ à ajouter
     * @return void
     */
    protected static function addSensitiveField(string $fieldName): void
    {
        if (!in_array($fieldName, self::$sensitiveFields, true)) {
            self::$sensitiveFields[] = $fieldName;
        }
    }

    /**
     * Récupère uniquement les champs autorisés d'un modèle.
     * Utile pour éviter l'exposition de tous les champs avec toArray().
     *
     * @param array $allowedFields Liste des champs autorisés
     * @return array Les données filtrées
     */
    protected function onlyAllowedFields(array $allowedFields): array
    {
        $result = [];
        $allFields = $this->toArray();

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $allFields) && !$this->isSensitiveField($field)) {
                $result[$field] = $allFields[$field];
            }
        }

        return $result;
    }
}
