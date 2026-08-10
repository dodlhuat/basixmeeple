<x-mail::message>
# Du wurdest eingeladen

{{ $inviterName }} hat dich zur Sammlung **{{ $collectionName }}** auf BasixMeeple eingeladen.

<x-mail::button :url="$registrationUrl">
Einladung annehmen
</x-mail::button>

Dieser Link ist 7 Tage gültig.

Danke,<br>
{{ config('app.name') }}
</x-mail::message>
