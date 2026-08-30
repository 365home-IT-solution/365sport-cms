@extends('bladethemev1::layouts.master')

@section('title', 'Quỹ nhóm góp tiền')

@section('content')
    <x-bladethemev1::home.top-bar />
    <x-bladethemev1::home.search-bar />
    @livewire('bladethemev1::drawer-menu')

    @livewire('bladethemev1::booking-fund.fund-detail', ['code' => $code])

    @livewire('bladethemev1::footer')
    @livewire('bladethemev1::contact-link')
    @livewire('bladethemev1::notification')
@endsection
