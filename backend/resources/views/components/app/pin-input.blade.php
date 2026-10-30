{{-- Multi-box PIN entry; defers the combined value to the `target` Livewire prop (sent on the next action, not eagerly). --}}
@props(['target' => 'pin', 'length' => 4, 'autofocus' => true])

<div x-data="{
        digits: Array({{ $length }}).fill(''),
        sync() { $wire.set('{{ $target }}', this.digits.join(''), false); },
        handle(i, e) {
            const v = (e.target.value || '').replace(/[^0-9]/g, '').slice(-1);
            this.digits[i] = v;
            e.target.value = v;
            this.sync();
            if (v && i < {{ $length - 1 }}) this.$refs['b' + (i + 1)]?.focus();
        },
        key(i, e) {
            if (e.key === 'Backspace' && !this.digits[i] && i > 0) {
                this.$refs['b' + (i - 1)]?.focus();
            }
        },
        paste(e) {
            const t = (e.clipboardData.getData('text') || '').replace(/[^0-9]/g, '').slice(0, {{ $length }});
            if (!t) return;
            e.preventDefault();
            for (let i = 0; i < {{ $length }}; i++) {
                this.digits[i] = t[i] || '';
                if (this.$refs['b' + i]) this.$refs['b' + i].value = this.digits[i];
            }
            this.sync();
            this.$refs['b' + Math.min(t.length, {{ $length - 1 }})]?.focus();
        }
     }"
     @if ($autofocus) x-init="$nextTick(() => $refs.b0?.focus())" @endif
     class="flex items-center justify-center gap-3">
    @for ($i = 0; $i < $length; $i++)
        <input type="password" inputmode="numeric" maxlength="1" autocomplete="off"
               x-ref="b{{ $i }}"
               @input="handle({{ $i }}, $event)"
               @keydown="key({{ $i }}, $event)"
               @paste="paste($event)"
               class="size-[58px] rounded-[16px] bg-surface border text-center font-heading font-semibold text-text text-[22px] outline-none transition focus:border-primary focus:ring-1 focus:ring-primary"
               :class="digits[{{ $i }}] ? 'border-primary' : 'border-muted/25'">
    @endfor
</div>
