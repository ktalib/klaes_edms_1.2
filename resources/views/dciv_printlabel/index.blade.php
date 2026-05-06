@extends('layouts.app')

@section('page-title')
    {{ __('DCIV Print File Labels') }}
@endsection

@section('content')
    @include('dciv_printlabel.assets.head')

    <div class="flex-1 overflow-auto">
        @include('admin.header')

        @include('dciv_printlabel.partials.page')

        @include('admin.footer')
    </div>
@endsection


@section('footer-scripts')
    @parent
    @include('dciv_printlabel.assets.js')
@endsection
