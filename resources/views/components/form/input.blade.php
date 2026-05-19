@props([
    'label'    => '',
    'name'     => '',
    'type'     => 'text',
    'hint'     => '',
    'required' => false,
])

<div class="form-group">
    @if ($label)
        <label for="{{ $name }}" class="form-label">
            {{ $label }}
            @if ($required) <span class="text-red-500">*</span> @endif
        </label>
    @endif

    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        {{ $attributes->class(['form-input', 'border-red-400' => $errors->has($name)]) }}
    />

    @error($name)
        <p class="form-error">{{ $message }}</p>
    @enderror

    @if ($hint)
        <p class="form-hint">{{ $hint }}</p>
    @endif
</div>
