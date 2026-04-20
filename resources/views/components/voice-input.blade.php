{{--
    Voice Input Component — Web Speech API + Alpine.js

    Usage:
      <x-voice-input target="description" :label="__('Description')" rows="3" />

    Props:
      target  — Livewire model name to bind (wire:model)
      label   — Optional label text
      rows    — Textarea rows (default 3)

    Feature flag: config('app.voice_input_enabled') — defaults to true.
    Set VOICE_INPUT_ENABLED=false in .env to disable globally.
--}}

@props(['target', 'label' => null, 'rows' => 3])

@if (config('app.voice_input_enabled', true))
    <div
        {{ $attributes->class(['flex flex-col gap-1']) }}
        x-data="{
            listening: false,
            supported: typeof window !== 'undefined' && ('SpeechRecognition' in window || 'webkitSpeechRecognition' in window),
            recognition: null,

            init() {
                if (!this.supported) return;
                const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
                this.recognition = new SR();
                this.recognition.continuous = false;
                this.recognition.interimResults = false;
                this.recognition.lang = document.documentElement.lang || 'tr-TR';

                this.recognition.onresult = (event) => {
                    const transcript = event.results[0][0].transcript;
                    const el = this.$el.querySelector('textarea');
                    if (el) {
                        const current = el.value;
                        el.value = current ? current + ' ' + transcript : transcript;
                        el.dispatchEvent(new Event('input'));
                    }
                    this.listening = false;
                };

                this.recognition.onerror = () => { this.listening = false; };
                this.recognition.onend = () => { this.listening = false; };
            },

            toggle() {
                if (!this.supported) return;
                if (this.listening) {
                    this.recognition.stop();
                    this.listening = false;
                } else {
                    this.recognition.start();
                    this.listening = true;
                }
            }
        }"
    >
        @if ($label)
            <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ $label }}</label>
        @endif

        <div class="relative">
            <textarea
                wire:model="{{ $target }}"
                rows="{{ $rows }}"
                class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 pr-10 text-sm text-zinc-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100"
            ></textarea>

            {{-- Mic button --}}
            <button
                type="button"
                @click="toggle()"
                x-show="supported"
                :title="listening ? '{{ __('Stop listening') }}' : '{{ __('Speak') }}'"
                :class="listening
                    ? 'text-red-500 animate-pulse hover:text-red-600'
                    : 'text-zinc-400 hover:text-zinc-600 dark:text-zinc-500 dark:hover:text-zinc-300'"
                class="absolute right-2 top-2 rounded p-1 transition"
            >
                <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 0 0 6-6v-1.5m-6 7.5a6 6 0 0 1-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 0 1-3-3V4.5a3 3 0 1 1 6 0v8.25a3 3 0 0 1-3 3Z" />
                </svg>
            </button>
        </div>

        <p x-show="listening" class="animate-pulse text-xs text-red-500">{{ __('Listening…') }}</p>
    </div>
@else
    {{-- Fallback: plain textarea without voice --}}
    <div {{ $attributes->class(['flex flex-col gap-1']) }}>
        @if ($label)
            <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ $label }}</label>
        @endif
        <textarea
            wire:model="{{ $target }}"
            rows="{{ $rows }}"
            class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100"
        ></textarea>
    </div>
@endif
