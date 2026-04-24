@php
    $locales = config('locales.enabled', []);
    $current = app()->getLocale();
@endphp

@if(count($locales) > 1)
    <div class="flex items-center justify-center gap-1 px-3 py-2 border-t border-slate-200 dark:border-gray-700">
        @foreach($locales as $code => $meta)
            <form method="POST" action="{{ url('/locale/' . $code) }}" class="inline">
                @csrf
                <button
                    type="submit"
                    title="{{ $meta['label'] }}"
                    class="px-2 py-1 rounded text-xs transition
                        {{ $current === $code
                            ? 'bg-red-50 text-red-700 font-semibold'
                            : 'text-slate-500 hover:text-slate-800 hover:bg-slate-50' }}"
                >
                    <span class="mr-1">{{ $meta['flag'] }}</span>
                    <span class="uppercase">{{ $code }}</span>
                </button>
            </form>
        @endforeach
    </div>
@endif
