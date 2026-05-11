<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires de la classe Connexion (couche accès BDD).
 * Couvre : getInstance (singleton), queryBDD, updateBDD, beginTransaction/commit/rollback.
 */
class ConnexionTest extends TestCase
{
    private static Connexion $conn;

    public static function setUpBeforeClass(): void
    {
        self::$conn = Connexion::getInstance(
            $_ENV['BDD_LOGIN'],
            $_ENV['BDD_PWD'],
            $_ENV['BDD_BD'],
            $_ENV['BDD_SERVER'],
            $_ENV['BDD_PORT']
        );
    }

    // -------------------------------------------------------------------------
    // Singleton
    // -------------------------------------------------------------------------

    public function testGetInstanceRetourneLaMemeInstance(): void
    {
        $conn2 = Connexion::getInstance(
            $_ENV['BDD_LOGIN'],
            $_ENV['BDD_PWD'],
            $_ENV['BDD_BD'],
            $_ENV['BDD_SERVER'],
            $_ENV['BDD_PORT']
        );
        $this->assertSame(self::$conn, $conn2);
    }

    // -------------------------------------------------------------------------
    // queryBDD
    // -------------------------------------------------------------------------

    public function testQueryBDDRetourneUnTableauPourRequeteValide(): void
    {
        $result = self::$conn->queryBDD("select id, libelle from genre limit 1;");
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('id', $result[0]);
        $this->assertArrayHasKey('libelle', $result[0]);
    }

    public function testQueryBDDFiltreAvecParametre(): void
    {
        $result = self::$conn->queryBDD(
            "select id, libelle from genre where id=:id;",
            ['id' => '10001']
        );
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals('10001', $result[0]['id']);
        $this->assertEquals('Bande dessinée', $result[0]['libelle']);
    }

    public function testQueryBDDRetourneNullPourSQLInvalide(): void
    {
        $result = self::$conn->queryBDD("select * from table_inexistante_xyz;");
        $this->assertNull($result);
    }

    public function testQueryBDDRetourneTableauVideSiAucunResultat(): void
    {
        $result = self::$conn->queryBDD(
            "select * from genre where id=:id;",
            ['id' => 'ZZZZZ']
        );
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // -------------------------------------------------------------------------
    // updateBDD
    // -------------------------------------------------------------------------

    public function testUpdateBDDRetourneNombreLignesModifiees(): void
    {
        self::$conn->beginTransaction();
        $rowCount = self::$conn->updateBDD(
            "update genre set libelle=:libelle where id=:id;",
            ['libelle' => 'TestGenre_PHPUnit', 'id' => '10001']
        );
        self::$conn->rollback();

        $this->assertIsInt($rowCount);
        $this->assertEquals(1, $rowCount);
    }

    public function testUpdateBDDRollbackRestaurereDonnees(): void
    {
        $avant = self::$conn->queryBDD(
            "select libelle from genre where id=:id;",
            ['id' => '10001']
        );

        self::$conn->beginTransaction();
        self::$conn->updateBDD(
            "update genre set libelle=:libelle where id=:id;",
            ['libelle' => 'TestGenre_PHPUnit', 'id' => '10001']
        );
        self::$conn->rollback();

        $apres = self::$conn->queryBDD(
            "select libelle from genre where id=:id;",
            ['id' => '10001']
        );
        $this->assertEquals($avant[0]['libelle'], $apres[0]['libelle']);
    }

    public function testUpdateBDDRetourneNullPourSQLInvalide(): void
    {
        $result = self::$conn->updateBDD("UPDATE table_inexistante_xyz SET x=1;");
        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // Transactions
    // -------------------------------------------------------------------------

    public function testBeginTransactionCommitPersisteLaDonnee(): void
    {
        self::$conn->beginTransaction();
        self::$conn->updateBDD(
            "update genre set libelle=:libelle where id=:id;",
            ['libelle' => 'TestGenre_Commit', 'id' => '10000']
        );
        self::$conn->commit();

        $result = self::$conn->queryBDD(
            "select libelle from genre where id=:id;",
            ['id' => '10000']
        );
        $this->assertEquals('TestGenre_Commit', $result[0]['libelle']);

        // Restore
        self::$conn->updateBDD(
            "update genre set libelle=:libelle where id=:id;",
            ['libelle' => 'Humour', 'id' => '10000']
        );
    }
}
