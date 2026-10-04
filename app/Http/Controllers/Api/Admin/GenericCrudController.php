<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Concerns\BelongsToPartner;
use App\Models\User;
use App\Support\AdminCrud\CrudRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// CRUD dùng chung cho API admin: mỗi tài nguyên chỉ là một khai báo trong App\Support\AdminCrud\CrudRegistry (model, quyền,
// thao tác cho phép, trường tìm kiếm/lọc...). Luật validate được SINH TỰ ĐỘNG từ cấu trúc bảng + $fillable của model nên API
// luôn khớp với CSDL; từng tài nguyên chỉ cần ghi đè phần khác thường. Cùng định dạng trả về với các controller admin khác:
//   index → phân trang chuẩn Laravel; show/store/update → {"data": ...}; destroy → {"message": ...}.
// Phân quyền do AdminApiAuth đảm nhiệm (nhóm route → quyền Shield), controller này chỉ lo dữ liệu.
class GenericCrudController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $def = $this->def($request);
        $query = $this->query($def, $request);

        if ($def['with'] ?? []) {
            $query->with($def['with']);
        }

        $term = trim((string) $request->query('search', ''));
        if ($term !== '' && ($def['search'] ?? [])) {
            $query->where(function ($w) use ($def, $term) {
                foreach ($def['search'] as $col) {
                    $w->orWhere($col, 'like', '%' . str_replace(['%', '_'], ['\\%', '\\_'], $term) . '%');
                }
            });
        }

        foreach ($def['filters'] ?? [] as $param => $col) {
            $param = is_int($param) ? $col : $param;
            if ($request->filled($param)) {
                $query->where($col, $request->query($param));
            }
        }
        foreach ($def['scopes'] ?? [] as $param => $callback) {
            if ($request->filled($param)) {
                $callback($query, $request->query($param), $request);
            }
        }

        [$sort, $dir] = $def['sort'] ?? ['id', 'desc'];
        $requested = (string) $request->query('sort', '');
        if ($requested !== '' && Schema::hasColumn((new $def['model'])->getTable(), $requested)) {
            $sort = $requested;
            $dir = strtolower((string) $request->query('dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        }
        $query->orderBy($sort, $dir);

        $page = $query->paginate(min(100, max(1, $request->integer('per_page', 20))));
        if (isset($def['transform'])) {
            $page->through(fn (Model $m) => $def['transform']($m));
        }

        return response()->json($page);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $def = $this->def($request);
        $model = $this->find($def, $request, $id);

        return response()->json(['data' => $this->present($def, $model, true)]);
    }

    public function store(Request $request): JsonResponse
    {
        $def = $this->def($request);
        $data = $this->validated($request, $def, null);
        $data = $this->withPartner($request, $def, $data);
        $data = $this->withUploads($request, $def, $data);
        $data = $this->withSlug($def, $data, null);

        if (isset($def['before'])) {
            $data = $def['before']($data, null, $request);
        }

        try {
            /** @var Model $model */
            $model = ($def['model'])::create($data);
        } catch (QueryException $e) {
            return $this->integrity($e);
        }

        if (isset($def['after'])) {
            $def['after']($model, $request, true);
        }

        return response()->json(['data' => $this->present($def, $model->fresh($def['with'] ?? []) ?? $model, true)], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $def = $this->def($request);
        $model = $this->find($def, $request, $id);
        $data = $this->validated($request, $def, $model);
        unset($data['partner_id']); // không đổi chủ sở hữu của bản ghi đã có
        $data = $this->withUploads($request, $def, $data);
        $data = $this->withSlug($def, $data, $model);

        if (isset($def['before'])) {
            $data = $def['before']($data, $model, $request);
        }

        try {
            $model->update($data);
        } catch (QueryException $e) {
            return $this->integrity($e);
        }

        if (isset($def['after'])) {
            $def['after']($model, $request, false);
        }

        return response()->json(['data' => $this->present($def, $model->fresh($def['with'] ?? []) ?? $model, true)]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $def = $this->def($request);
        $model = $this->find($def, $request, $id);

        if (isset($def['deletable']) && ($reason = $def['deletable']($model))) {
            return response()->json(['message' => $reason], 422);
        }

        try {
            $model->delete();
        } catch (QueryException $e) {
            return $this->integrity($e, 'This record is still in use and cannot be deleted.');
        }

        return response()->json(['message' => 'Deleted.']);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────────────────────

    private function def(Request $request): array
    {
        $slug = (string) $request->route('resource');
        $def = CrudRegistry::get($slug);
        abort_if($def === null, 404, 'Unknown resource.');

        return $def;
    }

    private function query(array $def, Request $request)
    {
        $query = ($def['model'])::query();

        // Global scope 'partner' chỉ chạy trong panel Filament — route API phải lọc thủ công theo đối tác của người gọi
        // (super_admin xem hết). Người dùng không thuộc đối tác nào (partner_id null) không thấy dữ liệu của đối tác.
        $user = $request->user();
        if ($this->isPartnerScoped($def) && $user instanceof User && ! $user->isSuperAdmin()) {
            $query->where((new $def['model'])->getTable() . '.partner_id', $user->partner_id);
        }
        if (isset($def['scope'])) {
            $def['scope']($query, $request->user());
        }

        return $query;
    }

    private function find(array $def, Request $request, string $id): Model
    {
        $query = $this->query($def, $request);
        if ($def['with'] ?? []) {
            $query->with($def['with']);
        }

        return $query->findOrFail($id);
    }

    private function isPartnerScoped(array $def): bool
    {
        return in_array(BelongsToPartner::class, class_uses_recursive($def['model']), true);
    }

    private function present(array $def, Model $model, bool $detail): mixed
    {
        if (isset($def['transform'])) {
            return $def['transform']($model);
        }

        return $model;
    }

    private function integrity(QueryException $e, string $message = 'Invalid reference or duplicate value.'): JsonResponse
    {
        // 23000 = vi phạm ràng buộc (khoá ngoại / trùng khoá duy nhất). Lỗi khác là lỗi hệ thống thật, để nổi lên.
        if (($e->errorInfo[0] ?? '') !== '23000') {
            throw $e;
        }

        return response()->json(['message' => $message], 422);
    }

    // Bản ghi mới của người dùng thuộc đối tác luôn mang partner_id của họ (bỏ qua giá trị client gửi lên); chỉ super_admin
    // được chọn đối tác khác bằng partner_id.
    private function withPartner(Request $request, array $def, array $data): array
    {
        if (! $this->isPartnerScoped($def)) {
            return $data;
        }
        $user = $request->user();
        if ($user instanceof User && ! $user->isSuperAdmin()) {
            $data['partner_id'] = $user->partner_id;
        }

        return $data;
    }

    private function withUploads(Request $request, array $def, array $data): array
    {
        foreach ($def['files'] ?? [] as $field => $dir) {
            if ($request->hasFile($field)) {
                $data[$field] = $request->file($field)->store($dir, 'public');
            }
        }

        return $data;
    }

    private function withSlug(array $def, array $data, ?Model $model): array
    {
        $table = (new $def['model'])->getTable();
        if (! in_array('slug', (new $def['model'])->getFillable(), true) || ! empty($def['no_slug'])) {
            return $data;
        }
        $source = $data['name'] ?? $data['title'] ?? null;
        if (isset($data['slug']) || $source === null) {
            return $data;
        }
        if ($model && ($data['name'] ?? $data['title']) === ($model->name ?? $model->title ?? null)) {
            return $data;
        }
        $base = Str::slug((string) $source) ?: 'item';
        $slug = $base;
        $i = 2;
        while (($def['model'])::query()->where('slug', $slug)->when($model, fn ($q) => $q->whereKeyNot($model->getKey()))->exists()) {
            $slug = $base . '-' . $i++;
        }
        $data['slug'] = $slug;

        return $data;
    }

    // ── Validate tự sinh từ cấu trúc bảng ────────────────────────────────────────────────────

    private function validated(Request $request, array $def, ?Model $model): array
    {
        $partial = $model !== null;
        $rules = $this->rules($def, $partial);

        // Form-data gửi "true"/"false" dạng chuỗi: đưa về boolean trước khi validate.
        foreach ($rules as $field => $_) {
            if (in_array('boolean', (array) $rules[$field], true) && is_string($request->input($field)) && in_array(strtolower($request->input($field)), ['true', 'false'], true)) {
                $request->merge([$field => strtolower($request->input($field)) === 'true']);
            }
        }

        $validated = $request->validate($rules);
        // Trường file đã xử lý riêng, không nằm trong $validated của cột.
        foreach (array_keys($def['files'] ?? []) as $f) {
            unset($validated[$f]);
        }

        return $validated;
    }

    private function rules(array $def, bool $partial): array
    {
        $model = new $def['model'];
        $table = $model->getTable();
        $casts = $model->getCasts();
        $columns = collect(Schema::getColumns($table))->keyBy('name');
        $skip = $def['except'] ?? [];
        $forceRequired = $def['required'] ?? [];
        $forceOptional = $def['optional'] ?? [];
        $rules = [];

        foreach ($model->getFillable() as $field) {
            if (in_array($field, $skip, true) || ! isset($columns[$field])) {
                continue;
            }
            $col = $columns[$field];
            $type = strtolower($col['type']);
            $cast = strtolower((string) ($casts[$field] ?? ''));
            $nullable = (bool) $col['nullable'];
            $hasDefault = $col['default'] !== null || ($col['auto_increment'] ?? false);

            $r = [];
            if ($cast === 'boolean' || str_starts_with($type, 'tinyint(1)')) {
                $r[] = 'boolean';
            } elseif (preg_match('/^(tinyint|smallint|mediumint|int|bigint)/', $type)) {
                $r[] = 'integer';
            } elseif (preg_match('/^(decimal|float|double)/', $type)) {
                $r[] = 'numeric';
            } elseif ($type === 'json' || in_array($cast, ['array', 'json', 'collection', 'object'], true)) {
                $r[] = 'array';
            } elseif ($type === 'date') {
                $r[] = 'date';
            } elseif (str_starts_with($type, 'datetime') || str_starts_with($type, 'timestamp')) {
                $r[] = 'date';
            } elseif (str_starts_with($type, 'time')) {
                $r[] = 'date_format:H:i,H:i:s';
            } elseif (str_starts_with($type, 'enum(')) {
                preg_match_all("/'([^']*)'/", $type, $m);
                $r[] = 'in:' . implode(',', $m[1]);
            } elseif (preg_match('/^(var)?char\((\d+)\)/', $type, $m)) {
                $r[] = 'string';
                $r[] = 'max:' . $m[2];
            } else {
                $r[] = 'string';
            }

            // slug tự sinh từ name/title khi không gửi (withSlug) nên không bắt buộc.
            $selfFilled = $field === 'slug' && ! in_array('slug', $forceRequired, true);
            $isRequired = ! $partial && ! $nullable && ! $hasDefault && ! $selfFilled && ! in_array($field, $forceOptional, true);
            $isRequired = $isRequired || (! $partial && in_array($field, $forceRequired, true));
            $head = $isRequired ? 'required' : ($partial ? 'sometimes' : 'nullable');
            if ($nullable && ! $isRequired) {
                array_unshift($r, 'nullable');
            }
            array_unshift($r, $head);
            $rules[$field] = $r;
        }

        foreach ($def['rules'] ?? [] as $field => $override) {
            $rules[$field] = is_callable($override) ? $override($partial) : $override;
        }
        foreach ($def['files'] ?? [] as $field => $dir) {
            $rules[$field] = ['sometimes', 'nullable', 'file', 'image', 'max:8192'];
        }

        return $rules;
    }
}
