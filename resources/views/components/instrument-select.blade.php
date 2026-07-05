@props([
    'id', 
    'label', 
    'icon' => 'file-text',
    'options' => [],
    'value' => '',
    'placeholder' => 'Select an option',
    'optionValue' => 'value',
    'optionLabel' => 'label'
])

<div {{ $attributes->merge(['class' => '']) }}>
    @if(!empty($label))
        <label id="{{ $id }}-label" for="{{ $id }}" class="block text-sm font-medium text-gray-700 mb-1">{{ $label }}</label>
    @endif
    <div class="relative">
        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <i data-lucide="{{ $icon }}" class="h-4 w-4 text-gray-400"></i>
        </div>
        <select id="{{ $id }}" name="{{ $id }}"
            class="w-full pl-10 px-4 py-2.5 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-100 focus:border-blue-400 outline-none transition-all text-sm appearance-none">
            @if($placeholder)
                <option value="">{{ $placeholder }}</option>
            @endif
            @php
                // Check if the value exists in options
                $valueExistsInOptions = false;
                $optionsArray = [];
                $seenOther = false; // collapse duplicate "Other"/"Others" into a single option
                $seenValues = [];

                foreach($options as $option) {
                    if (is_object($option)) {
                        $currentValue = $option->{$optionValue} ?? $option->StateName ?? $option->id ?? '';
                        $currentLabel = $option->{$optionLabel} ?? $option->StateName ?? $option->name ?? '';
                    } else {
                        $currentValue = $option;
                        $currentLabel = $option;
                    }

                    $normalized = strtolower(trim((string)$currentValue));

                    // Skip a second "Other"/"Others" entry (keeps the first occurrence)
                    if (in_array($normalized, ['other', 'others'], true)) {
                        if ($seenOther) {
                            continue;
                        }
                        $seenOther = true;
                    }

                    // Skip any exact (case-insensitive) duplicate value
                    if ($normalized !== '' && isset($seenValues[$normalized])) {
                        continue;
                    }
                    $seenValues[$normalized] = true;

                    $optionsArray[] = ['value' => $currentValue, 'label' => $currentLabel];

                    if ((string)$value === (string)$currentValue) {
                        $valueExistsInOptions = true;
                    }
                }
                
                // If value doesn't exist in options and is not empty, add it
                if (!empty($value) && !$valueExistsInOptions) {
                    array_unshift($optionsArray, ['value' => $value, 'label' => $value]);
                }
            @endphp
            <!-- DEBUG: value="{{ $value }}", optionsCount={{ count($optionsArray) }}, valueExists={{ $valueExistsInOptions ? 'yes' : 'no' }} -->
            @foreach($optionsArray as $opt)
                <option value="{{ $opt['value'] }}" {{ (string)$value === (string)$opt['value'] ? 'selected' : '' }}>
                    {{ $opt['label'] }}
                </option>
            @endforeach
        </select>
        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
            <i data-lucide="chevron-down" class="h-4 w-4 text-gray-400"></i>
        </div>
    </div>
</div>
