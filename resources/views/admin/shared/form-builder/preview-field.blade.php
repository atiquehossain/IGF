@php
    $fieldId = 'afb-preview-field-' . $fieldIndex;
    $optionName = 'preview[' . $field['key'] . ']';
    $hasConditions = !empty($field['conditions']);
@endphp
<fieldset class="afb-preview-field" data-preview-field="{{ $field['key'] }}" @if($hasConditions) data-has-conditions="1" @endif>
    <legend id="{{ $fieldId }}-label">{{ $field['label'] }} @if($field['required'])<span class="afb-required" aria-label="required">*</span>@endif</legend>
    @if($field['help'] !== '')<p id="{{ $fieldId }}-help" class="afb-field-help">{{ $field['help'] }}</p>@endif
    @if($hasConditions)<p class="afb-condition-note"><i class="fa fa-random" aria-hidden="true"></i> This question is shown conditionally.</p>@endif

    @switch($field['type'])
        @case('long_text')
            <textarea class="form-control" name="{{ $optionName }}" placeholder="{{ $field['placeholder'] }}" @if($field['help'] !== '') aria-describedby="{{ $fieldId }}-help" @endif></textarea>
            @break
        @case('dropdown')
            <select class="form-control" name="{{ $optionName }}" @if($field['help'] !== '') aria-describedby="{{ $fieldId }}-help" @endif>
                <option value="">{{ $field['placeholder'] ?: ($locale === 'bn' ? 'একটি নির্বাচন করুন' : 'Choose an option') }}</option>
                @foreach($field['options'] as $option)<option value="{{ $option['value'] }}">{{ $option['label'] }}</option>@endforeach
            </select>
            @break
        @case('radio')
            <div class="afb-preview-choices">
                @foreach($field['options'] as $option)
                    <label><input type="radio" name="{{ $optionName }}" value="{{ $option['value'] }}"> <span>{{ $option['label'] }}</span></label>
                @endforeach
            </div>
            @break
        @case('checkboxes')
            <div class="afb-preview-choices">
                @foreach($field['options'] as $option)
                    <label><input type="checkbox" name="{{ $optionName }}[]" value="{{ $option['value'] }}"> <span>{{ $option['label'] }}</span></label>
                @endforeach
            </div>
            @break
        @case('yes_no')
            <div class="afb-preview-choices afb-preview-choices--inline">
                <label><input type="radio" name="{{ $optionName }}" value="yes"> <span>{{ $locale === 'bn' ? 'হ্যাঁ' : 'Yes' }}</span></label>
                <label><input type="radio" name="{{ $optionName }}" value="no"> <span>{{ $locale === 'bn' ? 'না' : 'No' }}</span></label>
            </div>
            @break
        @case('file')
            <input class="form-control" type="file" accept="application/pdf,.pdf" disabled data-preview-locked="1" aria-describedby="{{ $fieldId }}-file-help">
            <small id="{{ $fieldId }}-file-help">PDF only, maximum 5 MB. File selection is disabled in preview.</small>
            @break
        @default
            <input class="form-control" type="{{ match($field['type']) { 'email' => 'email', 'phone' => 'tel', 'number' => 'number', 'date' => 'date', default => 'text' } }}" name="{{ $optionName }}" placeholder="{{ $field['placeholder'] }}" @if($field['help'] !== '') aria-describedby="{{ $fieldId }}-help" @endif>
    @endswitch
</fieldset>
