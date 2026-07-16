<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Spatie\Permission\Models\Role;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->route('user');

        return $user instanceof User && (bool) $this->user()?->can('update', $user);
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
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class)->ignore($this->route('user'))],
            'password' => ['nullable', 'string', 'min:12', 'confirmed'],
            'active' => ['required', 'boolean'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', 'distinct', Rule::exists('roles', 'name')->where('guard_name', 'web')],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $target = $this->route('user');
            if (! $target instanceof User || $validator->errors()->isNotEmpty()) {
                return;
            }
            $removesAdministrator = $target->hasRole('Administrator') && (! $this->boolean('active') || ! in_array('Administrator', $this->input('roles', []), true));
            $retainsUserManagement = Role::query()->whereIn('name', $this->input('roles', []))->whereHas('permissions', fn ($query) => $query->where('name', 'users.manage'))->exists();
            if ($target->is($this->user()) && (! $this->boolean('active') || ! $retainsUserManagement)) {
                $validator->errors()->add('roles', 'You cannot remove your own active user-management access.');
            } elseif ($removesAdministrator && User::role('Administrator')->where('active', true)->count() <= 1) {
                $validator->errors()->add('roles', 'The last active administrator cannot be deactivated or demoted.');
            }
        }];
    }
}
