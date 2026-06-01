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
            'category_translations' => ['ru' => 'О компании', 'kk' => 'Компания туралы'],
            'question_translations' => ['ru' => 'Что такое Safi Life?', 'kk' => 'Safi Life деген не?'],
            'answer_translations' => ['ru' => 'Русский ответ', 'kk' => 'Қазақша жауап'],
        ]);

        $this->getJson('/api/public/faqs', ['Accept-Language' => 'kk'])
            ->assertOk()
            ->assertJsonPath('faqs.0.category', 'Компания туралы')
            ->assertJsonPath('faqs.0.question', 'Safi Life деген не?')
            ->assertJsonPath('faqs.0.answer', 'Қазақша жауап');
    }
}
