<?php

namespace App\Services;

use App\Models\ActivationLicense;
use App\Status;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ActivationLicenseService
{
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function create(array $data): Collection
    {
        $quantity = (int) ($data['quantity'] ?? 1);
        $type = (int) $data['type'];
        $status = (int) ($data['status'] ?? Status::ACTIVE->value);
        $customCode = $data['code'] ?? null;
        $idempotencyKey = $data['idempotency_key'] ?? null;
        $prefix = $customCode === null
            ? $this->resolvePrefix($data['prefix'] ?? config('activation_license.code_prefix'))
            : 'STELLAR';

        return DB::transaction(function () use (
            $data,
            $quantity,
            $type,
            $status,
            $prefix,
            $customCode,
            $idempotencyKey
        ): Collection {
            $licenses = collect();

            for ($index = 0; $index < $quantity; $index++) {
                $code = $customCode ?? $this->generateUniqueCode($prefix, $type);

                $licenses->push(ActivationLicense::create([
                    'code' => $code,
                    'status' => $status,
                    'type' => $type,
                    'subscriptions_days' => (int) $data['subscriptions_days'],
                    'idempotency_key' => $idempotencyKey,
                ]));
            }

            return $licenses;
        });
    }

    private function generateUniqueCode(string $prefix, int $type): string
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $segments = [];

            for ($segment = 0; $segment < 4; $segment++) {
                $segments[] = $this->randomSegment(4);
            }

            $code = $prefix.'-'.implode('-', $segments);

            if (! ActivationLicense::where('code', $code)->where('type', $type)->exists()) {
                return $code;
            }
        }

        throw new RuntimeException('Unable to generate a unique activation license code.');
    }

    private function resolvePrefix(mixed $prefix): string
    {
        $prefix = is_string($prefix) ? strtoupper(trim($prefix)) : 'STELLAR';

        if (! preg_match('/^[A-Z0-9]{2,24}$/', $prefix)) {
            throw new RuntimeException('The configured activation license prefix is invalid.');
        }

        return $prefix;
    }

    private function randomSegment(int $length): string
    {
        $segment = '';
        $lastIndex = strlen(self::CODE_ALPHABET) - 1;

        for ($index = 0; $index < $length; $index++) {
            $segment .= self::CODE_ALPHABET[random_int(0, $lastIndex)];
        }

        return $segment;
    }
}
