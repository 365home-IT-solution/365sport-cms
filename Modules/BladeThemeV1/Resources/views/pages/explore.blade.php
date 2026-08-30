@extends('bladethemev1::layouts.master')

<x-bladethemev1::seo :seoData="$seoData" />

@section('content')
    {{-- Trước đây không có header nào (dòng @livewire('bladethemev1::header') cũ bị comment tắt,
         cũng chưa từng thêm top-bar/search-bar mới) — trang Khám phá không có cách nào điều hướng
         sang trang khác, phải bấm Back trình duyệt (bug đã gặp). Thêm đúng bộ top-bar + search-bar
         dùng chung với mọi trang khác trong site. --}}
    <x-bladethemev1::home.top-bar />
    <x-bladethemev1::home.search-bar />
    @livewire('bladethemev1::drawer-menu')

    @livewire('bladethemev1::explore-feed')

    @livewire('bladethemev1::footer')
    @livewire('bladethemev1::contact-link')
    @livewire('bladethemev1::notification')
@endsection
