<?php

namespace App\Policies;

use App\Models\ProductService;
use App\Models\User;

class ProductServicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('products-services.view');
    }

    public function view(User $user, ProductService $productService): bool
    {
        return $user->can('products-services.view');
    }

    public function create(User $user): bool
    {
        return $user->can('products-services.create');
    }

    public function update(User $user, ProductService $productService): bool
    {
        return $user->can('products-services.update');
    }

    public function delete(User $user, ProductService $productService): bool
    {
        return $user->can('products-services.delete');
    }

    public function restore(User $user, ProductService $productService): bool
    {
        return false;
    }

    public function forceDelete(User $user, ProductService $productService): bool
    {
        return false;
    }
}
