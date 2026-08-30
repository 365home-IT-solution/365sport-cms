@extends('bladethemev1::layouts.master')

<x-bladethemev1::seo :seoData="$seoData" />

@section('content')
    <x-bladethemev1::home.top-bar />
    <x-bladethemev1::home.search-bar />
    @livewire('bladethemev1::drawer-menu')

    {{-- Livewire\PostPage lấy màu "primary" từ theme Filament (site_theme cấu hình chung toàn
         site — dùng cho các trang page-builder khác), nên "Nổi bật" trước đây hiện màu xám/xanh
         đen mặc định KHÔNG khớp màu xanh lá thương hiệu (#0B6B3A) của top-bar/bản đồ/khám phá vừa
         dựng lại. KHÔNG đổi --color-primary/site_theme toàn site (sẽ vỡ màu các trang page-builder
         khác đang dùng theme cũ) — chỉ ĐÈ CỤC BỘ ngay trong scope trang này bằng inline CSS var
         (che các class Tailwind "bg-primary/text-primary/border-primary" ở card.blade.php +
         filter-post.blade.php) và 1 khối <style> riêng đè các class tô màu CỨNG bằng hex tính từ
         PHP $primaryColor (.bgCate/.bgButton/.pagiItem/.paginate/.resetFilter..., post-page.blade.php)
         — 2 cơ chế màu khác nhau cùng tồn tại trong component này nên phải đè cả hai mới đủ. --}}
    <div class="min-h-screen bg-gray-50 px-4 py-6 sm:px-6 lg:px-8 tin-tuc-brand-scope" style="--color-primary: #0B6B3A;">
        <div class="mx-auto max-w-7xl">
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Tin tức</h1>
                <p class="text-sm text-gray-500">Cập nhật những thông tin mới nhất từ {{ config('app.name') }}.</p>
            </div>

            @livewire('bladethemev1::post-page', [
                'config' => [
                    'component' => [
                        'show_sidebar' => false,
                        'columns' => 3,
                        'number-post' => 9,
                        'show_pagination' => true,
                        'location-sidebar' => '',
                        'new_post' => '0',
                        'post_category' => '0',
                        'link_social' => '',
                    ],
                ],
            ], key('posts-page'))
        </div>
    </div>

    <style>
        .tin-tuc-brand-scope .bgCate,
        .tin-tuc-brand-scope .bgButton,
        .tin-tuc-brand-scope .paginate {
            color: #0B6B3A !important;
            border-color: #0B6B3A !important;
        }
        .tin-tuc-brand-scope .bgCate:hover,
        .tin-tuc-brand-scope .bgButton:hover,
        .tin-tuc-brand-scope .paginate:hover,
        .tin-tuc-brand-scope .bgPrimaWhite,
        .tin-tuc-brand-scope .pagiItem,
        .tin-tuc-brand-scope .resetFilter {
            background-color: #0B6B3A !important;
            border-color: #0B6B3A !important;
            color: #fff !important;
        }
        .tin-tuc-brand-scope .filter-container::-webkit-scrollbar-thumb {
            background: #0B6B3A !important;
        }
    </style>
@endsection
