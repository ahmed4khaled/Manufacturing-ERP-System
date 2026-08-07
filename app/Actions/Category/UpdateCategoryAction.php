<?php

namespace App\Actions\Category;

use App\Models\Category;
use Illuminate\Support\Facades\DB;

class UpdateCategoryAction
{
    public function handle(Category $category, array $data): Category
    {
        return DB::transaction(function () use ($category, $data) {
            $category->update($data);

            return $category->fresh();
        });
    }
}
