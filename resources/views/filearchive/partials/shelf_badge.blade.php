{{--
    Shelf/rack chip shown beside the file number on a card header.

    `shelf_location` may be recorded on the file or derived from the
    shelf_rack_ranges map by ShelfRackLocator, which flags the difference on
    `shelf_is_derived`. Derived values carry a known rack-level drift for part of
    the workbook set, so they are marked with a trailing dot rather than being
    presented as confirmed. Renders nothing when there is no shelf either way.
--}}
@php
    $shelfLabel = trim((string) ($file->shelf_location ?? ''));
    $shelfIsDerived = !empty($file->shelf_is_derived);
@endphp
@if($shelfLabel !== '')
    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold leading-none bg-white/25 text-white whitespace-nowrap"
          title="Shelf/Rack {{ $shelfLabel }}{{ $shelfIsDerived ? ' — derived from the shelf map, not recorded on the file' : '' }}">
        {{ $shelfLabel }}@if($shelfIsDerived)<span class="ml-0.5 opacity-70">&bull;</span>@endif
    </span>
@endif
