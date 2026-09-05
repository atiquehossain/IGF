@php
    $ui ??= static fn (string $key, array $replace = []): string => \App\Support\AdminUi::text("application_forms.{$key}", $replace);
    $fieldUi = static fn (string $key, array $replace = []): string => \App\Support\AdminUi::text("application_forms.{$key}", $replace, $locale);
    $fieldId = 'afb-preview-field-' . $fieldIndex;
    $optionName = 'preview[' . $field['key'] . ']';
    $hasConditions = !empty($field['conditions']);
@endphp
<fieldset class="afb-preview-field" data-preview-field="{{ $field['key'] }}" @if($hasConditions) data-has-conditions="1" @endif>
    <legend id="{{ $fieldId }}-label">{{ $field['label'] }} @if($field['required'])<span class="afb-required" aria-label="{{ $fieldUi('preview.required') }}">*</span>@endif</legend>
    @if($field['help'] !== '')<p id="{{ $fieldId }}-help" class="afb-field-help">{{ $field['help'] }}</p>@endif
    @if($hasConditions)<p class="afb-condition-note"><i class="fa fa-random" aria-hidden="true"></i> {{ $ui('preview.conditional') }}</p>@endif

    @switch($field['type'])
        @case('long_text')
            <textarea class="form-control" name="{{ $optionName }}" placeholder="{{ $field['placeholder'] }}" @if($field['help'] !== '') aria-describedby="{{ $fieldId }}-help" @endif></textarea>
            @break
        @case('dropdown')
            <select class="form-control" name="{{ $optionName }}" @if($field['help'] !== '') aria-describedby="{{ $fieldId }}-help" @endif>
                <option value="">{{ $field['placeholder'] ?: $fieldUi('preview.choose_option') }}</option>
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
                <label><input type="radio" name="{{ $optionName }}" value="yes"> <span>{{ $fieldUi('preview.yes') }}</span></label>
                <label><input type="radio" name="{{ $optionName }}" value="no"> <span>{{ $fieldUi('preview.no') }}</span></label>
            </div>
            @break
        @case('file')
            <input class="form-control" type="file" accept="application/pdf,.pdf" disabled data-preview-locked="1" aria-describedby="{{ $fieldId }}-file-help">
            <small id="{{ $fieldId }}-file-help">{{ $fieldUi('preview.file_help') }}</small>
            @break
        @default
            <input class="form-control" type="{{ match($field['type']) { 'email' => 'email', 'phone' => 'tel', 'number' => 'number', 'date' => 'date', default => 'text' } }}" name="{{ $optionName }}" placeholder="{{ $field['placeholder'] }}" @if($field['help'] !== '') aria-describedby="{{ $fieldId }}-help" @endif>
    @endswitch
</fieldset>
