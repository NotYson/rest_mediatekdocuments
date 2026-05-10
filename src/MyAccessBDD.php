<?php
include_once("AccessBDD.php");

/**
 * Classe de construction des requêtes SQL
 * hérite de AccessBDD qui contient les requêtes de base
 * Pour ajouter une requête :
 * - créer la fonction qui crée une requête (prendre modèle sur les fonctions 
 *   existantes qui ne commencent pas par 'traitement')
 * - ajouter un 'case' dans un des switch des fonctions redéfinies 
 * - appeler la nouvelle fonction dans ce 'case'
 */
class MyAccessBDD extends AccessBDD {
	    
    /**
     * constructeur qui appelle celui de la classe mère
     */
    public function __construct(){
        try{
            parent::__construct();
        }catch(\Exception $e){
            throw $e;
        }
    }

    /**
     * demande de recherche
     * @param string $table
     * @param array|null $champs nom et valeur de chaque champ
     * @return array|null tuples du résultat de la requête ou null si erreur
     * @override
     */	
    protected function traitementSelect(string $table, ?array $champs) : ?array{
        switch($table){  
            case "livre" :
                return $this->selectAllLivres();
            case "dvd" :
                return $this->selectAllDvd();
            case "revue" :
                return $this->selectAllRevues();
            case "exemplaire" :
                return $this->selectExemplairesRevue($champs);
            case "commandedocument" :
                return $this->selectCommandesLivreDvd($champs);
            case "abonnement" :
                return $this->selectAbonnements($champs);
            case "genre" :
            case "public" :
            case "rayon" :
            case "etat" :
                // select portant sur une table contenant juste id et libelle
                return $this->selectTableSimple($table);
            case "" :
                // return $this->uneFonction(parametres);
            default:
                // cas général
                return $this->selectTuplesOneTable($table, $champs);
        }	
    }

    /**
     * demande d'ajout (insert)
     * @param string $table
     * @param array|null $champs nom et valeur de chaque champ
     * @return int|null nombre de tuples ajoutés ou null si erreur
     * @override
     */	
    protected function traitementInsert(string $table, ?array $champs) : ?int{
        switch($table){
            case "livre" :
                return $this->insertLivre($champs);
            case "dvd" :
                return $this->insertDvd($champs);
            case "revue" :
                return $this->insertRevue($champs);
            case "commandedocument" :
                return $this->insertCommande($champs);
            case "abonnement" :
                return $this->insertAbonnement($champs);
            case "" :
                // return $this->uneFonction(parametres);
            default:
                // cas général
                return $this->insertOneTupleOneTable($table, $champs);
        }
    }
    
    /**
     * demande de modification (update)
     * @param string $table
     * @param string|null $id
     * @param array|null $champs nom et valeur de chaque champ
     * @return int|null nombre de tuples modifiés ou null si erreur
     * @override
     */	
    protected function traitementUpdate(string $table, ?string $id, ?array $champs) : ?int{
        switch($table){
            case "livre" :
                return $this->updateLivre($id, $champs);
            case "dvd" :
                return $this->updateDvd($id, $champs);
            case "revue" :
                return $this->updateRevue($id, $champs);
            case "commandedocument" :
                return $this->updateStatutCommande($id, $champs);
            case "" :
                // return $this->uneFonction(parametres);
            default:
                // cas général
                return $this->updateOneTupleOneTable($table, $id, $champs);
        }
    }  
    
    /**
     * demande de suppression (delete)
     * @param string $table
     * @param array|null $champs nom et valeur de chaque champ
     * @return int|null nombre de tuples supprimés ou null si erreur
     * @override
     */	
    protected function traitementDelete(string $table, ?array $champs) : ?int{
        switch($table){
            case "livre" :
                return $this->deleteLivre($champs);
            case "dvd" :
                return $this->deleteDvd($champs);
            case "revue" :
                return $this->deleteRevue($champs);
            case "commandedocument" :
                return $this->deleteCommande($champs);
            case "abonnement" :
                return $this->deleteAbonnement($champs);
            case "" :
                // return $this->uneFonction(parametres);
            default:
                // cas général
                return $this->deleteTuplesOneTable($table, $champs);
        }
    }
        
    /**
     * récupère les tuples d'une seule table
     * @param string $table
     * @param array|null $champs
     * @return array|null 
     */
    private function selectTuplesOneTable(string $table, ?array $champs) : ?array{
        if(empty($champs)){
            // tous les tuples d'une table
            $requete = "select * from $table;";
            return $this->conn->queryBDD($requete);  
        }else{
            // tuples spécifiques d'une table
            $requete = "select * from $table where ";
            foreach ($champs as $key => $value){
                $requete .= "$key=:$key and ";
            }
            // (enlève le dernier and)
            $requete = substr($requete, 0, strlen($requete)-5);	          
            return $this->conn->queryBDD($requete, $champs);
        }
    }	

    /**
     * demande d'ajout (insert) d'un tuple dans une table
     * @param string $table
     * @param array|null $champs
     * @return int|null nombre de tuples ajoutés (0 ou 1) ou null si erreur
     */	
    private function insertOneTupleOneTable(string $table, ?array $champs) : ?int{
        if(empty($champs)){
            return null;
        }
        // construction de la requête
        $requete = "insert into $table (";
        foreach ($champs as $key => $value){
            $requete .= "$key,";
        }
        // (enlève la dernière virgule)
        $requete = substr($requete, 0, strlen($requete)-1);
        $requete .= ") values (";
        foreach ($champs as $key => $value){
            $requete .= ":$key,";
        }
        // (enlève la dernière virgule)
        $requete = substr($requete, 0, strlen($requete)-1);
        $requete .= ");";
        return $this->conn->updateBDD($requete, $champs);
    }

    /**
     * demande de modification (update) d'un tuple dans une table
     * @param string $table
     * @param string\null $id
     * @param array|null $champs 
     * @return int|null nombre de tuples modifiés (0 ou 1) ou null si erreur
     */	
    private function updateOneTupleOneTable(string $table, ?string $id, ?array $champs) : ?int {
        if(empty($champs)){
            return null;
        }
        if(is_null($id)){
            return null;
        }
        // construction de la requête
        $requete = "update $table set ";
        foreach ($champs as $key => $value){
            $requete .= "$key=:$key,";
        }
        // (enlève la dernière virgule)
        $requete = substr($requete, 0, strlen($requete)-1);				
        $champs["id"] = $id;
        $requete .= " where id=:id;";		
        return $this->conn->updateBDD($requete, $champs);	        
    }
    
    /**
     * demande de suppression (delete) d'un ou plusieurs tuples dans une table
     * @param string $table
     * @param array|null $champs
     * @return int|null nombre de tuples supprimés ou null si erreur
     */
    private function deleteTuplesOneTable(string $table, ?array $champs) : ?int{
        if(empty($champs)){
            return null;
        }
        // construction de la requête
        $requete = "delete from $table where ";
        foreach ($champs as $key => $value){
            $requete .= "$key=:$key and ";
        }
        // (enlève le dernier and)
        $requete = substr($requete, 0, strlen($requete)-5);   
        return $this->conn->updateBDD($requete, $champs);	        
    }
 
    /**
     * récupère toutes les lignes d'une table simple (qui contient juste id et libelle)
     * @param string $table
     * @return array|null
     */
    private function selectTableSimple(string $table) : ?array{
        $requete = "select * from $table order by libelle;";		
        return $this->conn->queryBDD($requete);	    
    }
    
    /**
     * récupère toutes les lignes de la table Livre et les tables associées
     * @return array|null
     */
    private function selectAllLivres() : ?array{
        $requete = "Select l.id, l.ISBN, l.auteur, d.titre, d.image, l.collection, ";
        $requete .= "d.idrayon, d.idpublic, d.idgenre, g.libelle as genre, p.libelle as lePublic, r.libelle as rayon ";
        $requete .= "from livre l join document d on l.id=d.id ";
        $requete .= "join genre g on g.id=d.idGenre ";
        $requete .= "join public p on p.id=d.idPublic ";
        $requete .= "join rayon r on r.id=d.idRayon ";
        $requete .= "order by titre ";		
        return $this->conn->queryBDD($requete);
    }	

    /**
     * récupère toutes les lignes de la table DVD et les tables associées
     * @return array|null
     */
    private function selectAllDvd() : ?array{
        $requete = "Select l.id, l.duree, l.realisateur, d.titre, d.image, l.synopsis, ";
        $requete .= "d.idrayon, d.idpublic, d.idgenre, g.libelle as genre, p.libelle as lePublic, r.libelle as rayon ";
        $requete .= "from dvd l join document d on l.id=d.id ";
        $requete .= "join genre g on g.id=d.idGenre ";
        $requete .= "join public p on p.id=d.idPublic ";
        $requete .= "join rayon r on r.id=d.idRayon ";
        $requete .= "order by titre ";	
        return $this->conn->queryBDD($requete);
    }	

    /**
     * récupère toutes les lignes de la table Revue et les tables associées
     * @return array|null
     */
    private function selectAllRevues() : ?array{
        $requete = "Select l.id, l.periodicite, d.titre, d.image, l.delaiMiseADispo, ";
        $requete .= "d.idrayon, d.idpublic, d.idgenre, g.libelle as genre, p.libelle as lePublic, r.libelle as rayon ";
        $requete .= "from revue l join document d on l.id=d.id ";
        $requete .= "join genre g on g.id=d.idGenre ";
        $requete .= "join public p on p.id=d.idPublic ";
        $requete .= "join rayon r on r.id=d.idRayon ";
        $requete .= "order by titre ";
        return $this->conn->queryBDD($requete);
    }	

    /**
     * ajoute un livre (document + livres_dvd + livre) dans une transaction
     * @param array|null $champs tous les champs du livre
     * @return int|null 1 si succès, null si erreur
     */
    private function insertLivre(?array $champs) : ?int {
        if (empty($champs)) {
            return null;
        }
        $champsDocument  = array_intersect_key($champs, array_flip(['id', 'titre', 'image', 'idRayon', 'idPublic', 'idGenre']));
        $champsLivresDvd = array_intersect_key($champs, array_flip(['id']));
        $champsLivre     = array_intersect_key($champs, array_flip(['id', 'ISBN', 'auteur', 'collection']));
        $this->conn->beginTransaction();
        $ok = $this->insertOneTupleOneTable('document',   $champsDocument)  !== null
           && $this->insertOneTupleOneTable('livres_dvd', $champsLivresDvd) !== null
           && $this->insertOneTupleOneTable('livre',      $champsLivre)     !== null;
        if ($ok) {
            $this->conn->commit();
            return 1;
        }
        $this->conn->rollback();
        return null;
    }

    /**
     * ajoute un DVD (document + livres_dvd + dvd) dans une transaction
     * @param array|null $champs tous les champs du DVD
     * @return int|null 1 si succès, null si erreur
     */
    private function insertDvd(?array $champs) : ?int {
        if (empty($champs)) {
            return null;
        }
        $champsDocument  = array_intersect_key($champs, array_flip(['id', 'titre', 'image', 'idRayon', 'idPublic', 'idGenre']));
        $champsLivresDvd = array_intersect_key($champs, array_flip(['id']));
        $champsDvd       = array_intersect_key($champs, array_flip(['id', 'synopsis', 'realisateur', 'duree']));
        $this->conn->beginTransaction();
        $ok = $this->insertOneTupleOneTable('document',   $champsDocument)  !== null
           && $this->insertOneTupleOneTable('livres_dvd', $champsLivresDvd) !== null
           && $this->insertOneTupleOneTable('dvd',        $champsDvd)       !== null;
        if ($ok) {
            $this->conn->commit();
            return 1;
        }
        $this->conn->rollback();
        return null;
    }

    /**
     * ajoute une revue (document + revue) dans une transaction
     * @param array|null $champs tous les champs de la revue
     * @return int|null 1 si succès, null si erreur
     */
    private function insertRevue(?array $champs) : ?int {
        if (empty($champs)) {
            return null;
        }
        $champsDocument = array_intersect_key($champs, array_flip(['id', 'titre', 'image', 'idRayon', 'idPublic', 'idGenre']));
        $champsRevue    = array_intersect_key($champs, array_flip(['id', 'periodicite', 'delaiMiseADispo']));
        $this->conn->beginTransaction();
        $ok = $this->insertOneTupleOneTable('document', $champsDocument) !== null
           && $this->insertOneTupleOneTable('revue',    $champsRevue)    !== null;
        if ($ok) {
            $this->conn->commit();
            return 1;
        }
        $this->conn->rollback();
        return null;
    }

    /**
     * modifie un livre (document + livre) dans une transaction
     * @param string|null $id
     * @param array|null $champs champs à modifier (sans id)
     * @return int|null 1 si succès, null si erreur
     */
    private function updateLivre(?string $id, ?array $champs) : ?int {
        if (empty($champs) || is_null($id)) {
            return null;
        }
        $champsDocument = array_intersect_key($champs, array_flip(['titre', 'image', 'idRayon', 'idPublic', 'idGenre']));
        $champsLivre    = array_intersect_key($champs, array_flip(['ISBN', 'auteur', 'collection']));
        $this->conn->beginTransaction();
        $ok = $this->updateOneTupleOneTable('document', $id, $champsDocument) !== null
           && $this->updateOneTupleOneTable('livre',    $id, $champsLivre)    !== null;
        if ($ok) {
            $this->conn->commit();
            return 1;
        }
        $this->conn->rollback();
        return null;
    }

    /**
     * modifie un DVD (document + dvd) dans une transaction
     * @param string|null $id
     * @param array|null $champs champs à modifier (sans id)
     * @return int|null 1 si succès, null si erreur
     */
    private function updateDvd(?string $id, ?array $champs) : ?int {
        if (empty($champs) || is_null($id)) {
            return null;
        }
        $champsDocument = array_intersect_key($champs, array_flip(['titre', 'image', 'idRayon', 'idPublic', 'idGenre']));
        $champsDvd      = array_intersect_key($champs, array_flip(['synopsis', 'realisateur', 'duree']));
        $this->conn->beginTransaction();
        $ok = $this->updateOneTupleOneTable('document', $id, $champsDocument) !== null
           && $this->updateOneTupleOneTable('dvd',      $id, $champsDvd)      !== null;
        if ($ok) {
            $this->conn->commit();
            return 1;
        }
        $this->conn->rollback();
        return null;
    }

    /**
     * modifie une revue (document + revue) dans une transaction
     * @param string|null $id
     * @param array|null $champs champs à modifier (sans id)
     * @return int|null 1 si succès, null si erreur
     */
    private function updateRevue(?string $id, ?array $champs) : ?int {
        if (empty($champs) || is_null($id)) {
            return null;
        }
        $champsDocument = array_intersect_key($champs, array_flip(['titre', 'image', 'idRayon', 'idPublic', 'idGenre']));
        $champsRevue    = array_intersect_key($champs, array_flip(['periodicite', 'delaiMiseADispo']));
        $this->conn->beginTransaction();
        $ok = $this->updateOneTupleOneTable('document', $id, $champsDocument) !== null
           && $this->updateOneTupleOneTable('revue',    $id, $champsRevue)    !== null;
        if ($ok) {
            $this->conn->commit();
            return 1;
        }
        $this->conn->rollback();
        return null;
    }

    /**
     * récupère les commandes d'un livre ou DVD avec jointure sur commande
     * @param array|null $champs peut contenir 'idLivreDvd' pour filtrer
     * @return array|null
     */
    private function selectCommandesLivreDvd(?array $champs) : ?array {
        $requete = "select cd.id, cd.nbExemplaire, cd.idLivreDvd, c.dateCommande, c.montant, c.statut ";
        $requete .= "from commandedocument cd join commande c on cd.id=c.id ";
        if (!empty($champs) && array_key_exists('idLivreDvd', $champs)) {
            $requete .= "where cd.idLivreDvd=:idLivreDvd ";
            $requete .= "order by c.dateCommande DESC";
            return $this->conn->queryBDD($requete, ['idLivreDvd' => $champs['idLivreDvd']]);
        }
        $requete .= "order by c.dateCommande DESC";
        return $this->conn->queryBDD($requete);
    }

    /**
     * ajoute une commande (commande + commandedocument) dans une transaction
     * le statut est initialisé à "en cours"
     * @param array|null $champs id, dateCommande, montant, nbExemplaire, idLivreDvd
     * @return int|null 1 si succès, null si erreur
     */
    private function insertCommande(?array $champs) : ?int {
        if (empty($champs)) {
            return null;
        }
        $champsCommande = array_intersect_key($champs, array_flip(['id', 'dateCommande', 'montant']));
        $champsCommande['statut'] = 'en cours';
        $champsCmdDoc = array_intersect_key($champs, array_flip(['id', 'nbExemplaire', 'idLivreDvd']));
        $this->conn->beginTransaction();
        $ok = $this->insertOneTupleOneTable('commande',         $champsCommande) !== null
           && $this->insertOneTupleOneTable('commandedocument', $champsCmdDoc)   !== null;
        if ($ok) {
            $this->conn->commit();
            return 1;
        }
        $this->conn->rollback();
        return null;
    }

    /**
     * modifie le statut d'une commande
     * si le statut passe à "livrée" pour la première fois, génère automatiquement les exemplaires
     * @param string|null $id
     * @param array|null $champs doit contenir 'statut'
     * @return int|null 1 si succès, null si erreur
     */
    private function updateStatutCommande(?string $id, ?array $champs) : ?int {
        if (empty($champs) || is_null($id)) {
            return null;
        }
        $nouveauStatut = $champs['statut'] ?? null;
        if (is_null($nouveauStatut)) {
            return null;
        }
        $statutActuel = $this->conn->queryBDD("select statut from commande where id=:id;", ['id' => $id]);
        if ($statutActuel === null || empty($statutActuel)) {
            return null;
        }
        $this->conn->beginTransaction();
        $result = $this->updateOneTupleOneTable('commande', $id, ['statut' => $nouveauStatut]);
        if ($result === null) {
            $this->conn->rollback();
            return null;
        }
        if ($nouveauStatut === 'livrée' && $statutActuel[0]['statut'] !== 'livrée') {
            if ($this->genererExemplaires($id) === null) {
                $this->conn->rollback();
                return null;
            }
        }
        $this->conn->commit();
        return $result;
    }

    /**
     * génère les exemplaires lors du passage au statut "livrée"
     * les numéros sont séquentiels par rapport aux exemplaires existants en base
     * @param string $idCommande
     * @return int|null nombre d'exemplaires créés ou null si erreur
     */
    private function genererExemplaires(string $idCommande) : ?int {
        $cd = $this->conn->queryBDD(
            "select nbExemplaire, idLivreDvd from commandedocument where id=:id;",
            ['id' => $idCommande]
        );
        if ($cd === null || empty($cd)) {
            return null;
        }
        $nbExemplaire = (int)$cd[0]['nbExemplaire'];
        $idLivreDvd   = $cd[0]['idLivreDvd'];
        $maxRes = $this->conn->queryBDD(
            "select coalesce(max(numero), 0) as maxNum from exemplaire where id=:id;",
            ['id' => $idLivreDvd]
        );
        if ($maxRes === null) {
            return null;
        }
        $maxNum = (int)$maxRes[0]['maxNum'];
        for ($i = 1; $i <= $nbExemplaire; $i++) {
            $champsEx = [
                'id'        => $idLivreDvd,
                'numero'    => $maxNum + $i,
                'dateAchat' => date('Y-m-d'),
                'photo'     => '',
                'idEtat'    => '00001'
            ];
            if ($this->insertOneTupleOneTable('exemplaire', $champsEx) === null) {
                return null;
            }
        }
        return $nbExemplaire;
    }

    /**
     * récupère les abonnements avec jointure sur commande
     * - sans champs : tous les abonnements (y compris expirés)
     * - champs['idRevue'] : abonnements d'une revue donnée
     * - champs['expireBientot'] : abonnements dont la fin est dans les 30 prochains jours
     * @param array|null $champs
     * @return array|null
     */
    private function selectAbonnements(?array $champs) : ?array {
        $requete = "select a.id, a.idRevue, a.dateFinAbonnement, c.dateCommande, c.montant ";
        $requete .= "from abonnement a join commande c on a.id=c.id ";
        if (!empty($champs) && array_key_exists('idRevue', $champs)) {
            $requete .= "where a.idRevue=:idRevue ";
            $requete .= "order by a.dateFinAbonnement DESC";
            return $this->conn->queryBDD($requete, ['idRevue' => $champs['idRevue']]);
        }
        if (!empty($champs) && array_key_exists('expireBientot', $champs)) {
            $requete .= "where a.dateFinAbonnement between curdate() and date_add(curdate(), interval 30 day) ";
            $requete .= "order by a.dateFinAbonnement ASC";
            return $this->conn->queryBDD($requete);
        }
        $requete .= "order by a.dateFinAbonnement DESC";
        return $this->conn->queryBDD($requete);
    }

    /**
     * ajoute un abonnement (commande + abonnement) dans une transaction
     * @param array|null $champs id, dateCommande, montant, dateFinAbonnement, idRevue
     * @return int|null 1 si succès, null si erreur
     */
    private function insertAbonnement(?array $champs) : ?int {
        if (empty($champs)) {
            return null;
        }
        $champsCommande = array_intersect_key($champs, array_flip(['id', 'dateCommande', 'montant']));
        $champsCommande['statut'] = 'en cours';
        $champsAbonnement = array_intersect_key($champs, array_flip(['id', 'dateFinAbonnement', 'idRevue']));
        $this->conn->beginTransaction();
        $ok = $this->insertOneTupleOneTable('commande',   $champsCommande)   !== null
           && $this->insertOneTupleOneTable('abonnement', $champsAbonnement) !== null;
        if ($ok) {
            $this->conn->commit();
            return 1;
        }
        $this->conn->rollback();
        return null;
    }

    /**
     * supprime un abonnement (abonnement → commande) dans une transaction
     * lève une exception si des parutions (exemplaires) existent pour la revue concernée
     * @param array|null $champs doit contenir 'id'
     * @return int|null 1 si succès, null si erreur
     */
    private function deleteAbonnement(?array $champs) : ?int {
        if (empty($champs)) {
            return null;
        }
        $id = $champs['id'] ?? null;
        if (is_null($id)) {
            return null;
        }
        $abo = $this->conn->queryBDD("select idRevue from abonnement where id=:id;", ['id' => $id]);
        if ($abo === null || empty($abo)) {
            return null;
        }
        $idRevue = $abo[0]['idRevue'];
        $result = $this->conn->queryBDD("select count(*) as nb from exemplaire where id=:id;", ['id' => $idRevue]);
        if ($result === null) {
            return null;
        }
        if ((int)$result[0]['nb'] > 0) {
            throw new \Exception("Suppression impossible : des parutions existent pour cet abonnement.");
        }
        $param = ['id' => $id];
        $this->conn->beginTransaction();
        $ok = $this->deleteTuplesOneTable('abonnement', $param) !== null
           && $this->deleteTuplesOneTable('commande',   $param) !== null;
        if ($ok) {
            $this->conn->commit();
            return 1;
        }
        $this->conn->rollback();
        return null;
    }

    /**
     * supprime une commande (commandedocument → commande) dans une transaction
     * lève une exception si le statut n'est pas "en cours" ou "relancée"
     * @param array|null $champs doit contenir 'id'
     * @return int|null 1 si succès, null si erreur
     */
    private function deleteCommande(?array $champs) : ?int {
        if (empty($champs)) {
            return null;
        }
        $id = $champs['id'] ?? null;
        if (is_null($id)) {
            return null;
        }
        $result = $this->conn->queryBDD("select statut from commande where id=:id;", ['id' => $id]);
        if ($result === null || empty($result)) {
            return null;
        }
        $statut = $result[0]['statut'];
        if ($statut !== 'en cours' && $statut !== 'relancée') {
            throw new \Exception("Suppression impossible : la commande a le statut \"$statut\".");
        }
        $param = ['id' => $id];
        $this->conn->beginTransaction();
        $ok = $this->deleteTuplesOneTable('commandedocument', $param) !== null
           && $this->deleteTuplesOneTable('commande',         $param) !== null;
        if ($ok) {
            $this->conn->commit();
            return 1;
        }
        $this->conn->rollback();
        return null;
    }

    /**
     * supprime un livre (livre → livres_dvd → document) dans une transaction
     * lève une exception si des exemplaires ou commandes existent
     * @param array|null $champs doit contenir 'id'
     * @return int|null 1 si succès, null si erreur
     */
    private function deleteLivre(?array $champs) : ?int {
        if (empty($champs)) {
            return null;
        }
        $id = $champs['id'] ?? null;
        if (is_null($id)) {
            return null;
        }
        $param = ['id' => $id];
        $result = $this->conn->queryBDD("select count(*) as nb from exemplaire where id=:id;", $param);
        if ($result === null) {
            return null;
        }
        if ((int)$result[0]['nb'] > 0) {
            throw new \Exception("Suppression impossible : des exemplaires existent pour ce livre.");
        }
        $result = $this->conn->queryBDD("select count(*) as nb from commandedocument where idLivreDvd=:id;", $param);
        if ($result === null) {
            return null;
        }
        if ((int)$result[0]['nb'] > 0) {
            throw new \Exception("Suppression impossible : des commandes existent pour ce livre.");
        }
        $this->conn->beginTransaction();
        $ok = $this->deleteTuplesOneTable('livre',      $param) !== null
           && $this->deleteTuplesOneTable('livres_dvd', $param) !== null
           && $this->deleteTuplesOneTable('document',   $param) !== null;
        if ($ok) {
            $this->conn->commit();
            return 1;
        }
        $this->conn->rollback();
        return null;
    }

    /**
     * supprime un DVD (dvd → livres_dvd → document) dans une transaction
     * lève une exception si des exemplaires ou commandes existent
     * @param array|null $champs doit contenir 'id'
     * @return int|null 1 si succès, null si erreur
     */
    private function deleteDvd(?array $champs) : ?int {
        if (empty($champs)) {
            return null;
        }
        $id = $champs['id'] ?? null;
        if (is_null($id)) {
            return null;
        }
        $param = ['id' => $id];
        $result = $this->conn->queryBDD("select count(*) as nb from exemplaire where id=:id;", $param);
        if ($result === null) {
            return null;
        }
        if ((int)$result[0]['nb'] > 0) {
            throw new \Exception("Suppression impossible : des exemplaires existent pour ce DVD.");
        }
        $result = $this->conn->queryBDD("select count(*) as nb from commandedocument where idLivreDvd=:id;", $param);
        if ($result === null) {
            return null;
        }
        if ((int)$result[0]['nb'] > 0) {
            throw new \Exception("Suppression impossible : des commandes existent pour ce DVD.");
        }
        $this->conn->beginTransaction();
        $ok = $this->deleteTuplesOneTable('dvd',        $param) !== null
           && $this->deleteTuplesOneTable('livres_dvd', $param) !== null
           && $this->deleteTuplesOneTable('document',   $param) !== null;
        if ($ok) {
            $this->conn->commit();
            return 1;
        }
        $this->conn->rollback();
        return null;
    }

    /**
     * supprime une revue (revue → document) dans une transaction
     * lève une exception si des exemplaires ou abonnements existent
     * @param array|null $champs doit contenir 'id'
     * @return int|null 1 si succès, null si erreur
     */
    private function deleteRevue(?array $champs) : ?int {
        if (empty($champs)) {
            return null;
        }
        $id = $champs['id'] ?? null;
        if (is_null($id)) {
            return null;
        }
        $param = ['id' => $id];
        $result = $this->conn->queryBDD("select count(*) as nb from exemplaire where id=:id;", $param);
        if ($result === null) {
            return null;
        }
        if ((int)$result[0]['nb'] > 0) {
            throw new \Exception("Suppression impossible : des exemplaires existent pour cette revue.");
        }
        $result = $this->conn->queryBDD("select count(*) as nb from abonnement where idRevue=:id;", $param);
        if ($result === null) {
            return null;
        }
        if ((int)$result[0]['nb'] > 0) {
            throw new \Exception("Suppression impossible : des abonnements existent pour cette revue.");
        }
        $this->conn->beginTransaction();
        $ok = $this->deleteTuplesOneTable('revue',    $param) !== null
           && $this->deleteTuplesOneTable('document', $param) !== null;
        if ($ok) {
            $this->conn->commit();
            return 1;
        }
        $this->conn->rollback();
        return null;
    }

    /**
     * récupère tous les exemplaires d'une revue
     * @param array|null $champs
     * @return array|null
     */
    private function selectExemplairesRevue(?array $champs) : ?array{
        if(empty($champs)){
            return null;
        }
        if(!array_key_exists('id', $champs)){
            return null;
        }
        $champNecessaire['id'] = $champs['id'];
        $requete = "Select e.id, e.numero, e.dateAchat, e.photo, e.idEtat ";
        $requete .= "from exemplaire e join document d on e.id=d.id ";
        $requete .= "where e.id = :id ";
        $requete .= "order by e.dateAchat DESC";
        return $this->conn->queryBDD($requete, $champNecessaire);
    }		    
    
}
