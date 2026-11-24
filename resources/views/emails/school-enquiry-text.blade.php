{{-- Plain text, so the school's own words go in unescaped: escaping would show &#039; for an apostrophe. --}}
A school asked about SlipNote.

School:    {!! $enquiry['school'] !!}
@if ($enquiry['from'])
From:      {!! $enquiry['from'] !!}
@endif
@if ($enquiry['classes'])
Classes:   {{ $enquiry['classes'] }}
@endif
Reply to:  {!! $contact !!}
@if ($enquiry['ref'])
Came via:  {{ \App\Models\Ambassador::refLabel($enquiry['ref']) }}
@endif
@if ($enquiry['message'] !== '')

What they wrote:

{!! $enquiry['message'] !!}
@endif

It's also in the operator dashboard:
{{ $dashboardUrl }}

SlipNote
