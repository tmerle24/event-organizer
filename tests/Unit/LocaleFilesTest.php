<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Zwei Fehler in den Locale-Dateien haben Produktionswirkung und sind von Hand
 * leicht zu übersehen — deshalb stehen sie hier als Test:
 *
 * 1. Ein unescaptes "@" löst den vue-i18n-"Linked Message"-Parser aus. Die App
 *    wirft dann SyntaxError: Invalid linked format und die Seite bleibt weiß.
 * 2. Ein fehlender Key in einer der fünf Sprachen fällt im Alltag nicht auf,
 *    weil der Fallback greift — bis jemand die Sprache wechselt.
 */
class LocaleFilesTest extends TestCase
{
    private const LOCALES = ['de', 'en', 'fr', 'es', 'nl'];

    private function load(string $locale): array
    {
        $path = __DIR__.'/../../resources/js/i18n/locales/'.$locale.'.json';

        return json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }

    /** @return array<string, string> */
    private function flatten(array $data, string $prefix = ''): array
    {
        $out = [];

        foreach ($data as $key => $value) {
            $full = $prefix ? "$prefix.$key" : $key;

            if (is_array($value)) {
                $out += $this->flatten($value, $full);
            } else {
                $out[$full] = $value;
            }
        }

        return $out;
    }

    public function test_all_locales_share_the_same_keys(): void
    {
        $base = array_keys($this->flatten($this->load('de')));

        foreach (array_slice(self::LOCALES, 1) as $locale) {
            $keys = array_keys($this->flatten($this->load($locale)));

            $this->assertSame(
                [],
                array_values(array_diff($base, $keys)),
                "In $locale fehlen Keys, die in de existieren."
            );
            $this->assertSame(
                [],
                array_values(array_diff($keys, $base)),
                "In $locale gibt es Keys, die in de fehlen."
            );
        }
    }

    public function test_no_locale_string_contains_an_unescaped_at_sign(): void
    {
        foreach (self::LOCALES as $locale) {
            foreach ($this->flatten($this->load($locale)) as $key => $value) {
                // Erlaubt ist ausschliesslich die escapte Form {'@'}.
                $withoutEscapes = str_replace("{'@'}", '', $value);

                $this->assertStringNotContainsString(
                    '@',
                    $withoutEscapes,
                    "$locale.$key enthält ein unescaptes @ — vue-i18n wirft dann 'Invalid linked format'."
                );
            }
        }
    }

    public function test_plural_messages_have_a_form_for_every_branch(): void
    {
        foreach (self::LOCALES as $locale) {
            foreach ($this->flatten($this->load($locale)) as $key => $value) {
                if (! str_contains($value, '|')) {
                    continue;
                }

                $forms = array_map('trim', explode('|', $value));

                $this->assertGreaterThanOrEqual(2, count($forms), "$locale.$key hat nur eine Pluralform.");
                foreach ($forms as $form) {
                    $this->assertNotSame('', $form, "$locale.$key hat eine leere Pluralform.");
                }
            }
        }
    }
}
