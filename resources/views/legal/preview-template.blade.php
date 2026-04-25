@props(['template'])
<div style="max-height: 70vh; overflow-y: auto; padding: 1rem; border: 1px solid #e5e7eb; border-radius: 0.5rem; background: white; color: #111827;">
    <h2 style="margin-bottom: 1rem; font-size: 1.25rem; font-weight: 700;">{{ $template->title }}</h2>
    <div style="font-size: 0.85rem; color: #6b7280; margin-bottom: 1rem;">
        {{ $template->kind }} &middot; {{ $template->language }} &middot; v{{ $template->version }}
        @if($template->is_published)
            &middot; <span style="color: #047857;">Publié le {{ optional($template->published_at)->format('d/m/Y H:i') }}</span>
        @else
            &middot; <span style="color: #b91c1c;">Brouillon</span>
        @endif
    </div>
    <div class="prose max-w-none">
        {!! $template->body_html !!}
    </div>
</div>
