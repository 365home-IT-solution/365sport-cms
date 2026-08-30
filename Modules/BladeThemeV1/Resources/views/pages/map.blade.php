@extends('bladethemev1::layouts.master')

<x-bladethemev1::seo :seoData="$seoData" />

@section('content')
    <script>
        window.__headerAlwaysCompact = true;
        window.__appBannerDisabled = true;
    </script>

    <x-bladethemev1::home.top-bar />
    <x-bladethemev1::home.search-bar />
    @livewire('bladethemev1::drawer-menu')

    @livewire('bladethemev1::map-page')
@endsection
