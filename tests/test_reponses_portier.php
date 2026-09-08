<?php

/* gds3710::parseXml() — lecture des reponses du portier.
 *
 * Avant son introduction, le constructeur SimpleXMLElement etait appele directement sur
 * le retour de curl : des que le portier etait injoignable ou repondait autre chose que
 * du XML, l'exception non rattrapee donnait une erreur 500 cote Jeedom au lieu d'une
 * ligne de log. Ces tests fixent ce comportement. */

Verif::bloc('parseXml() : reponses valides');

$xml = gds3710::parseXml('<Config><ChallengeCode>ABC123</ChallengeCode><ResCode>0</ResCode></Config>', 'test');
Verif::vrai(is_object($xml), 'un XML valide rend un objet');
Verif::egal('ABC123', (string) $xml->ChallengeCode[0], 'le defi est lisible');
Verif::egal('0', (string) $xml->ResCode[0], 'le code de retour est lisible');

Verif::bloc('parseXml() : portier injoignable ou muet');

log::vider();
Verif::nul(gds3710::parseXml(false, 'lecture door'), 'un echec de curl rend null');
Verif::egal(1, count(log::messages('error')), 'et journalise une erreur');
Verif::present('lecture door', implode(' ', log::messages('error')),
    'le contexte figure dans le message, pour situer l appel');

log::vider();
Verif::nul(gds3710::parseXml('', 'test'), 'une reponse vide rend null');
Verif::nul(gds3710::parseXml('   ', 'test'), 'une reponse blanche rend null');
Verif::vrai(count(log::messages('error')) >= 2, 'chaque cas est journalise');

Verif::bloc('parseXml() : reponse non XML');

log::vider();
/* Cas reel : le portier renvoie une page HTML de connexion quand la session a expire. */
Verif::nul(gds3710::parseXml('<html><body>401 Unauthorized</body>', 'lecture sysinfo'),
    'du HTML tronque rend null');
Verif::egal(1, count(log::messages('error')), 'et journalise une erreur');

log::vider();
Verif::nul(gds3710::parseXml('{"erreur":"pas du xml"}', 'test'), 'du JSON rend null');

/* Le detail journalise ne doit pas rejouer un secret : la reponse est tronquee ET passee
 * par redact(). */
log::vider();
gds3710::parseXml('authcode=9f8e7d6c5b4a3210 mais pas du XML', 'test');
Verif::absent('9f8e7d6c5b4a3210', implode(' ', log::messages('error')),
    'la reponse journalisee est masquee');

Verif::bloc('parseXml() : pas d exception qui remonte');

/* Le point capital : quelle que soit l'entree, la fonction rend null au lieu de lever.
 * Une exception ici tuait le point d'entree des evenements. */
$entrees = array(false, null, '', '<', '<Config>', 'texte libre', '<?xml version="1.0"?>');
$levee = false;
foreach ($entrees as $e) {
    try {
        gds3710::parseXml($e, 'balayage');
    } catch (Exception $ex) {
        $levee = true;
    }
}
Verif::vrai($levee === false, 'aucune entree malformee ne leve d exception');
