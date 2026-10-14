<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Modules\Comment\Entities\Comment;
use Modules\Form\Entities\Form;
use Modules\Form\Entities\FormFieldValue;
use Modules\Form\Entities\FormSubmission;
use Modules\Menu\Entities\Menu;
use Modules\Post\Entities\Post;
use Modules\Product\App\Models\Product;

// API nội dung cho app/web client: bài viết, bình luận (bài viết + sân), biểu mẫu liên hệ và menu. Tất cả chỉ đọc nội dung đã công khai.
class CmsClientController extends Controller
{
    // GET /api/posts — ?search=, ?category_id=, ?per_page=10. Chỉ bài đã xuất bản và đến giờ đăng.
    public function posts(Request $request): JsonResponse
    {
        $page = Post::query()->where('status', 'published')->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()))
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($w) => $w->where('title', 'like', '%' . $request->query('search') . '%')->orWhere('summary', 'like', '%' . $request->query('search') . '%')))
            ->when($request->filled('category_id'), fn ($q) => $q->whereHas('categories', fn ($c) => $c->where('categories.id', (int) $request->query('category_id'))))
            ->orderByDesc('published_at')->orderByDesc('created_at')
            ->paginate(min(50, max(1, $request->integer('per_page', 10))));

        $page->through(fn (Post $p) => $this->postItem($p, false));

        return response()->json($page);
    }

    // GET /api/posts/{slug}
    public function post(string $slug): JsonResponse
    {
        $post = $this->publishedPost($slug);

        return $post ? response()->json(['data' => $this->postItem($post, true)]) : response()->json(['message' => 'Post not found.'], 404);
    }

    // GET /api/posts/{slug}/comments, GET /api/rooms/{id}/comments — bình luận đang hiển thị (ghim lên đầu) kèm phản hồi
    public function postComments(Request $request, string $slug): JsonResponse
    {
        $post = $this->publishedPost($slug);

        return $post ? $this->listComments(Post::class, (string) $post->id, $request) : response()->json(['message' => 'Post not found.'], 404);
    }

    public function roomComments(Request $request, string $id): JsonResponse
    {
        return Product::query()->whereKey($id)->exists() ? $this->listComments(Product::class, $id, $request) : response()->json(['message' => 'Room not found.'], 404);
    }

    // POST /api/posts/{slug}/comments, POST /api/rooms/{id}/comments — body: text, name (bắt buộc với khách vãng lai)
    public function commentOnPost(Request $request, string $slug): JsonResponse
    {
        $post = $this->publishedPost($slug);

        return $post ? $this->createComment(Post::class, (string) $post->id, $request) : response()->json(['message' => 'Post not found.'], 404);
    }

    public function commentOnRoom(Request $request, string $id): JsonResponse
    {
        return Product::query()->whereKey($id)->exists() ? $this->createComment(Product::class, $id, $request) : response()->json(['message' => 'Room not found.'], 404);
    }

    // GET /api/forms/{id} — định nghĩa các trường để app tự dựng form
    public function form(int $id): JsonResponse
    {
        $form = Form::query()->with(['formFields' => fn ($q) => $q->orderBy('sort_order'), 'notification'])->find($id);
        if (! $form) {
            return response()->json(['message' => 'Form not found.'], 404);
        }

        return response()->json(['data' => [
            'id' => $form->id, 'name' => $form->name, 'submit_button_text' => $form->submit_button_text,
            'success_message' => $form->notification?->success_message, 'error_message' => $form->notification?->error_message,
            'fields' => $form->formFields->map(fn ($f) => [
                'name' => $f->name, 'label' => $f->label, 'type' => $f->type, 'options' => $f->options, 'required' => (bool) $f->is_required,
                'min_length' => $f->min_length, 'max_length' => $f->max_length,
            ])->values()->all(),
        ]]);
    }

    // POST /api/forms/{id}/submit — body: {tên_trường: giá_trị,...} (multipart nếu có trường file)
    public function submitForm(Request $request, int $id): JsonResponse
    {
        $form = Form::query()->with(['formFields', 'notification'])->find($id);
        if (! $form) {
            return response()->json(['message' => 'Form not found.'], 404);
        }

        $rules = [];
        foreach ($form->formFields as $f) {
            $r = [$f->is_required ? 'required' : 'nullable'];
            match ($f->type) {
                'email' => $r[] = 'email',
                'tel' => $r[] = 'regex:/^0[1-9][0-9]{2}([- ]?[0-9]{3}){2}$/',
                'file' => array_push($r, 'file', 'mimes:pdf,jpeg,png,jpg,xlsx,xls,doc,docx', 'max:10240'),
                default => null,
            };
            if ($f->type !== 'file') {
                $r[] = 'max:' . ($f->max_length ?: 5000);
                if ($f->min_length) {
                    $r[] = 'min:' . $f->min_length;
                }
            }
            $rules[$f->name] = $r;
        }

        $data = Validator::make($request->all(), $rules)->validate();
        if (collect($form->formFields)->every(fn ($f) => blank($request->input($f->name)) && ! $request->hasFile($f->name))) {
            return response()->json(['message' => 'Please fill in at least one field.', 'errors' => ['form' => ['Please fill in at least one field.']]], 422);
        }

        $submission = FormSubmission::create(['form_id' => $form->id, 'is_viewed' => false]);
        foreach ($form->formFields as $f) {
            $value = $request->hasFile($f->name) ? $request->file($f->name)->store('uploads', 'public') : ($data[$f->name] ?? null);
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            // Chống chèn script/HTML vào dữ liệu hiển thị ở trang quản trị.
            FormFieldValue::create(['form_submission_id' => $submission->id, 'form_field_id' => $f->id, 'value' => is_string($value) && $f->type !== 'file' ? strip_tags($value) : $value]);
        }

        return response()->json(['message' => $form->notification?->success_message ?: 'Thank you, your submission has been received.', 'data' => ['submission_id' => $submission->id]], 201);
    }

    // GET /api/menus/{location} — menu theo vị trí (vd header, footer), dạng cây
    public function menu(string $location): JsonResponse
    {
        $menu = Menu::location($location);
        if (! $menu) {
            return response()->json(['message' => 'Menu not found.'], 404);
        }

        $node = function ($item) use (&$node) {
            return [
                'id' => $item->id, 'title' => $item->title, 'url' => $item->url ? (str_starts_with($item->url, 'http') ? $item->url : url($item->url)) : null,
                'target' => $item->target?->value ?? '_self', 'children' => $item->children->sortBy('order')->map($node)->values()->all(),
            ];
        };

        return response()->json(['data' => ['name' => $menu->name ?? $location, 'items' => $menu->menuItems->map($node)->values()->all()]]);
    }

    private function publishedPost(string $slug): ?Post
    {
        return Post::query()->where('slug', $slug)->where('status', 'published')
            ->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()))->first();
    }

    private function postItem(Post $p, bool $full): array
    {
        $item = [
            'id' => $p->id, 'title' => $p->title, 'slug' => $p->slug, 'summary' => $p->summary, 'thumbnail' => $p->getFirstMediaUrl() ?: null,
            'published_at' => $p->published_at?->toIso8601String(),
        ];

        return $full ? $item + ['content' => $p->content, 'seo_title' => $p->seo_title, 'seo_description' => $p->seo_description] : $item;
    }

    private function listComments(string $type, string $id, Request $request): JsonResponse
    {
        $page = Comment::query()->where('commentable_type', $type)->where('commentable_id', $id)->where('show', true)
            ->with(['replies' => fn ($q) => $q->where('show', true)->orderBy('created_at')])
            ->orderByDesc('pin')->orderByDesc('created_at')->paginate(min(50, max(1, $request->integer('per_page', 10))));

        $page->through(fn (Comment $c) => [
            'id' => $c->id, 'name' => $c->name, 'text' => $c->text, 'pinned' => (bool) $c->pin, 'created_at' => $c->created_at?->toIso8601String(),
            'replies' => $c->replies->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'text' => $r->text, 'created_at' => $r->created_at?->toIso8601String()])->all(),
        ]);

        return response()->json($page);
    }

    private function createComment(string $type, string $id, Request $request): JsonResponse
    {
        /** @var Customer|null $customer */
        $customer = auth('sanctum')->user() instanceof Customer ? auth('sanctum')->user() : null;

        $data = $request->validate([
            'name' => [$customer ? 'nullable' : 'required', 'string', 'min:2', 'max:50'],
            'text' => 'required|string|min:2|max:2000',
        ]);

        $comment = Comment::create([
            'name' => strip_tags($customer?->fullname ?: $data['name']), 'text' => strip_tags($data['text']),
            'commentable_type' => $type, 'commentable_id' => $id,
        ]);

        return response()->json(['message' => 'Comment posted.', 'data' => ['id' => $comment->id, 'name' => $comment->name, 'text' => $comment->text, 'created_at' => $comment->created_at?->toIso8601String()]], 201);
    }
}
