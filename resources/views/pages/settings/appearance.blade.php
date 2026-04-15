<?php

use Livewire\Component;
use Livewire\Attributes\Title;

new #[Title('Appearance settings')] class extends Component {
    //
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Appearance settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Appearance')" :subheading="__('Update the appearance settings for your account')">
        <flux:radio.group x-data variant="segmented" x-model="$flux.appearance">
            <flux:radio value="light" icon="sun">{{ __('Light') }}</flux:radio>
            <flux:radio value="dark" icon="moon">{{ __('Dark') }}</flux:radio>
            <flux:radio value="system" icon="computer-desktop">{{ __('System') }}</flux:radio>
        </flux:radio.group>

        <div class="mt-8 pt-6 border-t border-zinc-200 dark:border-zinc-700">
            <flux:heading size="sm" class="mb-1">{{ __('Color Theme') }}</flux:heading>
            <flux:subheading class="mb-4">{{ __('Choose an accent color for the interface') }}</flux:subheading>

            <div x-data="{
                get colorTheme() { return localStorage.getItem('now.color-theme') || 'default'; },
                set colorTheme(val) {
                    localStorage.setItem('now.color-theme', val);
                    val === 'default'
                        ? document.documentElement.removeAttribute('data-theme')
                        : document.documentElement.setAttribute('data-theme', val);
                }
            }">
                <flux:radio.group variant="segmented" x-model="colorTheme">
                    <flux:radio value="default">{{ __('Default') }}</flux:radio>
                    <flux:radio value="brand">{{ __('Brand') }}</flux:radio>
                    <flux:radio value="ocean">{{ __('Ocean') }}</flux:radio>
                    <flux:radio value="warm">{{ __('Warm') }}</flux:radio>
                </flux:radio.group>
            </div>
        </div>
    </x-pages::settings.layout>
</section>
