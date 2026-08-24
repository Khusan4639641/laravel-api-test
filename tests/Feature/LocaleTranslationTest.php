<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\LocalizedValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LocaleTranslationTest extends TestCase
{
    use RefreshDatabase;

    public function test_locale_mapping_uses_canonical_codes_and_russian_fallback(): void
    {
        $this->assertSame('ru', LocalizedValue::normalize('RU'));
        $this->assertSame('kk', LocalizedValue::normalize('KZ'));
        $this->assertSame('ky', LocalizedValue::normalize('KG'));
        $this->assertSame('en', LocalizedValue::normalize('EN'));
        $this->assertSame('mn', LocalizedValue::normalize('MN'));
        $this->assertSame('kk', LocalizedValue::normalize('kk-KZ,ru;q=0.8'));
        $this->assertSame('ru', LocalizedValue::normalize('xx'));
        $this->assertSame(['ru', 'kk', 'ky', 'en', 'mn'], LocalizedValue::LANGUAGES);
    }

    public function test_personal_data_is_identical_for_every_supported_locale(): void
    {
        $user = User::factory()->create([
            'name' => 'Такетова Тансулу Дуйсенбековна',
            'login' => 'EliteTanslu77',
            'email' => 'Taketova.tansulu@mail.ru',
        ]);
        $user->profile()->create([
            'first_name' => 'Такетова',
            'last_name' => 'Тансулу Дуйсенбековна',
            'phone' => '+77078417344',
        ]);
        Sanctum::actingAs($user);

        foreach (LocalizedValue::LANGUAGES as $locale) {
            $this->getJson('/api/me', ['Accept-Language' => $locale])
                ->assertOk()
                ->assertJsonPath('user.name', 'Такетова Тансулу Дуйсенбековна')
                ->assertJsonPath('user.login', 'EliteTanslu77')
                ->assertJsonPath('user.email', 'Taketova.tansulu@mail.ru')
                ->assertJsonPath('user.phone', '+77078417344');
        }
    }

    public function test_kazakh_dictionary_contains_approved_cyrillic_strings(): void
    {
        $dictionary = json_decode((string) file_get_contents(resource_path('js/safi/locales/kk.json')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('Басты бет', data_get($dictionary, 'navigation.home'));
        $this->assertSame('Компания туралы', data_get($dictionary, 'navigation.about'));
        $this->assertSame('Өнімдер', data_get($dictionary, 'navigation.products'));
        $this->assertSame('Мүмкіндіктер', data_get($dictionary, 'navigation.opportunities'));
        $this->assertSame('Маркетинг жоспары', data_get($dictionary, 'navigation.marketingPlan'));
        $this->assertSame('Қалай бастау керек', data_get($dictionary, 'navigation.howToStart'));
        $this->assertSame('Жиі қойылатын сұрақтар', data_get($dictionary, 'navigation.faq'));
        $this->assertSame('Байланыс', data_get($dictionary, 'navigation.contacts'));
        $this->assertSame('Кіру', data_get($dictionary, 'auth.login'));
        $this->assertSame('Жеке кабинет', data_get($dictionary, 'auth.dashboard'));
        $this->assertSame('Жеке кабинет', data_get($dictionary, 'auth.personalCabinet'));
        $this->assertSame('Өз әлеуетіңізді Safi арқылы ашыңыз', data_get($dictionary, 'hero.title'));
        $this->assertSame('Неліктен Safi Life-ты таңдайды?', data_get($dictionary, 'benefits.title'));
        $this->assertSame('Бастапқы пакеттер', data_get($dictionary, 'packages.title'));
        $this->assertSame('Өзіңізге сәйкес пакетті таңдап, Safi Life-пен бірге табыс табуды бастаңыз.', data_get($dictionary, 'packages.subtitle'));

        $serialized = json_encode($dictionary, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        foreach (['ЖАМАН СТАВКА', 'Жаман ставка', 'ТУРАЛ КОМПАНИЯСЫ', 'Турал компаниясы', 'JOSPAR', 'Jospar', 'ШКАФ', 'Шкаф', 'Nege tandaydy', 'èz alueutizdi', 'Бастапқы пакеттеушісі'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $serialized);
        }
    }

    public function test_mobile_and_desktop_header_share_navigation_and_auth_keys(): void
    {
        $header = (string) file_get_contents(resource_path('js/safi/components/layout/Header.tsx'));

        foreach ([
            'navigation.home',
            'navigation.about',
            'navigation.products',
            'navigation.opportunities',
            'navigation.marketingPlan',
            'navigation.howToStart',
            'navigation.faq',
            'navigation.contacts',
            'auth.login',
            'auth.dashboard',
        ] as $key) {
            $this->assertStringContainsString($key, $header);
        }

        $this->assertStringContainsString('PUBLIC_NAVIGATION_ITEMS.map', $header);
        $this->assertStringContainsString('navLinks.map', $header);
        $this->assertStringNotContainsString("t('nav.home'", $header);
        $this->assertStringNotContainsString("t('nav.cabinet'", $header);
    }

    public function test_translation_sources_do_not_contain_forbidden_legacy_strings(): void
    {
        $sources = $this->translationSources();

        foreach ([
            'ЖАМАН СТАВКА',
            'Жаман ставка',
            'ТУРАЛ КОМПАНИЯСЫ',
            'Турал компаниясы',
            'JOSPAR',
            'Jospar',
            'ШКАФ',
            'Шкаф',
            'Nege tandaydy',
            'èz alueutizdi',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $sources);
        }
    }

    public function test_frontend_uses_manual_i18n_without_dom_translator(): void
    {
        $frontend = $this->frontendSources();
        $switcher = (string) file_get_contents(resource_path('js/safi/components/ui/LanguageSwitcher.tsx'));

        $this->assertStringContainsString("{ code: 'ru', label: 'RU' }", $switcher);
        $this->assertStringContainsString("{ code: 'kk', label: 'KZ' }", $switcher);
        $this->assertStringContainsString("{ code: 'ky', label: 'KG' }", $switcher);
        $this->assertStringContainsString("{ code: 'en', label: 'EN' }", $switcher);
        $this->assertStringContainsString("{ code: 'mn', label: 'MN' }", $switcher);

        foreach (['cdn.gtranslate.net', 'translate.google.com', 'googleTranslateElementInit', 'MutationObserver', 'TreeWalker'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $frontend);
        }
    }

    private function frontendSources(): string
    {
        $sources = '';
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('js/safi')));

        foreach ($iterator as $file) {
            if (! $file->isFile() || ! in_array($file->getExtension(), ['ts', 'tsx', 'js', 'jsx'], true)) {
                continue;
            }

            $sources .= file_get_contents($file->getPathname());
        }

        return $sources;
    }

    private function translationSources(): string
    {
        $sources = '';

        foreach ([resource_path('js'), resource_path('views'), base_path('lang')] as $root) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

            foreach ($iterator as $file) {
                if (! $file->isFile() || ! in_array($file->getExtension(), ['ts', 'tsx', 'js', 'jsx', 'json', 'php'], true)) {
                    continue;
                }

                $sources .= file_get_contents($file->getPathname());
            }
        }

        return $sources;
    }
}
