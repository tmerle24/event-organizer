<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Die Icons kommen fertig gezeichnet aus dem Design (brand/icon/README.md) und
 * werden bewusst nicht aus einer großen Datei heruntergerechnet. Was hier
 * geprüft wird, sind die Fehler, die beim Umbauen von Pfaden entstehen: ein
 * Manifest, das auf nicht existierende Dateien zeigt, oder ein fehlendes
 * favicon.ico im Wurzelverzeichnis.
 */
class IconSetTest extends TestCase
{
    public function test_the_manifest_is_valid_and_every_icon_it_names_exists(): void
    {
        // Statische Dateien liefert der Webserver aus, nicht Laravel — hier
        // wird deshalb die Datei geprüft, nicht die HTTP-Antwort.
        $this->assertFileExists(public_path('site.webmanifest'));

        $manifest = json_decode(file_get_contents(public_path('site.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(config('app.name'), $manifest['name']);
        $this->assertSame('#5B4BE8', $manifest['theme_color']);
        $this->assertNotEmpty($manifest['icons']);

        foreach ($manifest['icons'] as $icon) {
            $path = public_path(ltrim($icon['src'], '/'));

            $this->assertFileExists($path, "{$icon['src']} aus dem Manifest fehlt.");

            [$width, $height] = getimagesize($path);
            $this->assertSame(
                $icon['sizes'],
                "{$width}x{$height}",
                "{$icon['src']} ist {$width}x{$height}, das Manifest sagt {$icon['sizes']}."
            );
        }

        // Android braucht mindestens ein maskable Icon, sonst schneidet es die
        // Kachel ein zweites Mal an.
        $purposes = array_column($manifest['icons'], 'purpose');
        $this->assertContains('maskable', $purposes);
    }

    public function test_the_icons_referenced_in_the_head_are_served(): void
    {
        foreach ([
            '/favicon.ico',
            '/icon/favicon.svg',
            '/icon/apple-touch-icon.png',
        ] as $path) {
            $this->assertFileExists(public_path(ltrim($path, '/')), "$path fehlt.");
        }

        $response = $this->get('/');
        $response->assertSee('href="/favicon.ico"', false);
        $response->assertSee('href="/icon/favicon.svg"', false);
        $response->assertSee('href="/icon/apple-touch-icon.png"', false);
    }

    public function test_the_store_icon_is_square_and_opaque(): void
    {
        // App Store und Play Store lehnen Transparenz im 1024er Icon ab.
        $path = public_path('icon/icon-1024.png');

        $this->assertFileExists($path);
        $this->assertSame([1024, 1024], array_slice(getimagesize($path), 0, 2));

        $image = imagecreatefrompng($path);
        $corner = imagecolorsforindex($image, imagecolorat($image, 0, 0));
        imagedestroy($image);

        $this->assertSame(0, $corner['alpha'], 'icon-1024.png darf keine Transparenz haben.');
    }
}
