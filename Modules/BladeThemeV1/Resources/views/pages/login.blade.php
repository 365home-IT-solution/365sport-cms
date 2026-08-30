@extends('bladethemev1::layouts.master')

<x-bladethemev1::seo :seoData="$seoData" />

@section('content')
    {{-- Trang đăng nhập: nền gradient xanh lá + card trắng căn giữa, theo giao diện mẫu tham
         khảo (khác bố cục 2 cột Go2Joy trước đây). Header/drawer-menu vẫn ẩn hẳn — trang đăng
         nhập không cần thanh điều hướng đầy đủ. --}}
    <script>
        window.__headerAlwaysCompact = true;
        window.__appBannerDisabled = true;
    </script>

    <div class="relative min-h-[calc(100dvh-76px)] overflow-hidden px-4 py-10 lg:min-h-screen"
         style="background: linear-gradient(160deg, #0B6B3A 0%, #128A4A 45%, #1AA859 100%);">
        {{-- Hoạ tiết sóng trang trí, tĩnh, không ảnh hưởng logic --}}
        <svg class="pointer-events-none absolute -left-24 -top-24 h-72 w-72 text-white/10" fill="currentColor" viewBox="0 0 200 200">
            <path d="M45.3,-58.5C58.5,-49.6,68.6,-34.9,72.7,-18.5C76.8,-2.1,74.9,16,66.7,30.5C58.5,45,44,55.9,27.9,62.3C11.8,68.7,-5.9,70.6,-22.1,66.1C-38.3,61.6,-53,50.7,-61.9,36C-70.8,21.3,-73.9,2.8,-70.1,-13.8C-66.3,-30.4,-55.6,-45.1,-41.8,-54C-28,-62.9,-14,-66,1.6,-68C17.2,-70,34.4,-70.9,45.3,-58.5Z" transform="translate(100 100)"/>
        </svg>
        <svg class="pointer-events-none absolute -bottom-32 -right-24 h-96 w-96 text-white/10" fill="currentColor" viewBox="0 0 200 200">
            <path d="M45.3,-58.5C58.5,-49.6,68.6,-34.9,72.7,-18.5C76.8,-2.1,74.9,16,66.7,30.5C58.5,45,44,55.9,27.9,62.3C11.8,68.7,-5.9,70.6,-22.1,66.1C-38.3,61.6,-53,50.7,-61.9,36C-70.8,21.3,-73.9,2.8,-70.1,-13.8C-66.3,-30.4,-55.6,-45.1,-41.8,-54C-28,-62.9,-14,-66,1.6,-68C17.2,-70,34.4,-70.9,45.3,-58.5Z" transform="translate(100 100)"/>
        </svg>

        <div class="relative mx-auto flex max-w-md flex-col items-center">
            <a href="/" class="mb-6 flex items-center gap-2 text-white">
                <span class="text-lg font-extrabold tracking-wide">{{ config('app.name', '365 Home') }}</span>
            </a>

            <div class="w-full rounded-2xl bg-white p-6 shadow-xl sm:p-8">
                <x-bladethemev1::auth.form mode="login" :primaryHex="$primaryColor" :textOnPrimary="$textOnPrimary" />
            </div>
        </div>
    </div>

    @livewire('bladethemev1::contact-link')
    @livewire('bladethemev1::notification')
@endsection
