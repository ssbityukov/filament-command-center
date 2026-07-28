@props(['output'])

{{-- fi-fo-code-editor is Filament's own code surface, so it is styled by the
     panel's compiled CSS. A plugin cannot ship Tailwind utilities of its own:
     the app's Tailwind never scans these views, and the classes are dropped. --}}
<pre class="fi-fo-code-editor" style="max-height: 32rem; overflow: auto; margin: 0; padding: 0.75rem; font-size: 0.75rem; line-height: 1.5;">{{ $output === '' ? 'No output.' : $output }}</pre>
