@props(['output'])

@php
    // Escape sequences are stripped for display only; the stored record keeps
    // exactly what the process wrote.
    $clean = \Bityukov\CommandCenter\Support\Ansi::strip($output);
@endphp

<pre class="fi-fo-code-editor" style="max-height: 32rem; overflow: auto; margin: 0; padding: 0.75rem; font-size: 0.75rem; line-height: 1.5;">{{ $clean === '' ? 'No output.' : $clean }}</pre>
