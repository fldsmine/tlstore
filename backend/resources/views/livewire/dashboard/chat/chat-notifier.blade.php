{{-- Reverb ping (getListeners echo) + 30s poll fallback both call check(). Invisible. --}}
<div wire:poll.15s="check" class="hidden" aria-hidden="true"></div>
