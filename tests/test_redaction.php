<?php

/* gds3710::redact() — masquage des secrets avant journalisation.
 *
 * Les logs de ce plugin sont regulierement colles tels quels sur le forum de la
 * communaute. Chaque cas ci-dessous correspond a une forme reellement produite par le
 * code : URL signee, chaine de cookies, tableau d'options cURL rendu par print_r,
 * section de configuration du portier. */

Verif::bloc('redact() : formes URL');

$url = 'https://192.168.100.51/goform/login?cmd=login&user=admin&authcode=9f8e7d6c5b4a3210';
$r = gds3710::redact($url);
Verif::absent('9f8e7d6c5b4a3210', $r, 'authcode masque dans une URL');
Verif::present('authcode=***', $r, 'le nom du parametre reste lisible');
Verif::present('user=admin', $r, 'le reste de l URL est preserve');

$r = gds3710::redact('/jpeg/stream?type=1&user=admin&authcode=abcd1234&idcode=XYZ987');
Verif::absent('abcd1234', $r, 'authcode masque quand un autre parametre suit');
Verif::absent('XYZ987', $r, 'idcode masque');

/* Le mode MJPEG « basic » construisait une URL a identifiants. Le correctif du 08/09 l'a
 * retiree du code, mais le masquage doit rester : une URL de cette forme peut encore
 * arriver dans un log par un autre chemin. */
$r = gds3710::redact('https://admin:MonMotDePasse@192.168.100.51/jpeg/stream');
Verif::absent('MonMotDePasse', $r, 'mot de passe masque dans une URL a identifiants');
Verif::present('192.168.100.51', $r, 'l hote reste lisible');

Verif::bloc('redact() : cookies de session');

$r = gds3710::redact('session=deadbeefcafe;uname=admin;level=1');
Verif::absent('deadbeefcafe', $r, 'cookie session masque');
Verif::present('uname=admin', $r, 'les champs non sensibles sont preserves');

$r = gds3710::redact('mjpeg_session=0011223344556677');
Verif::absent('0011223344556677', $r, 'cookie mjpeg_session masque');

Verif::bloc('redact() : forme tableau de print_r');

/* C'est la forme qui avait ete oubliee : les options cURL sont journalisees par print_r,
 * et le cookie d'authentification s'y trouve. */
$options = array(
    'CURLOPT_URL' => 'https://192.168.100.51/goform/config?cmd=get&type=door',
    'session' => 'aabbccddeeff0011',
    'password' => 'SecretAdmin2026',
);
$r = gds3710::redact($options);
Verif::absent('aabbccddeeff0011', $r, 'session masquee dans un tableau');
Verif::absent('SecretAdmin2026', $r, 'password masque dans un tableau');

$r = gds3710::redact(array('token' => 'tok_123456', 'secret' => 'sh_abcdef'));
Verif::absent('tok_123456', $r, 'token masque');
Verif::absent('sh_abcdef', $r, 'secret masque');

Verif::bloc('redact() : mot de passe administrateur en P2');

/* Les sections event, play et privacy du portier rendent le mot de passe administrateur
 * en clair dans P2. Aucun chemin de code ne les journalise aujourd hui — ce masquage est
 * la pour que cela reste sans consequence si l un d eux le faisait un jour. */
$r = gds3710::redact('<Config><P2>MotDePasseAdmin</P2><P10250>1</P10250></Config>');
Verif::absent('MotDePasseAdmin', $r, 'P2 masque dans du XML');
Verif::present('<P10250>1</P10250>', $r, 'les autres P-values restent lisibles');

$r = gds3710::redact(array('P2' => 'MotDePasseAdmin', 'P10250' => '1'));
Verif::absent('MotDePasseAdmin', $r, 'P2 masque dans un tableau');

Verif::bloc('redact() : robustesse');

Verif::egal('', gds3710::redact(''), 'chaine vide rendue telle quelle');
Verif::egal('rien a masquer ici', gds3710::redact('rien a masquer ici'),
    'un texte sans secret est inchange');
/* Une valeur non textuelle ne doit pas faire tomber la journalisation. */
$r = gds3710::redact(null);
Verif::vrai(is_string($r), 'null rend une chaine, pas une erreur');
$r = gds3710::redact(42);
Verif::vrai(is_string($r), 'un entier rend une chaine');
