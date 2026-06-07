@extends('layouts.app')

@section('page-title', $PageTitle)

@section('content')
<div class="flex-1 overflow-auto bg-slate-50 flex flex-col min-h-full">
    @include('admin.header', ['PageTitle' => $PageTitle, 'PageDescription' => 'PHS usage and revenue overview.'])
    <div class="flex-1 p-6">
        <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="rounded-lg border border-slate-200 bg-white p-5"><p class="text-xs font-bold uppercase text-slate-500">Revenue</p><p class="mt-2 text-2xl font-extrabold">NGN {{ number_format($revenue, 2) }}</p></div>
            <div class="rounded-lg border border-slate-200 bg-white p-5"><p class="text-xs font-bold uppercase text-slate-500">Tokens sold</p><p class="mt-2 text-2xl font-extrabold">{{ number_format($tokensSold) }}</p></div>
            <div class="rounded-lg border border-slate-200 bg-white p-5"><p class="text-xs font-bold uppercase text-slate-500">Searches run</p><p class="mt-2 text-2xl font-extrabold">{{ number_format($searchesRun) }}</p></div>
        </div>
        <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs font-bold uppercase text-slate-500"><tr><th class="px-4 py-3">Institution</th><th class="px-4 py-3">Query</th><th class="px-4 py-3">File</th><th class="px-4 py-3">Results</th><th class="px-4 py-3">Reference</th><th class="px-4 py-3">Date</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($recentSearches as $row)
                        <tr><td class="px-4 py-3 font-bold">{{ $row->institution_name }}</td><td class="px-4 py-3">{{ $row->query }}</td><td class="px-4 py-3">{{ $row->file_number }}</td><td class="px-4 py-3">{{ $row->result_count }}</td><td class="px-4 py-3">{{ $row->reference_no }}</td><td class="px-4 py-3">{{ $row->created_at }}</td></tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">No searches recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @include('admin.footer')
</div>
@endsection
