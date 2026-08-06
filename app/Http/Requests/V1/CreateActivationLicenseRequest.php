<?php

namespace App\Http\Requests\V1;

use App\Status;
use App\Type;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CreateActivationLicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        foreach (['type', 'status', 'subscriptions_days', 'quantity'] as $field) {
            $value = $this->input($field);

            if (is_string($value) && filter_var($value, FILTER_VALIDATE_INT) !== false) {
                $data[$field] = (int) $value;
            }
        }

        foreach (['code', 'prefix'] as $field) {
            if (is_string($this->input($field))) {
                $data[$field] = strtoupper(trim((string) $this->input($field)));
            }
        }

        if (is_string($this->input('idempotency_key'))) {
            $data['idempotency_key'] = trim((string) $this->input('idempotency_key'));
        }

        $this->merge($data);
    }

    public function rules(): array
    {
        $maxBatchSize = max(1, (int) config('activation_license.max_batch_size', 100));

        return [
            'type' => [
                'required',
                'integer',
                Rule::in(array_map(static fn (Type $type): int => $type->value, Type::cases())),
            ],
            'subscriptions_days' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'status' => [
                'sometimes',
                'integer',
                Rule::in([Status::INACTIVE->value, Status::ACTIVE->value]),
            ],
            'quantity' => [
                'sometimes',
                'integer',
                'min:1',
                'max:'.$maxBatchSize,
            ],
            'code' => ['nullable', 'string', 'min:8', 'max:128', 'regex:/^[A-Z0-9]+(?:-[A-Z0-9]+)*$/'],
            'prefix' => ['nullable', 'string', 'min:2', 'max:24', 'regex:/^[A-Z0-9]+$/'],
            'idempotency_key' => [
                'nullable',
                'string',
                'min:8',
                'max:191',
                'regex:/^[A-Za-z0-9._:-]+$/',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $quantity = (int) $this->input('quantity', 1);

            if ($this->filled('code') && $quantity !== 1) {
                $validator->errors()->add('quantity', 'Quantity must be 1 when a custom code is provided.');
            }

            if ($this->filled('idempotency_key') && $quantity !== 1) {
                $validator->errors()->add('quantity', 'Quantity must be 1 when an idempotency key is provided.');
            }

            if ($this->filled('code') && $this->filled('prefix')) {
                $validator->errors()->add('prefix', 'Prefix cannot be used together with a custom code.');
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'response_code' => 422,
            'response_message' => 'Validation failed.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
