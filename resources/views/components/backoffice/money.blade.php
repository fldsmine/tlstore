{{-- Money figure rendered from integer minor units in a given currency.
     Never performs currency conversion. Shows full precision grouped value. --}}
@props(['minor' => 0, 'currency' => 'NGN', 'class' => ''])

<span {{ $attributes->merge(['class' => $class]) }} title="{{ \App\Support\BackofficeFormat::moneyMinor((int) $minor, $currency) }}">{{ \App\Support\BackofficeFormat::moneyMinor((int) $minor, $currency) }}</span>
