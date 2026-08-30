@extends('bladethemev1::layouts.master')

<x-bladethemev1::seo :seoData="$seoData"/>

@section('content')

    {{-- @livewire('bladethemev1::header') --}}
    @livewire('bladethemev1::drawer-menu')

    @livewire('bladethemev1::location-modal')

    {{-- Giao diện trang chủ theo mẫu tham khảo ALO Booking (datlich.alobo.vn): top-bar xanh +
         thanh tìm kiếm nhanh phía trên form tìm kiếm nâng cao (hero-section) hiện có. --}}
    <x-bladethemev1::home.top-bar />
    <x-bladethemev1::home.search-bar />

    {{-- Hero Section (form tìm kiếm nâng cao theo ngày/giờ/khách — giữ nguyên, không đổi logic) --}}
    @livewire('bladethemev1::hero-section')

    <x-bladethemev1::home.promo-banner />

    {{-- Lưới sân/chi nhánh --}}
    @livewire('bladethemev1::venue-grid')

    {{-- Flash Sale --}}
    @livewire('bladethemev1::flash-sale')

    {{-- Voucher & Ưu đãi --}}
    @livewire('bladethemev1::voucher')

    @livewire('bladethemev1::footer')
    @livewire('bladethemev1::contact-link')
    @livewire('bladethemev1::notification')

@endsection

<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.documentElement.style.setProperty('--swiper-theme-color', @json($primaryColor));
    });
</script>
