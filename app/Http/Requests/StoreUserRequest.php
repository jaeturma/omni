<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', User::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->letters()->numbers()->symbols()],
            'active' => ['required', 'boolean'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', 'distinct', Rule::exists('roles', 'name')->where('guard_name', 'web')],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $roles = $this->input('roles', []);
            if (in_array('Administrator', $roles, true) && ! $this->user()?->hasRole('Administrator')) {
                $validator->errors()->add('roles', 'Only an administrator may assign the Administrator role.');
            }
            if (in_array('Owner', $roles, true) && ! $this->user()?->hasRole('Owner')) {
                $validator->errors()->add('roles', 'Only an owner may assign the Owner role.');
            }
        }];
    }
}
