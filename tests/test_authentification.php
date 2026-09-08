<?php

/* Authentification aupres du portier : sels, options HTTP, extraction des cookies.
 *
 * Le decoupage des en-tetes Set-Cookie vivait au milieu de take_snapshot(), entre deux
 * appels reseau : intestable, alors qu'il rend une chaine vide sans bruit et fait echouer
 * la capture bien plus loin, sans rapport apparent avec la cause. */

Verif::bloc('extraireCookies() : reponses reelles du portier');

$entetes = "HTTP/1.1 200 OK\r\n"
         . "Server: GDSWebs\r\n"
         . "Set-Cookie: session=abc123def456; path=/\r\n"
         . "Content-Type: text/xml\r\n\r\n<Config><ResCode>0</ResCode></Config>";
Verif::egal('session=abc123def456', gds3710::extraireCookies($entetes),
    'le cookie de session est extrait, sans ses attributs');

$entetes = "HTTP/1.1 200 OK\r\n"
         . "Set-Cookie: session=abc123; path=/\r\n"
         . "Set-Cookie: uname=admin; path=/\r\n"
         . "Set-Cookie: level=1\r\n\r\n";
$r = gds3710::extraireCookies($entetes);
Verif::present('session=abc123', $r, 'plusieurs cookies sont rassembles');
Verif::present('uname=admin', $r, 'le deuxieme cookie est present');
Verif::present('level=1', $r, 'le troisieme aussi');
Verif::vrai(substr($r, -1) !== ';', 'la chaine ne se termine pas par un point-virgule');

Verif::bloc('extraireCookies() : casse et espacement des en-tetes');

/* Les en-tetes HTTP sont insensibles a la casse, et le portier n'est pas regulier. */
Verif::egal('session=xyz', gds3710::extraireCookies("set-cookie: session=xyz\r\n"),
    'un en-tete en minuscules est reconnu');
Verif::egal('session=xyz', gds3710::extraireCookies("Set-Cookie:session=xyz\r\n"),
    'l absence d espace apres le deux-points ne gene pas');

Verif::bloc('extraireCookies() : absence de cookie');

/* Le cas qui compte : l'authentification a echoue, le portier ne pose aucun cookie.
 * La chaine vide doit etre reconnaissable par l'appelant, qui abandonne la capture au
 * lieu de demander l'image avec des identifiants vides. */
Verif::egal('', gds3710::extraireCookies("HTTP/1.1 401 Unauthorized\r\nServer: GDSWebs\r\n\r\n"),
    'une reponse sans Set-Cookie rend une chaine vide');
Verif::egal('', gds3710::extraireCookies(''), 'une reponse vide rend une chaine vide');
Verif::egal('', gds3710::extraireCookies(false), 'un echec de curl rend une chaine vide');
Verif::egal('', gds3710::extraireCookies(null), 'null rend une chaine vide');

Verif::bloc('les deux sels sont distincts et declares une seule fois');

/* Ils ne different que par quelques lettres et servent deux mecanismes differents :
 * les confondre donnerait une authentification refusee sans explication. */
Verif::vrai(gds3710::SEL_SESSION !== gds3710::SEL_MEDIA,
    'le sel de session et le sel media sont bien differents');
Verif::egal(17, strlen(gds3710::SEL_SESSION), 'le sel de session a la longueur attendue');
Verif::egal(17, strlen(gds3710::SEL_MEDIA), 'le sel media a la longueur attendue');

/* Une valeur magique recopiee finit par diverger : elle ne doit plus apparaitre en clair
 * ailleurs que dans sa declaration. */
$dur = array();
foreach (gds3710_fichiers_php() as $nom => $contenu) {
    foreach (array(gds3710::SEL_SESSION, gds3710::SEL_MEDIA) as $sel) {
        if (strpos($contenu, "'" . $sel . "'") !== false && strpos($nom, 'gds3710.class.php') === false) {
            $dur[] = $nom;
        }
    }
}
Verif::egal(array(), array_unique($dur),
    'aucun sel recopie en clair hors de sa declaration');

Verif::bloc('optionsHttp() : socle commun');

$o = gds3710::optionsHttp();
Verif::vrai($o[CURLOPT_SSL_VERIFYPEER] === false,
    'la verification du certificat reste desactivee : le portier est en auto-signe');
Verif::vrai($o[CURLOPT_RETURNTRANSFER] === true, 'le corps est rendu par defaut');
Verif::vrai($o[CURLOPT_TIMEOUT] > 0,
    'un delai d attente est toujours pose : sans lui une requete bloque indefiniment');

/* L'appelant doit pouvoir remplacer une option, sinon le socle devient un carcan. */
$o = gds3710::optionsHttp(array(CURLOPT_TIMEOUT => 3, CURLOPT_URL => 'https://exemple/'));
Verif::egal(3, $o[CURLOPT_TIMEOUT], 'une option fournie l emporte sur le socle');
Verif::egal('https://exemple/', $o[CURLOPT_URL], 'les options propres a l appel sont conservees');
Verif::vrai($o[CURLOPT_SSL_VERIFYHOST] === false, 'le reste du socle est toujours la');

Verif::bloc('sessionMedia() : configuration absente');

/* Sans adresse ni mot de passe, la methode refuse avant tout appel reseau et le dit. */
log::vider();
$eq = new gds3710();
Verif::nul($eq->sessionMedia(), 'un equipement sans adresse ne tente pas de capture');
Verif::egal(1, count(log::messages('error')), 'et l administrateur est prevenu');
