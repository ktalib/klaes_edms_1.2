@extends('layouts.app')
@section('page-title')
    {{ __('Template') }}
@endsection
 
@section('content')
    <div class="flex-1 overflow-auto">
        @include('admin.header')

        <div class="max-w-5xl mx-auto px-6 py-12">
            <h1 class="text-2xl font-semibold text-gray-900">Hello World</h1>
            <p class="mt-4 text-gray-600">Welcome to the File History View page.</p>
        </div>

        @include('admin.footer')
    </div>
@endsection
