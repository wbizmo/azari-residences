<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Arr;

abstract class AzariFormRequest extends FormRequest
{
    /**
     * Return only validated input. This is the preferred payload for
     * create(), update(), fill() and service-layer commands.
     *
     * @param  array<int, string>|string|null  $keys
     * @return array<string, mixed>
     */
    final public function validatedPayload(array|string|null $keys = null): array
    {
        $validated = $this->validated();

        if ($keys === null) {
            return $validated;
        }

        return Arr::only($validated, Arr::wrap($keys));
    }

    /**
     * Keep JSON validation failures consistent while preserving Laravel's
     * normal redirect-and-errors behavior for Blade requests.
     */
    protected function failedValidation(Validator $validator): void
    {
        if (! $this->expectsJson()) {
            parent::failedValidation($validator);
        }

        throw new HttpResponseException(response()->json([
            'message' => 'Please review the highlighted fields.',
            'errors' => $validator->errors(),
        ], 422));
    }

    /**
     * Keep JSON authorization failures free of implementation details.
     */
    protected function failedAuthorization(): void
    {
        if (! $this->expectsJson()) {
            parent::failedAuthorization();
        }

        throw new HttpResponseException(response()->json([
            'message' => 'You are not authorized to perform this action.',
        ], 403));
    }
}
