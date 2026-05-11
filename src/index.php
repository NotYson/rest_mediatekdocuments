<?php
/**
 * Point d'entrée de l'API REST Mediatek86.
 * Contrôle l'authentification, récupère les variables de l'URL et
 * délègue le traitement au contrôleur.
 *
 * @package MediateK86
 */
include_once ("Url.php");
include_once("Controle.php");

// crée l'objet d'accès aux informations de l'URL qui sollicite l'API
$url = Url::getInstance();
// crée l'objet d'accès au contrôleur
$controle = new Controle();

// vérifie l'authentification
if (!$url->authentification()){
    // l'authentification a échoué
    $controle->unauthorized();
}else{
    // récupère la méthode HTTP utilisée pour accéder à l'API
    $methodeHTTP = $url->recupMethodeHTTP();
    //récupère les données passées dans l'url (visibles ou cachées)
    $table = $url->recupVariable("table");
    $id = $url->recupVariable("id");
    $champs = $url->recupVariable("champs", "json");
    // demande au controleur de traiter la demande
    $controle->demande($methodeHTTP, $table, $id, $champs);
}
