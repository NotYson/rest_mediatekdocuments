<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests de la classe Controle (couche contrôleur).
 * Vérifie que chaque sortie HTTP est un JSON valide contenant code, message et result.
 */
class ControleTest extends TestCase
{
    private static Controle $controle;

    public static function setUpBeforeClass(): void
    {
        self::$controle = new Controle();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function captureEcho(callable $fn): array
    {
        ob_start();
        $fn();
        $raw = ob_get_clean();
        return json_decode($raw, true) ?? [];
    }

    // -------------------------------------------------------------------------
    // unauthorized()
    // -------------------------------------------------------------------------

    public function testUnauthorizedRetourneJson401(): void
    {
        $json = $this->captureEcho(fn() => self::$controle->unauthorized());
        $this->assertArrayHasKey('code',    $json);
        $this->assertArrayHasKey('message', $json);
        $this->assertArrayHasKey('result',  $json);
        $this->assertEquals(401, $json['code']);
        $this->assertStringContainsString('authentification', $json['message']);
    }

    // -------------------------------------------------------------------------
    // demande() — succès (GET sur données existantes)
    // -------------------------------------------------------------------------

    public function testDemandeGetLivreRetourneJson200(): void
    {
        $json = $this->captureEcho(
            fn() => self::$controle->demande('GET', 'livre', null, null)
        );
        $this->assertEquals(200, $json['code']);
        $this->assertEquals('OK', $json['message']);
        $this->assertIsArray($json['result']);
        $this->assertNotEmpty($json['result']);
    }

    public function testDemandeGetGenreRetourneJson200(): void
    {
        $json = $this->captureEcho(
            fn() => self::$controle->demande('GET', 'genre', null, null)
        );
        $this->assertEquals(200, $json['code']);
        $this->assertIsArray($json['result']);
    }

    // -------------------------------------------------------------------------
    // demande() — requête invalide (table inconnue, champs manquants)
    // -------------------------------------------------------------------------

    public function testDemandeGetExemplaireSansIdRetourne400(): void
    {
        $json = $this->captureEcho(
            fn() => self::$controle->demande('GET', 'exemplaire', null, null)
        );
        $this->assertEquals(400, $json['code']);
    }

    public function testDemandePostLivreSansChampsRetourne400(): void
    {
        $json = $this->captureEcho(
            fn() => self::$controle->demande('POST', 'livre', null, null)
        );
        $this->assertEquals(400, $json['code']);
    }

    // -------------------------------------------------------------------------
    // demande() — exception métier convertie en 400
    // -------------------------------------------------------------------------

    public function testDemandeDeleteRevueAvecExemplairesRetourne400(): void
    {
        // La revue 10011 a des exemplaires → suppression interdite → exception → 400
        $json = $this->captureEcho(
            fn() => self::$controle->demande('DELETE', 'revue', null, ['id' => '10011'])
        );
        $this->assertEquals(400, $json['code']);
        $this->assertStringContainsString('exemplaires existent', $json['message']);
    }

    public function testDemandePutExemplaireEtatInvalideRetourne400(): void
    {
        $json = $this->captureEcho(
            fn() => self::$controle->demande('PUT', 'exemplaire', '10007', [
                'numero' => 3237,
                'idEtat' => 'BADETAT',
            ])
        );
        $this->assertEquals(400, $json['code']);
        $this->assertStringContainsString('État invalide', $json['message']);
    }

    // -------------------------------------------------------------------------
    // Structure de la réponse JSON
    // -------------------------------------------------------------------------

    public function testReponsePossedeToujours3Cles(): void
    {
        foreach (['GET livre' => ['GET', 'livre', null, null],
                  'GET exemplaire sans id' => ['GET', 'exemplaire', null, null]] as $cas => $args) {
            $json = $this->captureEcho(
                fn() => self::$controle->demande($args[0], $args[1], $args[2], $args[3])
            );
            $this->assertArrayHasKey('code',    $json, "Clé 'code' absente pour : $cas");
            $this->assertArrayHasKey('message', $json, "Clé 'message' absente pour : $cas");
            $this->assertArrayHasKey('result',  $json, "Clé 'result' absente pour : $cas");
        }
    }
}
