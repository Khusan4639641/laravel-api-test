<?php

namespace Tests\Feature;

use App\Models\Faq;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaqLanguageTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_faqs_return_localized_question_and_answer(): void
    {
        Faq::query()->create([
            'category' => 'О компании',
            'question' => 'Что такое Safi Life?',
            'answer' => 'Русский ответ',
            'status' => 'active',
            'is_active' => true,
            'category_translations' => ['ru' => 'О компании', 'kk' => 'Компания туралы', 'kz' => 'KZ компания туралы', 'kg' => 'Компания жөнүндө'],
            'question_translations' => ['ru' => 'Что такое Safi Life?', 'kk' => 'Safi Life деген не?', 'kz' => 'KZ Safi Life деген не?', 'kg' => 'Safi Life деген эмне?'],
            'answer_translations' => ['ru' => 'Русский ответ', 'kk' => 'Қазақша жауап', 'kz' => 'KZ қазақша жауап', 'kg' => 'Кыргызча жооп'],
        ]);

        $this->getJson('/api/public/faqs', ['Accept-Language' => 'kk'])
            ->assertOk()
            ->assertJsonPath('faqs.0.category', 'KZ компания туралы')
            ->assertJsonPath('faqs.0.question', 'KZ Safi Life деген не?')
            ->assertJsonPath('faqs.0.answer', 'KZ қазақша жауап');

        $this->getJson('/api/public/faqs', ['Accept-Language' => 'kz'])
            ->assertOk()
            ->assertJsonPath('faqs.0.category', 'KZ компания туралы')
            ->assertJsonPath('faqs.0.question', 'KZ Safi Life деген не?')
            ->assertJsonPath('faqs.0.answer', 'KZ қазақша жауап');

        $this->getJson('/api/public/faqs', ['Accept-Language' => 'kg'])
            ->assertOk()
            ->assertJsonPath('faqs.0.category', 'Компания жөнүндө')
            ->assertJsonPath('faqs.0.question', 'Safi Life деген эмне?')
            ->assertJsonPath('faqs.0.answer', 'Кыргызча жооп');
    }

    public function test_legacy_kk_faq_translation_is_used_for_kz_when_explicit_kz_is_missing(): void
    {
        Faq::query()->create([
            'category' => 'О компании',
            'question' => 'Что такое Safi Life?',
            'answer' => 'Русский ответ',
            'status' => 'active',
            'is_active' => true,
            'category_translations' => ['ru' => 'О компании', 'kk' => 'Legacy компания туралы'],
            'question_translations' => ['ru' => 'Что такое Safi Life?', 'kk' => 'Legacy Safi Life деген не?'],
            'answer_translations' => ['ru' => 'Русский ответ', 'kk' => 'Legacy қазақша жауап'],
        ]);

        $this->getJson('/api/public/faqs', ['Accept-Language' => 'kz'])
            ->assertOk()
            ->assertJsonPath('faqs.0.category', 'Legacy компания туралы')
            ->assertJsonPath('faqs.0.question', 'Legacy Safi Life деген не?')
            ->assertJsonPath('faqs.0.answer', 'Legacy қазақша жауап');
    }
}
