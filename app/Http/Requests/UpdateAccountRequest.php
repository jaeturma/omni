<?php

namespace App\Http\Requests;

use App\Models\Account;

class UpdateAccountRequest extends AccountRequest
{
    public function authorize(): bool
    {
        $account = $this->route('account');

        return $account instanceof Account && (bool) $this->user()?->can('update', $account);
    }
}
