<?php

namespace Modules\Coach\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Category\Entities\Category;
use Modules\Coach\App\Models\Coach;

class AdminCoachController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Coach::query()->with('branch:id,name,partner_id');
        if ($request->filled('category_id')) $query->where('category_id', $request->integer('category_id'));
        if ($request->filled('search')) $query->where('name', 'like', '%' . $request->string('search')->trim() . '%');

        return response()->json($query->orderBy('name')->paginate($request->integer('per_page', 20)));
    }

    public function show(Coach $coach): JsonResponse
    {
        return response()->json(['data' => $coach->load('branch:id,name,partner_id')]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $branch = Category::query()->where('category_type', 'product')->findOrFail($data['category_id']);
        $data['partner_id'] = $branch->partner_id;
        $data['slug'] = Str::slug($data['name']) . '-' . Str::lower(Str::random(6));

        return response()->json(['data' => Coach::create($data)], 201);
    }

    public function update(Request $request, Coach $coach): JsonResponse
    {
        $data = $this->validated($request, true, $coach);
        if (isset($data['category_id'])) {
            $branch = Category::query()->where('category_type', 'product')->findOrFail($data['category_id']);
            $data['partner_id'] = $branch->partner_id;
        }
        $coach->update($data);

        return response()->json(['data' => $coach->fresh('branch:id,name,partner_id')]);
    }

    public function destroy(Coach $coach): JsonResponse
    {
        if ($coach->bookings()->exists()) {
            return response()->json(['message' => 'HLV đã phát sinh booking; hãy chuyển sang ngừng hoạt động thay vì xoá.'], 422);
        }
        $coach->delete();

        return response()->json(['message' => 'Đã xoá huấn luyện viên.']);
    }

    private function validated(Request $request, bool $partial = false, ?Coach $coach = null): array
    {
        $required = $partial ? 'sometimes' : 'required';
        return $request->validate([
            'category_id' => [$required, 'integer', Rule::exists('categories', 'id')->where('category_type', 'product')],
            'name' => [$required, 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'photo' => ['sometimes', 'nullable', 'string', 'max:255'],
            'specialties' => ['sometimes', 'nullable', 'string', 'max:255'],
            'certifications' => ['sometimes', 'nullable', 'string'],
            'bio' => ['sometimes', 'nullable', 'string'],
            'years_experience' => ['sometimes', 'integer', 'min:0', 'max:80'],
            'hourly_rate' => [$required, 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
