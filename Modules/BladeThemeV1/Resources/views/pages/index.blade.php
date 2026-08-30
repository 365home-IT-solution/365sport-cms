@extends('bladethemev1::layouts.master')

<x-bladethemev1::seo :seoData="$seoData"/>

@section('content')

    {{-- Top-bar xanh (giao diện mẫu ALO Booking) thay cho header cũ trên MỌI trang — header.blade.php
         (bladethemev1::header) không còn được include ở đây; header.blade.php tự render
         location-modal nên thêm lại ở đây (áp dụng chung cho mọi trang, không riêng trang chủ). --}}
    <x-bladethemev1::home.top-bar />
    <x-bladethemev1::home.search-bar />
    @livewire('bladethemev1::drawer-menu')
    @livewire('bladethemev1::location-modal')

    @if (request()->path() === '/')
        {{-- Trang chủ: giao diện theo mẫu tham khảo ALO Booking (datlich.alobo.vn) — xem plan
             "Đổi giao diện public site theo mẫu ALO Booking". --}}
        <x-bladethemev1::home.promo-banner />

        @livewire('bladethemev1::venue-grid')

    @else
        {{-- Các trang khác: render CMS components bình thường --}}
        <div>
            @foreach ($configuration as $config)
                <x-bladethemev1::layout :page="$page" :componentName="$config['component']['name']" :loop="$loop" :config="$config['layout']" :primaryColor="$primaryColor">
                    @isset($config['component'])
                        @livewire('bladethemev1::' . $config['component']['name'], ['config' => $config, 'loop' => $loop])
                    @endisset
                </x-bladethemev1::layout>
            @endforeach
        </div>
    @endif

    @livewire('bladethemev1::footer')
    @livewire('bladethemev1::contact-link')
    @livewire('bladethemev1::notification')
@endsection

<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.documentElement.style.setProperty('--swiper-theme-color', @json($primaryColor));
    });
</script>
