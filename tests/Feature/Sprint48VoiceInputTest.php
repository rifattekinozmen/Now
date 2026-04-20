<?php

it('voice input component renders mic button when feature is enabled', function (): void {
    config(['app.voice_input_enabled' => true]);

    $view = $this->blade(
        '<x-voice-input target="description" :label="\'Description\'" />',
    );

    $view->assertSee('toggle()', false);
    $view->assertSee('SpeechRecognition', false);
    $view->assertSee('wire:model', false);
});

it('voice input component renders plain textarea fallback when feature is disabled', function (): void {
    config(['app.voice_input_enabled' => false]);

    $view = $this->blade(
        '<x-voice-input target="description" :label="\'Description\'" />',
    );

    $view->assertDontSee('toggle()', false);
    $view->assertDontSee('SpeechRecognition', false);
    $view->assertSee('wire:model', false);
});
