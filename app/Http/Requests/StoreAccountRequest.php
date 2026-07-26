<?php

namespace App\Http\Requests;

use App\Models\Account;

class StoreAccountRequest extends AccountRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Account::class);
    }
}
