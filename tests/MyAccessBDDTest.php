<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests de la couche accès BDD (AccessBDD / MyAccessBDD).
 * Toutes les méthodes privées sont testées indirectement via demande().
 *
 * Prérequis : base de données mediatek86 avec le schéma complet (missions 1-4).
 * Les tests d'écriture nettoient leurs données dans le même test.
 */
class MyAccessBDDTest extends TestCase
{
    private static MyAccessBDD $access;
    private static Connexion $conn;

    // IDs dédiés aux tests pour éviter tout conflit avec les données réelles
    private const LIVRE_ID = 'TSTLV';
    private const DVD_ID   = 'TSTDV';
    private const REVUE_ID = 'TSTRV';
    private const CMD_ID   = 'TSTCD';
    private const ABO_ID   = 'TSTAB';

    public static function setUpBeforeClass(): void
    {
        self::$access = new MyAccessBDD();
        self::$conn   = Connexion::getInstance(
            $_ENV['BDD_LOGIN'],
            $_ENV['BDD_PWD'],
            $_ENV['BDD_BD'],
            $_ENV['BDD_SERVER'],
            $_ENV['BDD_PORT']
        );
    }

    public static function tearDownAfterClass(): void
    {
        // Filet de sécurité : supprime toute donnée de test résiduelle
        $idsDoc = [self::LIVRE_ID, self::DVD_ID, self::REVUE_ID];
        foreach ($idsDoc as $id) {
            self::$conn->updateBDD("delete from exemplaire   where id=:id;", ['id' => $id]);
            self::$conn->updateBDD("delete from livre        where id=:id;", ['id' => $id]);
            self::$conn->updateBDD("delete from dvd          where id=:id;", ['id' => $id]);
            self::$conn->updateBDD("delete from revue        where id=:id;", ['id' => $id]);
            self::$conn->updateBDD("delete from livres_dvd   where id=:id;", ['id' => $id]);
            self::$conn->updateBDD("delete from document     where id=:id;", ['id' => $id]);
        }
        foreach ([self::CMD_ID, self::ABO_ID] as $id) {
            self::$conn->updateBDD("delete from commandedocument where id=:id;", ['id' => $id]);
            self::$conn->updateBDD("delete from abonnement       where id=:id;", ['id' => $id]);
            self::$conn->updateBDD("delete from commande         where id=:id;", ['id' => $id]);
        }
    }

    // =========================================================================
    // Helpers privés (création / suppression de données de test)
    // =========================================================================

    private function insererTestLivre(string $id): void
    {
        self::$access->demande('POST', 'livre', null, [
            'id' => $id, 'titre' => 'Livre Test PHPUnit', 'image' => '',
            'idRayon' => 'LV001', 'idPublic' => '00002', 'idGenre' => '10002',
            'ISBN' => '9999999999999', 'auteur' => 'Auteur Test', 'collection' => '',
        ]);
    }

    private function supprimerTestLivre(string $id): void
    {
        self::$conn->updateBDD("delete from livre      where id=:id;", ['id' => $id]);
        self::$conn->updateBDD("delete from livres_dvd where id=:id;", ['id' => $id]);
        self::$conn->updateBDD("delete from document   where id=:id;", ['id' => $id]);
    }

    private function insererTestDvd(string $id): void
    {
        self::$access->demande('POST', 'dvd', null, [
            'id' => $id, 'titre' => 'DVD Test PHPUnit', 'image' => '',
            'idRayon' => 'DF001', 'idPublic' => '00003', 'idGenre' => '10002',
            'synopsis' => 'Synopsis test', 'realisateur' => 'Réalisateur Test', 'duree' => 90,
        ]);
    }

    private function supprimerTestDvd(string $id): void
    {
        self::$conn->updateBDD("delete from dvd        where id=:id;", ['id' => $id]);
        self::$conn->updateBDD("delete from livres_dvd where id=:id;", ['id' => $id]);
        self::$conn->updateBDD("delete from document   where id=:id;", ['id' => $id]);
    }

    private function insererTestRevue(string $id): void
    {
        self::$access->demande('POST', 'revue', null, [
            'id' => $id, 'titre' => 'Revue Test PHPUnit', 'image' => '',
            'idRayon' => 'PR001', 'idPublic' => '00002', 'idGenre' => '10015',
            'periodicite' => 'MS', 'delaiMiseADispo' => 30,
        ]);
    }

    private function supprimerTestRevue(string $id): void
    {
        self::$conn->updateBDD("delete from revue    where id=:id;", ['id' => $id]);
        self::$conn->updateBDD("delete from document where id=:id;", ['id' => $id]);
    }

    private function colonneStatutExiste(): bool
    {
        $result = self::$conn->queryBDD(
            "select column_name from information_schema.columns
             where table_schema=:db and table_name='commande' and column_name='statut';",
            ['db' => $_ENV['BDD_BD']]
        );
        return !empty($result);
    }

    // =========================================================================
    // GET — Lectures (données initiales garanties par mediatek86.sql)
    // =========================================================================

    public function testDemandeGetLivresRetourneUnTableau(): void
    {
        $result = self::$access->demande('GET', 'livre', null, null);
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('id', $result[0]);
        $this->assertArrayHasKey('titre', $result[0]);
    }

    public function testDemandeGetDvdsRetourneUnTableau(): void
    {
        $result = self::$access->demande('GET', 'dvd', null, null);
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('id', $result[0]);
    }

    public function testDemandeGetRevuesRetourneUnTableau(): void
    {
        $result = self::$access->demande('GET', 'revue', null, null);
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('id', $result[0]);
    }

    public function testDemandeGetExemplairesParRevueRetourneUnTableau(): void
    {
        $result = self::$access->demande('GET', 'exemplaire', null, ['id' => '10007']);
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('numero', $result[0]);
        $this->assertArrayHasKey('idEtat', $result[0]);
    }

    public function testDemandeGetExemplaireSansIdRetourneNull(): void
    {
        $result = self::$access->demande('GET', 'exemplaire', null, null);
        $this->assertNull($result);
    }

    public function testDemandeGetCommandesRetourneUnTableau(): void
    {
        $result = self::$access->demande('GET', 'commandedocument', null, null);
        $this->assertIsArray($result);
    }

    public function testDemandeGetAbonnementsRetourneUnTableau(): void
    {
        $result = self::$access->demande('GET', 'abonnement', null, null);
        $this->assertIsArray($result);
    }

    public function testDemandeGetAbonnementsExpireBientotRetourneUnTableau(): void
    {
        $result = self::$access->demande('GET', 'abonnement', null, ['expireBientot' => '1']);
        $this->assertIsArray($result);
    }

    public function testDemandeGetAbonnementsParRevueRetourneUnTableau(): void
    {
        $result = self::$access->demande('GET', 'abonnement', null, ['idRevue' => '10001']);
        $this->assertIsArray($result);
    }

    public function testDemandeGetGenreRetourneTableauTrie(): void
    {
        $result = self::$access->demande('GET', 'genre', null, null);
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('libelle', $result[0]);
        // Vérifie l'ordre alphabétique
        if (count($result) > 1) {
            $this->assertLessThanOrEqual($result[1]['libelle'], $result[0]['libelle']);
        }
    }

    public function testDemandeGetPublicRetourneUnTableau(): void
    {
        $result = self::$access->demande('GET', 'public', null, null);
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    public function testDemandeGetRayonRetourneUnTableau(): void
    {
        $result = self::$access->demande('GET', 'rayon', null, null);
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    public function testDemandeGetEtatRetourneUnTableau(): void
    {
        $result = self::$access->demande('GET', 'etat', null, null);
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    public function testDemandeAvecMethodeHTTPInconnueRetourneNull(): void
    {
        $result = self::$access->demande('PATCH', 'livre', null, null);
        $this->assertNull($result);
    }

    // =========================================================================
    // GET — Employé (authentification)
    // =========================================================================

    public function testDemandeGetEmployeChampVideRetourneNull(): void
    {
        $result = self::$access->demande('GET', 'employe', null, null);
        $this->assertNull($result);
    }

    public function testDemandeGetEmployeSansLoginRetourneNull(): void
    {
        $result = self::$access->demande('GET', 'employe', null, ['mdp' => 'pwd']);
        $this->assertNull($result);
    }

    public function testDemandeGetEmployeSansMdpRetourneNull(): void
    {
        $result = self::$access->demande('GET', 'employe', null, ['login' => 'user']);
        $this->assertNull($result);
    }

    public function testDemandeGetEmployeIdentifiantsInvalides(): void
    {
        try {
            $result = self::$access->demande('GET', 'employe', null, [
                'login' => 'utilisateur_inexistant_test',
                'mdp'   => 'motdepasse_invalide_test',
            ]);
            // Si la table employe n'existe pas, queryBDD retourne null → résultat null
            $this->assertNull($result);
        } catch (\Exception $e) {
            // Si la table existe mais les identifiants sont faux → exception attendue
            $this->assertStringContainsString('Identifiants incorrects', $e->getMessage());
        }
    }

    // =========================================================================
    // POST — Insertions (avec nettoyage)
    // =========================================================================

    public function testDemandePostLivreInsereEtRetourne1(): void
    {
        $result = self::$access->demande('POST', 'livre', null, [
            'id' => self::LIVRE_ID, 'titre' => 'Livre Test PHPUnit', 'image' => '',
            'idRayon' => 'LV001', 'idPublic' => '00002', 'idGenre' => '10002',
            'ISBN' => '9999999999999', 'auteur' => 'Auteur Test', 'collection' => '',
        ]);
        $this->assertEquals(1, $result);
        $this->supprimerTestLivre(self::LIVRE_ID);
    }

    public function testDemandePostDvdInsereEtRetourne1(): void
    {
        $result = self::$access->demande('POST', 'dvd', null, [
            'id' => self::DVD_ID, 'titre' => 'DVD Test PHPUnit', 'image' => '',
            'idRayon' => 'DF001', 'idPublic' => '00003', 'idGenre' => '10002',
            'synopsis' => 'Synopsis test', 'realisateur' => 'Réalisateur Test', 'duree' => 90,
        ]);
        $this->assertEquals(1, $result);
        $this->supprimerTestDvd(self::DVD_ID);
    }

    public function testDemandePostRevueInsereEtRetourne1(): void
    {
        $result = self::$access->demande('POST', 'revue', null, [
            'id' => self::REVUE_ID, 'titre' => 'Revue Test PHPUnit', 'image' => '',
            'idRayon' => 'PR001', 'idPublic' => '00002', 'idGenre' => '10015',
            'periodicite' => 'MS', 'delaiMiseADispo' => 30,
        ]);
        $this->assertEquals(1, $result);
        $this->supprimerTestRevue(self::REVUE_ID);
    }

    public function testDemandePostSansChampsRetourneNull(): void
    {
        $result = self::$access->demande('POST', 'livre', null, null);
        $this->assertNull($result);
    }

    public function testDemandePostCommandeAvecStatutEnCours(): void
    {
        if (!$this->colonneStatutExiste()) {
            $this->markTestSkipped('Colonne statut absente de la table commande (schéma mission 2 non appliqué).');
        }
        $result = self::$access->demande('POST', 'commandedocument', null, [
            'id' => self::CMD_ID, 'dateCommande' => '2026-05-11',
            'montant' => 25.00, 'nbExemplaire' => 2, 'idLivreDvd' => '00001',
        ]);
        $this->assertEquals(1, $result);

        // Vérifie que le statut est bien 'en cours'
        $row = self::$conn->queryBDD(
            "select statut from commande where id=:id;",
            ['id' => self::CMD_ID]
        );
        $this->assertEquals('en cours', $row[0]['statut']);

        // Nettoyage
        self::$conn->updateBDD("delete from commandedocument where id=:id;", ['id' => self::CMD_ID]);
        self::$conn->updateBDD("delete from commande         where id=:id;", ['id' => self::CMD_ID]);
    }

    // =========================================================================
    // PUT — Modifications
    // =========================================================================

    public function testDemandePutLivreModifieTitreEtAuteur(): void
    {
        // updateLivre exige au moins un champ document ET un champ livre.
        // Valeurs de restauration issues de mediatek86.sql (données initiales fixes).
        $result = self::$access->demande('PUT', 'livre', '00001', [
            'titre'  => 'Titre Modifié PHPUnit',
            'auteur' => 'Auteur Modifié PHPUnit',
        ]);
        $this->assertIsInt($result);
        $this->assertGreaterThanOrEqual(1, $result);

        self::$conn->updateBDD(
            "update document set titre=:titre where id=:id;",
            ['titre' => 'Quand sort la recluse', 'id' => '00001']
        );
        self::$conn->updateBDD(
            "update livre set auteur=:auteur where id=:id;",
            ['auteur' => 'Fred Vargas', 'id' => '00001']
        );
    }

    public function testDemandePutRevueModifiePeriodiciteEtTitre(): void
    {
        // updateRevue exige au moins un champ document ET un champ revue.
        $result = self::$access->demande('PUT', 'revue', '10001', [
            'titre'       => 'Titre Revue PHPUnit',
            'periodicite' => 'QT',
        ]);
        $this->assertIsInt($result);

        self::$conn->updateBDD(
            "update document set titre=:titre where id=:id;",
            ['titre' => 'Arts Magazine', 'id' => '10001']
        );
        self::$conn->updateBDD(
            "update revue set periodicite=:periodicite where id=:id;",
            ['periodicite' => 'MS', 'id' => '10001']
        );
    }

    public function testDemandePutExemplaireEtatInvalideLanceException(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/État invalide/');
        self::$access->demande('PUT', 'exemplaire', '10007', [
            'numero' => 3237,
            'idEtat' => '99999',
        ]);
    }

    public function testDemandePutExemplaireInexistantLanceException(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/Exemplaire introuvable/');
        self::$access->demande('PUT', 'exemplaire', 'XXXXX', [
            'numero' => 99999,
            'idEtat' => '00001',
        ]);
    }

    public function testDemandePutExemplaireSansChampsRetourneNull(): void
    {
        $result = self::$access->demande('PUT', 'exemplaire', '10007', null);
        $this->assertNull($result);
    }

    // =========================================================================
    // DELETE — Suppressions
    // =========================================================================

    public function testDemandeDeleteLivreAvecExemplairesLanceException(): void
    {
        $this->insererTestLivre(self::LIVRE_ID);
        self::$conn->updateBDD(
            "insert into exemplaire (id, numero, dateAchat, photo, idEtat) values (:id, 1, curdate(), '', '00001');",
            ['id' => self::LIVRE_ID]
        );
        try {
            $this->expectException(\Exception::class);
            $this->expectExceptionMessageMatches('/exemplaires existent/');
            self::$access->demande('DELETE', 'livre', null, ['id' => self::LIVRE_ID]);
        } finally {
            self::$conn->updateBDD("delete from exemplaire   where id=:id;", ['id' => self::LIVRE_ID]);
            $this->supprimerTestLivre(self::LIVRE_ID);
        }
    }

    public function testDemandeDeleteRevueAvecExemplairesLanceException(): void
    {
        // La revue 10011 possède des exemplaires dans les données initiales
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/exemplaires existent/');
        self::$access->demande('DELETE', 'revue', null, ['id' => '10011']);
    }

    public function testDemandeDeleteLivreSucces(): void
    {
        $this->insererTestLivre(self::LIVRE_ID);
        $result = self::$access->demande('DELETE', 'livre', null, ['id' => self::LIVRE_ID]);
        $this->assertEquals(1, $result);
        // Vérifie que le document n'existe plus
        $check = self::$conn->queryBDD(
            "select count(*) as nb from document where id=:id;",
            ['id' => self::LIVRE_ID]
        );
        $this->assertEquals(0, (int)$check[0]['nb']);
    }

    public function testDemandeDeleteDvdSucces(): void
    {
        $this->insererTestDvd(self::DVD_ID);
        $result = self::$access->demande('DELETE', 'dvd', null, ['id' => self::DVD_ID]);
        $this->assertEquals(1, $result);
        $check = self::$conn->queryBDD(
            "select count(*) as nb from document where id=:id;",
            ['id' => self::DVD_ID]
        );
        $this->assertEquals(0, (int)$check[0]['nb']);
    }

    public function testDemandeDeleteRevueSucces(): void
    {
        $this->insererTestRevue(self::REVUE_ID);
        $result = self::$access->demande('DELETE', 'revue', null, ['id' => self::REVUE_ID]);
        $this->assertEquals(1, $result);
        $check = self::$conn->queryBDD(
            "select count(*) as nb from document where id=:id;",
            ['id' => self::REVUE_ID]
        );
        $this->assertEquals(0, (int)$check[0]['nb']);
    }

    public function testDemandeDeleteExemplaireSucces(): void
    {
        // Insère un exemplaire temporaire sur une revue existante
        $revueId = '10001';
        $maxRes  = self::$conn->queryBDD(
            "select coalesce(max(numero), 0) as maxNum from exemplaire where id=:id;",
            ['id' => $revueId]
        );
        $testNumero = (int)$maxRes[0]['maxNum'] + 999;
        self::$conn->updateBDD(
            "insert into exemplaire (id, numero, dateAchat, photo, idEtat) values (:id, :numero, curdate(), '', '00001');",
            ['id' => $revueId, 'numero' => $testNumero]
        );

        $result = self::$access->demande('DELETE', 'exemplaire', null, [
            'id' => $revueId, 'numero' => $testNumero,
        ]);
        $this->assertIsInt($result);
        $this->assertGreaterThanOrEqual(1, $result);
    }

    public function testDemandeDeleteCommandeStatutInvalide(): void
    {
        if (!$this->colonneStatutExiste()) {
            $this->markTestSkipped('Colonne statut absente de la table commande (schéma mission 2 non appliqué).');
        }
        // Insère une commande avec statut 'livrée' directement en SQL
        self::$conn->updateBDD(
            "insert into commande (id, dateCommande, montant, statut) values (:id, curdate(), 10, 'livrée');",
            ['id' => self::CMD_ID]
        );
        self::$conn->updateBDD(
            "insert into commandedocument (id, nbExemplaire, idLivreDvd) values (:id, 1, '00001');",
            ['id' => self::CMD_ID]
        );
        try {
            $this->expectException(\Exception::class);
            $this->expectExceptionMessageMatches('/Suppression impossible/');
            self::$access->demande('DELETE', 'commandedocument', null, ['id' => self::CMD_ID]);
        } finally {
            self::$conn->updateBDD("delete from commandedocument where id=:id;", ['id' => self::CMD_ID]);
            self::$conn->updateBDD("delete from commande         where id=:id;", ['id' => self::CMD_ID]);
        }
    }

    public function testDemandeDeleteSansChampsRetourneNull(): void
    {
        $result = self::$access->demande('DELETE', 'livre', null, null);
        $this->assertNull($result);
    }
}
