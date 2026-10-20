<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ViewCounter
{
    public function increment(Model $model, Request $request, string $prefix): void
    {
        $fingerprint = hash_hmac(
            'sha256',
            ($request->ip() ?? '').'|'.($request->userAgent() ?? ''),
            (string) config('app.key'),
        );
        $key = "views:{$prefix}:{$model->getKey()}:{$fingerprint}";

        if (! Cache::add($key, true, now()->addDay())) {
            return;
        }

        try {
            if (method_exists($model, 'incrementerVues')) {
                $model->incrementerVues();
            } else {
                $model->newQueryWithoutRelationships()
                    ->whereKey($model->getKey())
                    ->toBase()
                    ->increment('nombre_vues');

                $model->nombre_vues = (int) $model->nombre_vues + 1;
                $model->syncOriginalAttribute('nombre_vues');
            }
        } catch (\Throwable $exception) {
            Cache::forget($key);

            throw $exception;
        }
    }
}
