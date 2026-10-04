<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Product\App\Models\Product;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('coach_profiles')) return;

        $legacy = DB::table('products as p')
            ->leftJoin('coach_profiles as cp', 'cp.product_id', '=', 'p.id')
            ->where('p.type', 'coach')
            ->select('p.*', 'cp.specialties', 'cp.certifications', 'cp.bio', 'cp.years_experience')
            ->get();

        foreach ($legacy as $row) {
            $categoryId = DB::table('categorizables')
                ->where('categorizable_id', $row->id)
                ->where('categorizable_type', Product::class)
                ->value('category_id');
            if (! $categoryId) continue;

            $category = DB::table('categories')->where('id', $categoryId)->first();
            for ($i = 0; $i < 5 && $category?->parent_id; $i++) {
                $category = DB::table('categories')->where('id', $category->parent_id)->first() ?? $category;
            }
            if (! $category) continue;

            $slug = $row->slug ?: Str::slug($row->name) . '-' . Str::lower(Str::random(6));
            DB::table('coaches')->updateOrInsert(['slug' => $slug], [
                'partner_id' => $row->partner_id,
                'category_id' => $category->id,
                'name' => $row->name,
                'specialties' => $row->specialties,
                'certifications' => $row->certifications,
                'bio' => $row->bio,
                'years_experience' => $row->years_experience ?? 0,
                'hourly_rate' => (int) ($row->price ?? 0),
                'is_active' => (bool) $row->is_activated,
                'created_at' => $row->created_at ?? now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('products')->where('type', 'coach')->update(['is_activated' => false]);
        Schema::dropIfExists('coach_profiles');
    }

    public function down(): void
    {
        // Không tái tạo kiến trúc Product-coach cũ; dữ liệu HLV chuẩn nằm trong module Coach.
    }
};
