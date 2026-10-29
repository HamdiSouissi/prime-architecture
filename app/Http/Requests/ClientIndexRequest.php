<?php

namespace App\Http\Requests;

use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;

class ClientIndexRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()->can('viewAny', Client::class); }
    public function rules(): array { return ['search' => ['nullable', 'string', 'max:255'], 'type' => ['nullable', 'in:individual,company'], 'governorate_id' => ['nullable', 'integer'], 'archived' => ['nullable', 'in:1']]; }
}
