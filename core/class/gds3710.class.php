<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

/* * ***************************Includes********************************* */
require_once __DIR__  . '/../../../../core/php/core.inc.php';

class gds3710 extends eqLogic {
    /*     * *************************Attributs****************************** */
    
    /*     * ***********************Methode static*************************** */

    /*
     * Fonction exécutée automatiquement toutes les minutes par Jeedom
      public static function cron() {

      }
     */


    /*
     * Fonction exécutée automatiquement toutes les heures par Jeedom
      public static function cronHourly() {

      }
     */

    /*
     * Fonction exécutée automatiquement tous les jours par Jeedom
      public static function cronDaily() {

      }
     */

    /* Masque les secrets avant journalisation. Les logs de ce plugin sont regulierement
     * colles tels quels sur le forum de la communaute : ils ne doivent contenir ni mot de
     * passe, ni jeton de session, ni code d authentification. */
    public static function redact($_text) {
        $text = is_string($_text) ? $_text : print_r($_text, true);
        /* Forme URL et chaine de cookies : cle=valeur */
        $text = preg_replace('/(authcode=)[^&\s]+/i', '$1***', $text);
        $text = preg_replace('/(idcode=)[^&\s]+/i', '$1***', $text);
        $text = preg_replace('/((?:mjpeg_)?sess(?:ion)?=)[^;\s]+/i', '$1***', $text);
        $text = preg_replace('/(:\/\/[^:\/\s]+:)[^@\s]+@/', '$1***@', $text);
        /* Forme tableau produite par print_r : [cle] => valeur. Elle etait oubliee, et
         * les cookies dauthentification sont justement journalises sous cette forme. */
        $text = preg_replace('/(\[(?:mjpeg_)?sess(?:ion)?\]\s*=>\s*)\S+/i', '$1***', $text);
        $text = preg_replace('/(\[(?:password|passwd|pass|secret|authcode|idcode|token)\]\s*=>\s*)\S+/i', '$1***', $text);
        /* Les sections event, play et privacy du portier renvoient le mot de passe
         * administrateur en clair dans P2. Aucun chemin de code ne les journalise
         * aujourd hui ; ce masquage garantit que cela reste sans consequence. */
        $text = preg_replace('/(<P2>)[^<]*(<\/P2>)/', '$1***$2', $text);
        $text = preg_replace('/(\[P2\]\s*=>\s*)\S+/', '$1***', $text);
        return $text;
    }

    /* Parse une reponse du portier. Auparavant le constructeur SimpleXMLElement etait
     * appele directement sur le retour de curl : il levait une exception non rattrapee des
     * que le portier etait injoignable ou repondait autre chose que du XML, soit une erreur
     * 500 cote Jeedom au lieu dune ligne de log. */
    public static function parseXml($_raw, $_context = '') {
        $where = $_context === '' ? '' : ' (' . $_context . ')';
        if (!is_string($_raw) || trim($_raw) === '') {
            log::add('gds3710', 'error', 'Portier injoignable ou reponse vide' . $where);
            return null;
        }
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($_raw);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if ($xml === false) {
            log::add('gds3710', 'error', 'Reponse non XML du portier' . $where . ' : '
                . gds3710::redact(substr($_raw, 0, 200)));
            return null;
        }
        return $xml;
    }

    /* Traduit un chemin disque en URL servie par Jeedom, ou renvoie '' si aucune URL ne
     * le sert.
     *
     * Le calcul historique, recopie en quatre endroits, etait
     * substr($chemin, strpos($chemin, '/plugins')). Or la configuration autorise
     * explicitement un repertoire de captures absolu, hors de l arborescence de Jeedom :
     * strpos renvoie alors false, substr($chemin, false) equivaut a substr($chemin, 0), et
     * la commande recevait le chemin disque entier en guise d URL. Le widget affichait une
     * image cassee sans que rien ne l explique. */
    public static function urlPublique($_chemin) {
        $reel = realpath($_chemin);
        $racine = realpath(__DIR__ . '/../../../..');
        if ($reel === false || $racine === false) {
            return '';
        }
        $reel = str_replace('\\', '/', $reel);
        $racine = rtrim(str_replace('\\', '/', $racine), '/');
        if (strpos($reel, $racine . '/') !== 0) {
            log::add('gds3710', 'warning', 'La capture ' . $_chemin . ' est hors de la racine web de '
                . 'Jeedom : aucune URL ne peut la servir, la tuile « Dernier snapshot » restera vide. '
                . 'Choisissez un repertoire de captures situe sous ' . $racine . '.');
            return '';
        }
        return substr($reel, strlen($racine));
    }

    /* Purge quotidienne des captures. Le repertoire nen avait aucune : il grossissait
     * indefiniment (670 fichiers et 51 Mo sur linstallation de reference). Une retention
     * a 0 desactive la purge, ce qui reste le comportement historique. */
    public static function cronDaily() {
        $days = (int) config::byKey('snapshot_retention_days', 'gds3710', 0);
        if ($days <= 0) {
            return;
        }
        $root = realpath(calculPath(config::byKey('recdir', 'gds3710')));
        if ($root === false) {
            log::add('gds3710', 'error', 'Purge annulee : repertoire des captures introuvable.');
            return;
        }
        $limit = time() - ($days * 86400);
        $prefix = $root . DIRECTORY_SEPARATOR;
        $removed = 0;
        $freed = 0;

        foreach (eqLogic::byType('gds3710') as $eq) {
            $dir = realpath($root . '/' . $eq->getId());
            if ($dir === false || strpos($dir . DIRECTORY_SEPARATOR, $prefix) !== 0) {
                continue;
            }
            foreach ((array) glob($dir . '/*') as $file) {
                $real = realpath($file);
                if ($real === false || !is_file($real) || strpos($real, $prefix) !== 0) {
                    continue;
                }
                if (filemtime($real) >= $limit) {
                    continue;
                }
                $size = filesize($real);
                if (@unlink($real)) {
                    $removed++;
                    $freed += $size;
                }
            }
            /* Si la derniere capture connue vient detre purgee, les deux commandes qui la
             * referencent pointent dans le vide : on les remet a jour. */
            $path = $eq->getCmd('info', 'Lastest_Snapshot_Path');
            if (is_object($path) && $path->execCmd() != '' && !file_exists((string) $path->execCmd())) {
                $files = glob($dir . '/*.jpg');
                $url = $eq->getCmd('info', 'Lastest_Snapshot_URL');
                if (is_array($files) && count($files) > 0) {
                    usort($files, function ($a, $b) { return filemtime($b) - filemtime($a); });
                    $path->event($files[0]);
                    if (is_object($url)) { $url->event(gds3710::urlPublique($files[0])); }
                } else {
                    $path->event('');
                    if (is_object($url)) { $url->event(''); }
                }
            }
        }

        if ($removed > 0) {
            log::add('gds3710', 'info', 'Purge des captures : ' . $removed . ' fichier(s) supprime(s), '
                . round($freed / 1048576, 1) . ' Mo liberes (retention ' . $days . ' jours).');
        }
    }

    /* ------------------------------------------------------------------ *
     *  Acces HTTP au portier                                              *
     * ------------------------------------------------------------------ */

    /* Sels d'authentification du portier. Ils etaient recopies en clair a trois endroits,
     * dont un hors de cette classe : deux valeurs magiques, faciles a confondre puisque
     * seules quelques lettres les separent.
     *
     * Les deux mecanismes sont bien distincts : SEL_SESSION ouvre la session
     * d'administration (une seule a la fois sur l'appareil), SEL_MEDIA autorise la
     * consultation d'une capture ou du flux et ne la perturbe pas. */
    const SEL_SESSION = 'GDS3710lZpRsFzCbM';
    const SEL_MEDIA = 'GDS3710lDyTlHwNgZ';

    /* Options communes a tout appel vers le portier.
     *
     * Le certificat de l'appareil est auto-signe et son nom ne correspond a rien : la
     * verification ne peut pas etre activee sans couper toute communication. Le delai
     * d'attente, lui, etait absent des appels de capture — une requete pouvait donc
     * bloquer indefiniment le cron ou la page qui l'avait declenchee. */
    public static function optionsHttp($_extra = array()) {
        return $_extra + array(
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        );
    }

    private static function httpGet($_url, $_cookie = '') {
        $ch = curl_init();
        $opt = self::optionsHttp(array(CURLOPT_URL => $_url, CURLOPT_TIMEOUT => 10));
        if ($_cookie !== '') {
            $opt[CURLOPT_COOKIE] = $_cookie;
        }
        curl_setopt_array($ch, $opt);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    /* Recompose une chaine de cookies a partir des en-tetes d'une reponse HTTP.
     *
     * Isolee de la requete pour etre eprouvee : ce decoupage etait ecrit au milieu de
     * take_snapshot(), donc intestable, alors qu'une chaine vide y passe inapercue et
     * fait echouer la capture bien plus loin, sans rapport apparent. */
    public static function extraireCookies($_entetes) {
        if (!is_string($_entetes) || $_entetes === '') {
            return '';
        }
        /* La classe de caracteres exclut aussi la fin de ligne. Avec le [^;]* d'origine,
         * un Set-Cookie sans attribut — donc sans « ; » pour arreter la capture — emportait
         * le retour chariot dans la valeur du cookie, qui partait corrompu a la requete
         * suivante. Le portier envoie « ; path=/ » aujourd'hui, ce qui masquait le defaut. */
        preg_match_all('/^Set-Cookie:\s*([^;\r\n]*)/mi', $_entetes, $matches);
        $cookies = array();
        foreach ($matches[1] as $item) {
            $cookie = array();
            parse_str($item, $cookie);
            $cookies = array_merge($cookies, $cookie);
        }
        $chaine = '';
        foreach ($cookies as $clef => $valeur) {
            $chaine .= $clef . '=' . $valeur . ';';
        }
        return rtrim($chaine, ';');
    }

    /* Ouvre une session de consultation media et rend la chaine de cookies a rejouer.
     *
     * Distincte de openSession() : sel different, point d'entree « type=1 », et cookies
     * lus dans les en-tetes de la reponse au lieu d'etre recomposes. Elle ne consomme donc
     * pas la session d'administration, dont l'appareil n'admet qu'un exemplaire.
     *
     * Ce code vivait au milieu de take_snapshot(), avec ses options cURL recopiees trois
     * fois — dont un CURLOPT_RETURNTRANSFER pose deux fois dans le meme tableau. */
    public function sessionMedia() {
        $ip = trim((string) $this->getConfiguration('ip'));
        $password = (string) $this->getConfiguration('password');
        if ($ip === '' || $password === '') {
            log::add('gds3710', 'error', 'Adresse ou mot de passe du portier absent : capture impossible.');
            return null;
        }

        $defi = gds3710::parseXml(
            self::httpGet('https://' . $ip . '/goform/login?cmd=login&user=admin&type=1'),
            'defi de capture');
        if ($defi === null) {
            return null;
        }
        $challenge = (string) $defi->ChallengeCode[0];
        if ($challenge === '') {
            log::add('gds3710', 'error', 'Le portier n a pas renvoye de defi d authentification pour la capture.');
            return null;
        }

        /* Ne jamais journaliser la chaine hachee : elle porte le mot de passe en clair. */
        $authcode = md5($challenge . ':' . gds3710::SEL_MEDIA . ':' . $password);

        $ch = curl_init();
        curl_setopt_array($ch, self::optionsHttp(array(
            CURLOPT_URL => 'https://' . $ip . '/goform/login?cmd=login&user=admin&authcode='
                . $authcode . '&type=1',
            CURLOPT_HEADER => true,
        )));
        $reponse = curl_exec($ch);
        curl_close($ch);

        $cookies = gds3710::extraireCookies($reponse);
        if ($cookies === '') {
            log::add('gds3710', 'error', 'Authentification refusee pour la capture sur ' . $this->getHumanName()
                . ' : aucun cookie de session recu. Verifiez le mot de passe administrateur.');
            return null;
        }
        return $cookies;
    }

    /* Ouvre une session sur le portier et renvoie la chaine de cookies a rejouer.
     *
     * ATTENTION : le GDS3710 nadmet quUNE SEULE session administrateur. Chaque
     * authentification invalide la precedente — y compris celle dun humain en train
     * dutiliser linterface web du portier. Cest pourquoi le releve des capteurs
     * tourne sur cron15 et non sur cron, et pourquoi la session est mise en cache
     * pour etre reutilisee entre deux appels rapproches. */
    public function openSession() {
        $ip = trim((string) $this->getConfiguration('ip'));
        $password = (string) $this->getConfiguration('password');
        if ($ip === '' || $password === '') {
            return null;
        }

        $cached = cache::byKey('gds3710::session::' . $this->getId())->getValue(null);
        if ($cached !== null && $cached !== '') {
            return $cached;
        }

        $xml = gds3710::parseXml(self::httpGet('https://' . $ip . '/goform/login?cmd=login&user=admin&type=0'), 'defi de session');
        if ($xml === null) {
            return null;
        }
        $challenge = (string) $xml->ChallengeCode[0];
        if ($challenge === '') {
            return null;
        }
        $authcode = md5($challenge . ':' . gds3710::SEL_SESSION . ':' . $password);
        $res = gds3710::parseXml(self::httpGet('https://' . $ip . '/goform/login?cmd=login&user=admin&authcode=' . $authcode), 'ouverture de session');
        if ($res === null || (string) $res->ResCode[0] !== '0') {
            log::add('gds3710', 'error', 'Authentification refusee par le portier ' . $this->getHumanName()
                . '. Verifiez le mot de passe administrateur.');
            return null;
        }
        $cookie = 'session=' . $authcode . ';uname=admin;level=1';
        /* Duree volontairement courte : la session du portier expire delle-meme entre
         * 90 et 300 secondes dinactivite. */
        cache::set('gds3710::session::' . $this->getId(), $cookie, 90);
        return $cookie;
    }

    /* Jette la session en cache. A appeler des qu'on sait qu'elle ne vaut plus rien :
     * apres un redemarrage du portier, ou quand une requete la revele expiree. */
    public function oublierSession() {
        cache::set('gds3710::session::' . $this->getId(), '', 1);
    }

    /* Lit une section de configuration du portier et la renvoie sous forme de tableau. */
    /* $_cookie permet de reutiliser une session deja ouverte par lappelant. Sans cela,
     * un appelant qui vient de souvrir sa propre session invalide celle mise en cache
     * ici — le portier nen tolere quune seule — et la lecture echoue silencieusement. */
    public function readConfigSection($_type, $_cookie = null) {
        $cookie = $_cookie !== null ? $_cookie : $this->openSession();
        if ($cookie === null) {
            return null;
        }
        $ip = trim((string) $this->getConfiguration('ip'));
        $xml = gds3710::parseXml(self::httpGet('https://' . $ip . '/goform/config?cmd=get&type=' . urlencode($_type), $cookie), 'lecture ' . $_type);
        if ($xml === null) {
            if ($_cookie === null) {
                /* La session a peut-etre ete invalidee entre-temps : on la jette. */
                $this->oublierSession();
            }
            return null;
        }
        $out = array();
        foreach ($xml->children() as $key => $value) {
            $out[(string) $key] = (string) $value;
        }
        return $out;
    }

    /* ------------------------------------------------------------------ *
     *  Capteurs remontes par cmd=get&type=sysinfo                         *
     * ------------------------------------------------------------------ */

    /* Etats lus sur l appareil, sans equivalent pilotable. Les reglages d image ne
     * prennent effet qu au redemarrage suivant du portier, mais la valeur stockee,
     * elle, change immediatement : c est cette valeur qui est rapportee ici. */
    public static function get_state_list() {
        return array(
            'cmos_mode' => array(
                'name' => __('Mode CMOS', __FILE__), 'p' => 'P10572', 'section' => 'cmos', 'subType' => 'string',
                'labels' => array('1' => 'Normal', '2' => 'Low Light', '3' => 'WDR'),
            ),
            'ldc_state' => array(
                'name' => __('LDC (correction de distorsion)', __FILE__), 'p' => 'P10573', 'section' => 'cmos', 'subType' => 'binary',
            ),
            'power_frequency' => array(
                'name' => __('Fréquence secteur', __FILE__), 'p' => 'P12314', 'section' => 'cmos', 'subType' => 'string',
                'labels' => array('0' => '50 Hz', '1' => '60 Hz'),
            ),
            'shutter_speed' => array(
                'name' => __('Vitesse d\'obturation', __FILE__), 'p' => 'P10503', 'section' => 'cmos', 'subType' => 'string',
                'labels' => array('0' => 'Auto', '30' => '1/30 s', '60' => '1/60 s', '125' => '1/125 s',
                                  '250' => '1/250 s', '500' => '1/500 s', '1000' => '1/1000 s',
                                  '2000' => '1/2000 s', '5000' => '1/5000 s', '10000' => '1/10000 s'),
            ),
            'audio_codec' => array(
                'name' => __('Codec audio', __FILE__), 'p' => 'P14000', 'section' => 'audio', 'subType' => 'string',
                'labels' => array('1' => 'PCMU', '2' => 'PCMA', '4' => 'G722'),
            ),
            'osd_time' => array(
                'name' => __('Horodatage incrusté', __FILE__), 'p' => 'P10044', 'section' => 'osd', 'subType' => 'binary',
            ),
            'osd_text_shown' => array(
                'name' => __('Texte incrusté', __FILE__), 'p' => 'P10045', 'section' => 'osd', 'subType' => 'binary',
            ),
            'osd_text' => array(
                'name' => __('Texte incrusté - contenu', __FILE__), 'p' => 'P10040', 'section' => 'osd', 'subType' => 'string',
            ),
            'ntp_enabled' => array(
                'name' => __('NTP actif', __FILE__), 'p' => 'P5006', 'section' => 'date', 'subType' => 'binary',
            ),
            'ntp_server' => array(
                'name' => __('Serveur NTP', __FILE__), 'p' => 'P30', 'section' => 'date', 'subType' => 'string',
            ),
            'dst_enabled' => array(
                'name' => __('Heure d\'été', __FILE__), 'p' => 'P10004', 'section' => 'date', 'subType' => 'binary',
            ),
            'timezone' => array(
                'name' => __('Fuseau horaire', __FILE__), 'p' => 'P14046', 'section' => 'date', 'subType' => 'string',
            ),

            /* Maintien de porte ouverte. « Immediat » deverrouille la porte et l y laisse
             * pendant la duree configuree ; « Planifie » suit la table horaire de
             * l appareil. L etat est expose pour qu un scenario puisse verifier qu une
             * porte n est pas restee ouverte. */
            'keep_open_1' => array(
                'name' => __('Maintien porte 1', __FILE__), 'p' => 'P15429', 'section' => 'sch_open_door', 'subType' => 'string',
                'labels' => array('0' => 'Désactivé', '1' => 'Immédiat', '2' => 'Planifié'),
            ),
            'keep_open_2' => array(
                'name' => __('Maintien porte 2', __FILE__), 'p' => 'P15455', 'section' => 'sch_open_door', 'subType' => 'string',
                'labels' => array('0' => 'Désactivé', '1' => 'Immédiat', '2' => 'Planifié'),
            ),
            /* L appareil renvoie « (null) » quand la porte n est pas forcee ouverte. */
            'forced_open_1' => array(
                'name' => __('Porte 1 forcée ouverte depuis', __FILE__), 'p' => 'forced_door_open_time',
                'section' => 'sch_open_door', 'subType' => 'string',
            ),
            'forced_open_2' => array(
                'name' => __('Porte 2 forcée ouverte depuis', __FILE__), 'p' => 'forced_door2_open_time',
                'section' => 'sch_open_door', 'subType' => 'string',
            ),

            /* Detection de mouvement. Elle vit dans la section « event », qui renvoie le
             * mot de passe administrateur en clair dans P2 : ne jamais journaliser cette
             * section brute. redact() masque P2, et readConfigSection() ne journalise pas
             * son contenu. */
            'motion_detection' => array(
                'name' => __('Détection de mouvement', __FILE__), 'p' => 'P10250', 'section' => 'event', 'subType' => 'binary',
            ),
            'motion_schedule' => array(
                'name' => __('Détection - planning', __FILE__), 'p' => 'P14221', 'section' => 'event', 'subType' => 'string',
                'labels' => array('0' => 'Toute la journée', '1' => 'Planning 1', '2' => 'Planning 2',
                                  '3' => 'Planning 3', '4' => 'Planning 4', '5' => 'Planning 5',
                                  '6' => 'Planning 6', '7' => 'Planning 7', '8' => 'Planning 8',
                                  '9' => 'Planning 9', '10' => 'Planning 10'),
            ),
            /* Les huit regions doivent etre definies ensemble et se dessinent dans
             * l interface de l appareil. On les rapporte sans les ecrire : une detection
             * activee sans aucune region ne se declenchera pas, et cela ne se voit
             * nulle part ailleurs. */
            'motion_region' => array(
                'name' => __('Détection - régions', __FILE__), 'p' => 'P14224', 'section' => 'event', 'subType' => 'string',
            ),
        );
    }

    /* Sections de configuration citees par une table de declaration, sans doublon. */
    public static function sectionsDe($_table) {
        $sections = array();
        foreach ($_table as $def) {
            if (isset($def['section']) && $def['section'] !== '') {
                $sections[$def['section']] = true;
            }
        }
        return array_keys($sections);
    }

    /* Lit plusieurs sections en une passe et rend leur contenu fusionne.
     *
     * Les P-values des deux tables de declaration ne se recouvrent pas — un test le
     * garantit — la fusion ne peut donc pas faire disparaitre une valeur au profit
     * d une autre. */
    public function lireSections($_sections) {
        $lu = array();
        foreach ($_sections as $section) {
            $contenu = $this->readConfigSection($section);
            if (is_array($contenu)) {
                $lu = array_merge($lu, $contenu);
            }
        }
        return $lu;
    }

    /* Relit les sections concernees et met a jour les etats. Une seule lecture par
     * section, quel que soit le nombre d etats qu elle porte : chaque requete pese
     * sur un appareil qui ne tolere qu une session administrateur.
     *
     * $_lu permet de fournir des sections deja lues. Sans lui, refreshSettings() et
     * refreshStates() relisaient chacun de leur cote, alors que « audio », « event » et
     * « sch_open_door » figurent dans les DEUX tables : ces trois sections partaient
     * donc deux fois a chaque passage du cron. Les appels qui suivent une ecriture, eux,
     * ne passent rien et relisent bel et bien l appareil — c est precisement leur role,
     * confirmer que la valeur a ete prise. */
    public function refreshStates($_lu = null) {
        $lu = is_array($_lu)
            ? $_lu
            : $this->lireSections(gds3710::sectionsDe(gds3710::get_state_list()));
        if (count($lu) === 0) {
            return false;
        }
        foreach (gds3710::get_state_list() as $lid => $def) {
            if (!array_key_exists($def['p'], $lu)) {
                continue;
            }
            $cmd = $this->getCmd('info', $lid);
            if (!is_object($cmd)) {
                continue;
            }
            $brut = trim((string) $lu[$def['p']]);
            if ($def['subType'] === 'binary') {
                $valeur = ($brut === '1') ? 1 : 0;
            } elseif (isset($def['labels']) && isset($def['labels'][$brut])) {
                $valeur = $def['labels'][$brut];
            } else {
                $valeur = $brut;
            }
            $this->checkAndUpdateCmd($cmd, $valeur);
        }
        return true;
    }

    public static function get_sensor_list() {
        return array(
            'di0'                   => array('name' => __('Entrée digitale 1', __FILE__),   'subType' => 'binary'),
            'di1'                   => array('name' => __('Entrée digitale 2', __FILE__),   'subType' => 'binary'),
            'do'                    => array('name' => __('Sortie digitale', __FILE__),     'subType' => 'binary'),
            'atp_in'                => array('name' => __('Anti-arrachement', __FILE__),    'subType' => 'binary'),
            'doorctrl0'             => array('name' => __('Relais porte 1', __FILE__),      'subType' => 'string'),
            'doorctrl1'             => array('name' => __('Relais porte 2', __FILE__),      'subType' => 'string'),
            'systemp'               => array('name' => __('Température carte', __FILE__),   'subType' => 'numeric', 'unite' => '°C', 'historized' => 1),
            'sensortemp'            => array('name' => __('Température capteur', __FILE__), 'subType' => 'numeric', 'unite' => '°C', 'historized' => 1),
            'P15009'                => array('name' => __('Uptime', __FILE__),              'subType' => 'string'),
            'P70'                   => array('name' => __('Version firmware', __FILE__),    'subType' => 'string'),
            'Pfw_available_version' => array('name' => __('Mise à jour dispo', __FILE__),   'subType' => 'string'),
        );
    }

    /* ------------------------------------------------------------------ *
     *  Configuration automatique de la notification d evenements          *
     * ------------------------------------------------------------------ */

    /* Ecrit des P-values sur le portier.
     *
     * IMPERATIVEMENT en POST : une valeur contenant des & (le gabarit dURL en contient
     * sept) est tronquee au premier & si elle part en GET. Le portier decode dabord
     * lencodage pourcent, puis re-decoupe sa propre chaine de requete. Il repond
     * ResCode 0 / OK malgre la troncature, donc lecriture doit toujours etre relue. */
    /* Publique : gds3710Cmd::setConfig() ecrit par ce meme chemin, pour la meme raison. */
    public static function httpPostConfig($_ip, $_cookie, $_params) {
        $body = 'cmd=set';
        foreach ($_params as $key => $value) {
            $body .= '&' . $key . '=' . urlencode((string) $value);
        }
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => 'https://' . $_ip . '/goform/config',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIE => $_cookie,
            CURLOPT_TIMEOUT => 15,
        ));
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    /* Gabarit attendu. USERNAME et DOOR_NUM manquaient dans la documentation historique
     * alors que le plugin les exploite. */
    const EVENT_URL_TEMPLATE = 'mac=${MAC}&content=${WARNING_MSG}&type=${TYPE}&date=${DATE}&card=${CARDID}&sip=${SIPNUM}&username=${USERNAME}&doornum=${DOOR_NUM}';

    /* Calcule la configuration que le portier devrait porter pour parler a ce Jeedom. */
    public function expectedEventNotificationConfig() {
        $internal = (string) network::getNetworkAccess('internal');
        if (strpos($internal, '://') === false) {
            return null;
        }
        list($scheme, $host) = explode('://', $internal, 2);
        $host = rtrim($host, '/');

        $login = trim((string) config::byKey('login', 'gds3710'));
        $password = (string) config::byKey('password', 'gds3710');
        if ($login === '') {
            $login = 'jeedom';
            config::save('login', $login, 'gds3710');
        }
        if ($password === '') {
            $password = substr(md5(uniqid('', true) . mt_rand()), 0, 16);
            config::save('password', $password, 'gds3710');
        }

        return array(
            'P15410' => '1',                                   // notification activee
            'P15417' => ($scheme === 'https' ? '2' : '1'),      // 1 = HTTP, 2 = HTTPS
            'P15413' => $host . '/plugins/gds3710/core/php/jeeGDS3710.php',
            'P15414' => $login,
            'P15415' => $password,
            'P15416' => self::EVENT_URL_TEMPLATE,
            'P15553' => '0',                                   // POST
        );
    }

    /* Compare la configuration du portier a celle attendue. Renvoie les ecarts.
     * P15415 est exclu : le portier ne restitue jamais le mot de passe en clair. */
    public function checkEventNotificationConfig() {
        $expected = $this->expectedEventNotificationConfig();
        if ($expected === null) {
            return null;
        }
        $current = $this->readConfigSection('log');
        if ($current === null) {
            return null;
        }
        $diff = array();
        foreach ($expected as $key => $value) {
            if ($key === 'P15415') {
                continue;
            }
            $got = isset($current[$key]) ? html_entity_decode($current[$key], ENT_QUOTES, 'UTF-8') : '';
            if ($got !== $value) {
                $diff[$key] = array('actuel' => $got, 'attendu' => $value);
            }
        }
        return $diff;
    }

    /* Pousse la configuration puis la relit pour confirmer. */
    public function pushEventNotificationConfig() {
        $ip = trim((string) $this->getConfiguration('ip'));
        if ($ip === '') {
            log::add('gds3710', 'error', 'Adresse IP du portier absente : configuration impossible.');
            return false;
        }
        $expected = $this->expectedEventNotificationConfig();
        if ($expected === null) {
            log::add('gds3710', 'error', 'Adresse interne de Jeedom introuvable. Renseignez-la dans Reglages > Systeme > Configuration > Reseaux.');
            return false;
        }
        $cookie = $this->openSession();
        if ($cookie === null) {
            return false;
        }

        self::httpPostConfig($ip, $cookie, $expected);
        config::save('password_protection', 1, 'gds3710');

        $diff = $this->checkEventNotificationConfig();
        if ($diff === null) {
            log::add('gds3710', 'error', 'Configuration ecrite mais relecture impossible.');
            return false;
        }
        if (count($diff) > 0) {
            foreach ($diff as $key => $d) {
                log::add('gds3710', 'error', 'Ecriture non confirmee pour ' . $key
                    . ' : lu "' . $d['actuel'] . '", attendu "' . $d['attendu'] . '".');
            }
            return false;
        }
        log::add('gds3710', 'info', 'Portier ' . $this->getHumanName() . ' configure pour publier ses evenements vers '
            . $expected['P15413'] . ' (relecture confirmee).');
        return true;
    }

    /* ------------------------------------------------------------------ *
     *  Decomposition des evenements                                       *
     * ------------------------------------------------------------------ */

    /* Chaque evenement etait stocke tel quel, en JSON brut, dans une commande string
     * invisible. Inexploitable dans un scenario ou sur une tuile sans passer par des
     * fonctions de manipulation de chaine. Ces commandes portent les memes donnees,
     * decomposees. Elles sappliquent a tout evenement, quel que soit son type. */
    public static function get_event_detail_list() {
        return array(
            'last_event_type'     => array('name' => __('Dernier évènement - code', __FILE__)),
            'last_event_message'  => array('name' => __('Dernier évènement - libellé', __FILE__)),
            'last_event_date'     => array('name' => __('Dernier évènement - date', __FILE__)),
            'last_card'           => array('name' => __('Dernier badge', __FILE__)),
            'last_username'       => array('name' => __('Dernier utilisateur', __FILE__)),
            'last_doornum'        => array('name' => __('Dernière porte utilisée', __FILE__)),
            'last_sip'            => array('name' => __('Dernier numéro SIP', __FILE__)),
            'last_person_in'      => array('name' => __('Dernière personne entrée', __FILE__)),
            'last_security_alert' => array('name' => __('Dernière alerte sécurité', __FILE__)),
        );
    }

    /* Types dont on retient lidentite : quelquun a ouvert la porte et sest identifie. */
    public static function get_entry_event_types() {
        return array(100, 101, 200, 300, 301, 302, 400, 401, 600, 700, 800);
    }

    /* Types qui relevent de la securite et meritent une trace visible. */
    public static function get_security_event_types() {
        return array(102, 1000, 1002, 1100, 1110, 1200, 1300);
    }

    /* Renseigne les commandes decomposees a partir dun evenement recu. */
    /* Evenements marquant le debut d un appel emis par le portier. */
    public static function get_ring_event_types() {
        return array(500, 504);
    }

    public function isMissedCallReportEnabled() {
        return (bool) $this->getConfiguration('missed_call_message', 0);
    }

    private function missedCallKey() {
        return 'gds3710::sonnerie::' . $this->getId();
    }

    /* Arme le signalement. La capture est prise tout de suite : quelques secondes plus
     * tard le visiteur peut avoir quitte le champ. */
    public function armMissedCallReport($_date) {
        $url = '';
        $declencheur = $this->getCmd('action', 'snapshot');
        if (is_object($declencheur)) {
            try {
                $declencheur->execCmd();
                $lien = $this->getCmd('info', 'Lastest_Snapshot_URL');
                if (is_object($lien)) {
                    $url = (string) $lien->execCmd();
                }
            } catch (Exception $e) {
                log::add('gds3710', 'warning', 'Capture impossible pour le signalement d appel : ' . $e->getMessage());
            }
        }
        cache::set($this->missedCallKey(), json_encode(array(
            'date' => (string) $_date,
            'url'  => $url,
            'ts'   => time(),
        )), 3600);
    }

    /* Appele par le widget des qu il decroche : il n y a plus d appel manque. */
    public function cancelMissedCallReport() {
        cache::set($this->missedCallKey(), '', 1);
    }

    /* Verifie une sonnerie en attente et publie le message si le delai est ecoule. */
    public function checkMissedCall() {
        $brut = cache::byKey($this->missedCallKey())->getValue('');
        if ($brut === '' || $brut === null) {
            return false;
        }
        $sonnerie = json_decode($brut, true);
        if (!is_array($sonnerie) || !isset($sonnerie['ts'])) {
            $this->cancelMissedCallReport();
            return false;
        }
        $delai = (int) $this->getConfiguration('missed_call_delay', 45);
        if ($delai < 10) {
            $delai = 10;
        }
        if ((time() - (int) $sonnerie['ts']) < $delai) {
            return false;
        }
        $this->cancelMissedCallReport();

        $quand = ($sonnerie['date'] !== '') ? $sonnerie['date'] : date('Y-m-d H:i:s', (int) $sonnerie['ts']);
        $action = '';
        if ($sonnerie['url'] !== '') {
            /* Le centre de messages n accepte que <i> et <a> : la capture est donc
             * offerte en lien, pas en vignette. */
            $action = '<a href="' . $sonnerie['url'] . '" target="_blank">'
                . '<i class="fas fa-camera"></i> ' . __('Voir la capture', __FILE__) . '</a>';
        }
        message::add('gds3710', $this->getHumanName() . ' : '
            . __('appel non decroche depuis Jeedom', __FILE__) . ' - ' . $quand,
            $action, 'missed_call::' . $this->getId());
        log::add('gds3710', 'info', 'Appel non decroche signale pour ' . $this->getHumanName() . ' (' . $quand . ').');
        return true;
    }

    /* Une minute est la granularite la plus fine offerte par Jeedom : le signalement
     * peut donc arriver avec ce retard, ce qui est sans consequence pour un appel
     * deja manque. */
    public static function cron() {
        foreach (eqLogic::byType('gds3710', true) as $eqLogic) {
            try {
                $eqLogic->checkMissedCall();
            } catch (Exception $e) {
                log::add('gds3710', 'error', 'Verification d appel non decroche : ' . $e->getMessage());
            }
        }
    }

    public function dispatchEventDetails($_evt, $_type) {
        $catalogue = gds3710::get_GDS3710_event_list();
        $message = isset($catalogue[(string) $_type]) ? $catalogue[(string) $_type]['message'] : (string) $_type;

        $values = array(
            'last_event_type'    => (string) $_type,
            'last_event_message' => $message,
            'last_event_date'    => isset($_evt['date']) ? (string) $_evt['date'] : '',
            'last_card'          => isset($_evt['card']) ? (string) $_evt['card'] : '',
            'last_username'      => isset($_evt['username']) ? (string) $_evt['username'] : '',
            'last_doornum'       => isset($_evt['doornum']) ? (string) $_evt['doornum'] : '',
            'last_sip'           => isset($_evt['sip']) ? (string) $_evt['sip'] : '',
        );

        /* « Derniere personne entree » : lidentite la plus parlante disponible, et on ne
         * lecrase pas avec du vide quand levenement nen porte pas. */
        if (in_array((int) $_type, gds3710::get_entry_event_types(), true)) {
            $who = $values['last_username'];
            if ($who === '') { $who = $values['last_card'] !== '' ? __('badge ', __FILE__) . $values['last_card'] : ''; }
            if ($who === '') { $who = $message; }
            $values['last_person_in'] = $who . ' (' . $values['last_event_date'] . ')';
        }

        if (in_array((int) $_type, gds3710::get_security_event_types(), true)) {
            $values['last_security_alert'] = $message . ' - ' . $values['last_event_date'];
            log::add('gds3710', 'warning', 'Alerte securite sur ' . $this->getHumanName() . ' : ' . $message);
            message::add('gds3710', $this->getHumanName() . ' : ' . $message
                . ' (' . $values['last_event_date'] . ')');
        }

        if (in_array((int) $_type, gds3710::get_ring_event_types(), true) && $this->isMissedCallReportEnabled()) {
            $this->armMissedCallReport($values['last_event_date']);
        }

        foreach ($values as $lid => $value) {
            $cmd = $this->getCmd('info', $lid);
            if (is_object($cmd)) {
                $this->checkAndUpdateCmd($cmd, $value);
            }
        }
    }

    /* ------------------------------------------------------------------ *
     *  Reglages de confort du portier                                     *
     * ------------------------------------------------------------------ */

    /* Chaque entree donne une commande info (letat reel, relu par le cron) et un curseur
     * qui lecrit. Toutes ces P-values ont ete verifiees presentes sur un GDS3710 en
     * firmware 1.0.13.15 ; les plus recentes napparaissent quà partir de 1.0.13.5. */
    public static function get_setting_list() {
        return array(
            'blue_led_idle'    => array('name' => __('LED clavier - veille', __FILE__),            'p' => 'P15591', 'section' => 'door', 'min' => 1, 'max' => 255),
            'blue_led_pressed' => array('name' => __('LED clavier - appui', __FILE__),             'p' => 'P15592', 'section' => 'door', 'min' => 1, 'max' => 255),
            'img_brightness'   => array('name' => __('Image - luminosité', __FILE__),              'p' => 'P15520', 'section' => 'play', 'min' => 0, 'max' => 128),
            'img_contrast'     => array('name' => __('Image - contraste', __FILE__),               'p' => 'P15521', 'section' => 'play', 'min' => 0, 'max' => 128),
            'img_saturation'   => array('name' => __('Image - saturation', __FILE__),              'p' => 'P15522', 'section' => 'play', 'min' => 0, 'max' => 128),
            'snapshot_delay'   => array('name' => __('Délai avant capture (s)', __FILE__),         'p' => 'P15584', 'section' => 'door', 'min' => 0, 'max' => 10),
            'onhook_timer'     => array('name' => __('Raccrochage après ouverture (s)', __FILE__), 'p' => 'P15582', 'section' => 'door', 'min' => 3, 'max' => 1800),
            'volume_system'    => array('name' => __('Volume système', __FILE__),                 'p' => 'P14003', 'section' => 'audio', 'min' => 0, 'max' => 6),
            'volume_doorbell'  => array('name' => __('Volume sonnerie', __FILE__),                'p' => 'P14835', 'section' => 'audio', 'min' => 0, 'max' => 6),
            'keep_open_time_1' => array('name' => __('Maintien porte 1 - durée (min)', __FILE__),  'p' => 'P15430', 'section' => 'sch_open_door', 'min' => 5, 'max' => 480),
            'keep_open_time_2' => array('name' => __('Maintien porte 2 - durée (min)', __FILE__),  'p' => 'P15456', 'section' => 'sch_open_door', 'min' => 5, 'max' => 480),
            'motion_sensitivity' => array('name' => __('Détection - sensibilité', __FILE__),       'p' => 'P14223', 'section' => 'event', 'min' => 0, 'max' => 100),
        );
    }

    /* Ecrit des P-values sur le portier et renvoie true si la relecture confirme. */
    public function writeConfig($_params, $_section) {
        $ip = trim((string) $this->getConfiguration('ip'));
        $cookie = $this->openSession();
        if ($ip === '' || $cookie === null) {
            return false;
        }
        self::httpPostConfig($ip, $cookie, $_params);
        $current = $this->readConfigSection($_section);
        if ($current === null) {
            return false;
        }
        foreach ($_params as $key => $value) {
            if (!isset($current[$key]) || (string) $current[$key] !== (string) $value) {
                log::add('gds3710', 'error', 'Ecriture non confirmee pour ' . $key . ' : lu "'
                    . (isset($current[$key]) ? $current[$key] : '(absent)') . '", attendu "' . $value . '".');
                return false;
            }
        }
        return true;
    }

    /* Relit les reglages sur le portier et met a jour les commandes info associees.
     * Une seule lecture par section, pas une par reglage.
     *
     * $_lu : voir refreshStates(), meme mecanique et meme raison. */
    public function refreshSettings($_lu = null) {
        $data = is_array($_lu)
            ? $_lu
            : $this->lireSections(gds3710::sectionsDe(gds3710::get_setting_list()));
        if (count($data) === 0) {
            return false;
        }
        foreach (gds3710::get_setting_list() as $lid => $def) {
            if (!isset($data[$def['p']])) {
                continue;
            }
            $cmd = $this->getCmd('info', $lid);
            if (is_object($cmd) && is_numeric($data[$def['p']])) {
                $this->checkAndUpdateCmd($cmd, (float) $data[$def['p']]);
            }
        }
        /* Planning du retroeclairage : un interrupteur et deux horaires en HHMMSS. */
        $cmd = $this->getCmd('info', 'backlight_schedule');
        if (is_object($cmd) && isset($data['P15594'])) {
            $this->checkAndUpdateCmd($cmd, $data['P15594'] == '1' ? 1 : 0);
        }
        $cmd = $this->getCmd('info', 'backlight_hours');
        if (is_object($cmd) && isset($data['P15595']) && isset($data['P15596'])) {
            $this->checkAndUpdateCmd($cmd, $data['P15595'] . ' - ' . $data['P15596']);
        }
        return true;
    }

    /* ------------------------------------------------------------------ *
     *  Client SIP                                                         *
     * ------------------------------------------------------------------ */

    /* Le client SIP est considere configure des que le websocket et lURI sont
     * renseignes. Sans cela la commande reste presente mais inerte. */
    public function isSipConfigured() {
        return trim((string) $this->getConfiguration('client_sip_websocket')) !== ''
            && trim((string) $this->getConfiguration('client_sip_uri')) !== '';
    }

    /* Configuration transmise au widget.
     *
     * ATTENTION : elle contient le mot de passe du compte SIP. Elle ne doit JAMAIS
     * etre placee dans la valeur dune commande : une valeur de commande est persistee
     * en base, historisee, lisible par tout utilisateur voyant la tuile et exposee par
     * lAPI de Jeedom. Elle est servie par un appel ajax authentifie, reserve aux
     * utilisateurs ayant des droits sur cet equipement. */
    public function getSipConfig() {
        $flags = array(
            'is_sip_debug_enabled'               => 0,
            'is_remote_call_audio_enabled'       => 1,
            'is_remote_call_video_enabled'       => 1,
            'is_remote_call_offer_audio_enabled' => 1,
            'is_remote_call_offer_video_enabled' => 1,
            'is_local_call_audio_enabled'        => 1,
            'is_local_call_video_enabled'        => 1,
        );
        $config = array(
            'client_sip_websocket' => (string) $this->getConfiguration('client_sip_websocket'),
            'client_sip_uri'       => (string) $this->getConfiguration('client_sip_uri'),
            'client_sip_password'  => (string) $this->getConfiguration('client_sip_password'),
            'portier_sip_uri'      => (string) $this->getConfiguration('portier_sip_uri'),
            /* Flux affiche pendant la sonnerie, avant tout decrochage. On sert le notre
             * plutot que l URL annoncee par le portier dans son en-tete Call-Info : ce
             * dernier repond 404 sur ce firmware, et son certificat auto-signe ferait
             * echouer le chargement sans le moindre message. */
            'preview_url'          => '/plugins/gds3710/core/php/camera.php?id=' . $this->getId(),
            'equipment_id'         => (int) $this->getId(),
            'equipment_name'       => (string) $this->getName(),
        );
        foreach ($flags as $key => $default) {
            $value = $this->getConfiguration($key, $default);
            $config[$key] = ($value === '' || $value === null) ? (bool) $default : (bool) $value;
        }
        /* Le client SIP de JsSIP produit un INVITE tres long. Certains serveurs le
         * refusent au-dela dune taille limite : ces deux reglages permettent de
         * lalleger. Voir la documentation. */
        $codecs = trim((string) $this->getConfiguration('sip-codec-removal'));
        $lines = trim((string) $this->getConfiguration('sip-invite-line-removal'));
        /* Boutons d ouverture de la fenetre d appel. On ne transmet que les commandes
         * visibles : masquer « Ouvrir la porte 2 » dans Jeedom retire son bouton, sans
         * reglage supplementaire. Le widget execute ces commandes plutot que d envoyer
         * le code par DTMF : le chemin HTTP est deja eprouve et n expose aucun secret
         * de plus au navigateur. */
        $config['door_commands'] = array();
        foreach (array('open', 'open2') as $logicalId) {
            $porte = $this->getCmd('action', $logicalId);
            if (is_object($porte) && $porte->getIsVisible()) {
                $config['door_commands'][] = array(
                    'id'   => (int) $porte->getId(),
                    'name' => (string) $porte->getName(),
                );
            }
        }

        $config['codec_to_remove'] = $codecs === '' ? array() : explode(',', $codecs);
        $config['invite_line_to_remove'] = $lines === '' ? array() : explode(',', $lines);
        return $config;
    }

    public static function get_GDS3710_event_list()
    {
        $return = array (
            "100" => array("section" =>'Ouverture porte', 'section_icon'=>'jeedom-porte-ferme', "type" => 100, "short_name" => "OpenDoorViaCard", "message" => "Open Door via Card", "use_case" => "Indicates that someone opens the door via card or key fob."),
            "101" => array("section" =>'Ouverture porte', 'section_icon'=>'jeedom-porte-ferme', "type" => 101, "short_name" => "OpenDoorViaCardOverWiegand", "message" => "Open Door via Card (over Wiegand)", "use_case" => "Indicates that someone opens the door via card or key fob using Wiegand interface connected to GDS."),
            "200" => array("section" =>'Ouverture porte', 'section_icon'=>'jeedom-porte-ferme', "type" => 200, "short_name" => "VisitingLog", "message" => "Visiting Log", "use_case" => "Indicates that door has been opened for visitor which pressed door bell button."),
            "300" => array("section" =>'Ouverture porte', 'section_icon'=>'jeedom-porte-ferme', "type" => 300, "short_name" => "OpenDoorViaUniversalPIN", "message" => "Open Door via Universal PIN", "use_case" => "Indicates that door has been opened successfully using local PIN code via GDS keypad."),
            "301" => array("section" =>'Ouverture porte', 'section_icon'=>'jeedom-porte-ferme', "type" => 301, "short_name" => "OpenDoorViaPrivatePIN", "message" => "Open Door via Private PIN", "use_case" => "Indicates that someone opened the door successfully using their private PIN code via GDS keypad."),
            "302" => array("section" =>'Ouverture porte', 'section_icon'=>'jeedom-porte-ferme', "type" => 302, "short_name" => "OpenDoorViaGuestPIN", "message" => "Open Door via Guest PIN", "use_case" => "Indicates that a guest used “Guest PIN” code to open the door using GDS keypad."),
            "600" => array("section" =>'Ouverture porte', 'section_icon'=>'jeedom-porte-ferme', "type" => 600, "short_name" => "OpenDoorViaCardandPIN", "message" => "Open Door via Card and PIN", "use_case" => "Indicates that someone used his RFID card or key fob, plus his own private password to authenticate and open the door."),
            "400" => array("section" =>'Ouverture porte', 'section_icon'=>'jeedom-porte-ferme', "type" => 400, "short_name" => "OpenDoorViaDI", "message" => "Open Door via DI", "use_case" => "Indicates that door has been opened using DI (Digital Input) Signal, such as using a push button."),
            "700" => array("section" =>'Ouverture porte', 'section_icon'=>'jeedom-porte-ferme', "type" => 700, "short_name" => "OpenDoorViaRemotePIN", "message" => "Open Door via Remote PIN", "use_case" => "Indicates that someone did send remote PIN code to open the door using GDS manager tool for example."),
            "800" => array("section" =>'Ouverture porte', 'section_icon'=>'jeedom-porte-ferme', "type" => 800, "short_name" => "HttpAPIOpenDoor", "message" => "HTTP API Open Door", "use_case" => "Indicates that someone did send remote PIN code to open the door HTTP API command."),
            
            "500" => array("section" =>'Appel', 'section_icon'=>'techno-phone16', "type" => 500, "short_name" => "CallOutLog", "message" => "Call Out Log", "use_case" => "Indicates the GDS unit initiated a call out, for example when someone uses the keypad to dial a number or press door bell button which preconfigured destination number."),
            "501" => array("section" =>'Appel', 'section_icon'=>'techno-phone16', "type" => 501, "short_name" => "CallInLog", "message" => "Call In Log", "use_case" => "Indicates that call has been received by the GDS unit."),
            "504" => array("section" =>'Appel', 'section_icon'=>'techno-phone16', "type" => 504, "short_name" => "CallLogDoorBellCall", "message" => "Call Log (Door Bell Call)", "use_case" => "Indicates that someone has initiated a call using door bell button."),
            
            "601" => array("section" =>'Maintient ouverture porte', 'section_icon'=>'jeedom-porte-ouverte', "type" => 601, "short_name" => "KeepDoorOpenImmediately", "message" => "Keep Door Open (Immediately)", "use_case" => "Key door Open (immediately) action has been performed from the web Interface."),
            "602" => array("section" =>'Maintient ouverture porte', 'section_icon'=>'jeedom-porte-ouverte', "type" => 602, "short_name" => "KeepDoorOpenScheduled", "message" => "Keep Door Open (Scheduled)", "use_case" => "Key door Open (immediately) action has been set from the web Interface and the event is triggered."),

            "900" => array("section" =>'Sécurité', 'section_icon'=>'securite-key1', "type" => 900, "short_name" => "MotionDetection", "message" => "Motion Detection", "use_case" => "Indicates that motion detection is triggered."),
            "1000" => array("section" =>'Sécurité', 'section_icon'=>'securite-key1', "type" => 1000, "short_name" => "DIAlarm", "message" => "DI Alarm", "use_case" => "Indicates that alarm IN is triggered."),
            "1100" => array("section" =>'Sécurité', 'section_icon'=>'securite-key1', "type" => 1100, "short_name" => "DismantleByForce", "message" => "Dismantle by Force", "use_case" => "Indicates that the unit has been dismantled by force."),
            "1200" => array("section" =>'Sécurité', 'section_icon'=>'securite-key1', "type" => 1200, "short_name" => "HostageAlarm", "message" => "Hostage Alarm", "use_case" => "Indicates that someone has entered the hostage alarm PIN code to open the door."),
            "1300" => array("section" =>'Sécurité', 'section_icon'=>'securite-key1', "type" => 1300, "short_name" => "InvalidPassword", "message" => "Invalid Password", "use_case" => "Indicates that someone has entered wrong password PIN code to open the door for 5 attempts and corresponding alarm action has been triggered."),
            
            "1101" => array("section" =>'Surveillance Logiciel', 'section_icon'=>'fas fa-exclamation-triangle', "type" => 1101, "short_name" => "SystemUp", "message" => "System up", "use_case" => "Indicates that the system is UP."),
            "1102" => array("section" =>'Surveillance Logiciel', 'section_icon'=>'fas fa-exclamation-triangle', "type" => 1102, "short_name" => "Reboot", "message" => "Reboot", "use_case" => "Indicates that the GDS unit has been rebooted."),
            "1103" => array("section" =>'Surveillance Logiciel', 'section_icon'=>'fas fa-exclamation-triangle', "type" => 1103, "short_name" => "ResetClearAllData", "message" => "Reset (Clear All Data) ", "use_case" => "Factory reset (clear all data) has been performed."),
            "1104" => array("section" =>'Surveillance Logiciel', 'section_icon'=>'fas fa-exclamation-triangle', "type" => 1104, "short_name" => "ResetRetainNetworkDataOnly", "message" => "Reset (Retain Network Data Only)", "use_case" => "Factory reset (Retain Network Data Only) has been performed."),
            "1105" => array("section" =>'Surveillance Logiciel', 'section_icon'=>'fas fa-exclamation-triangle', "type" => 1105, "short_name" => "ResetRetainOnlyCardInformation)", "message" => "Reset (Retain Only Card Information)", "use_case" => "Factory reset (Retain Only Card Information) has been performed."),
            "1106" => array("section" =>'Surveillance Logiciel', 'section_icon'=>'fas fa-exclamation-triangle', "type" => 1106, "short_name" => "ResetRetainNetworkDataAndCardInformation)", "message" => "Reset (Retain Network Data and Card Information)", "use_case" => "Factory reset (Retain Network Data and Card Information) has been performed."),
            "1107" => array("section" =>'Surveillance Logiciel', 'section_icon'=>'fas fa-exclamation-triangle', "type" => 1107, "short_name" => "ResetWiegand", "message" => "Reset (Wiegand)", "use_case" => "Factory reset using Wiegand module has been performed on the unit"),
            "1108" => array("section" =>'Surveillance Logiciel', 'section_icon'=>'fas fa-exclamation-triangle', "type" => 1108, "short_name" => "ConfigUpdate", "message" => "Config Update", "use_case" => "Indicates that the system’s configuration has been updated."),
            "1109" => array("section" =>'Surveillance Logiciel', 'section_icon'=>'fas fa-exclamation-triangle', "type" => 1109, "short_name" => "FirmwareUpdate", "message" => "Firmware Update (1.0.0.0)", "use_case" => "Indicates that the system’s firmware has been upgraded."),
            
            "1400" => array("section" =>'Surveillance Matériel', 'section_icon'=>'fas fa-thermometer-full', "type" => 1400, "short_name" => "MainboardTemperatureNormal", "message" => "Mainboard Temperature(32°C) Normal", "use_case" => "Indicates that device’s mainboard temperature is normal, (around 32°C)."),
            "1401" => array("section" =>'Surveillance Matériel', 'section_icon'=>'fas fa-thermometer-full', "type" => 1401, "short_name" => "MainboardTemperatureTooLow", "message" => "Mainboard Temperature(32°C) Too Low", "use_case" => "Indicates that device’s mainboard temperature is too low."),
            "1402" => array("section" =>'Surveillance Matériel', 'section_icon'=>'fas fa-thermometer-full', "type" => 1402, "short_name" => "MainboardTemperatureTooHigh", "message" => "Mainboard Temperature(32°C) Too High", "use_case" => "Indicates that device’s mainboard temperature is too high."),
            "1403" => array("section" =>'Surveillance Matériel', 'section_icon'=>'fas fa-thermometer-full', "type" => 1403, "short_name" => "SensorTemperatureNormal", "message" => "Sensor Temperature(32°C) Normal", "use_case" => "Indicates that device's sensor temperature is normal, (around 32°C)."),
            "1404" => array("section" =>'Surveillance Matériel', 'section_icon'=>'fas fa-thermometer-full', "type" => 1404, "short_name" => "SensorTemperatureTooLow", "message" => "Sensor Temperature(32°C) Too Low", "use_case" => "Indicates that device's sensor temperature is normal too low."),
            "1405" => array("section" =>'Surveillance Matériel', 'section_icon'=>'fas fa-thermometer-full', "type" => 1405, "short_name" => "SensorTemperatureTooHigh", "message" => "Sensor Temperature(32°C) Too High", "use_case" => "Indicates that device's sensor temperature is normal too high."),

            /* Types ajoutes le 2026-09-06. Ils sont declares par le firmware 1.0.13.15 et
             * absents du catalogue d'origine : un portier a jour en emet, et sur l'ancien
             * code un type inconnu interrompait toute la remontee d'evenements.
             * Releves dans le filtre du journal du portier, et pour 1500 et 1503 observes
             * en fonctionnement. La famille 1500 couvre les connexions administrateur. */
            "102" => array("section" =>'Ouverture porte', 'section_icon'=>'jeedom-porte-ferme', "type" => 102, "short_name" => "UnauthorizedDoorOpeningAttempt", "message" => "Unauthorized Door Opening Attempt", "use_case" => "Indicates that someone attempted to open the door without authorization."),
            "401" => array("section" =>'Ouverture porte', 'section_icon'=>'jeedom-porte-ferme', "type" => 401, "short_name" => "OpenDoorViaSI", "message" => "Open Door via SI", "use_case" => "Indicates that door has been opened using SI (Special Input) signal."),
            "1002" => array("section" =>'Sécurité', 'section_icon'=>'securite-key1', "type" => 1002, "short_name" => "DoorLockAbnormalAlarm", "message" => "Door and Lock Abnormal Alarm", "use_case" => "Indicates an abnormal state of the door or of the lock."),
            "1110" => array("section" =>'Sécurité', 'section_icon'=>'securite-key1', "type" => 1110, "short_name" => "NonScheduledAccess", "message" => "Non-scheduled Access", "use_case" => "Indicates an access outside of the authorized schedule."),
            "1500" => array("section" =>'Surveillance Logiciel', 'section_icon'=>'fas fa-exclamation-triangle', "type" => 1500, "short_name" => "AdminLogIn", "message" => "Admin Log In", "use_case" => "Indicates that an administrator signed in on the device web interface."),
            "1503" => array("section" =>'Surveillance Logiciel', 'section_icon'=>'fas fa-exclamation-triangle', "type" => 1503, "short_name" => "AdminLogOff", "message" => "Admin Log Off", "use_case" => "Indicates that an administrator session ended, by logout or timeout."),
        );
        return $return;
    }

    /* Commandes fixes de l'equipement.
     *
     * Elles etaient creees par 23 blocs recopies, de dix a vingt lignes chacun, pour un
     * total de plus de 400 lignes dans postSave(). Rien ne les distinguait qu'une poignee
     * de valeurs : c'est une table, pas du code. Le motif descriptif existait deja pour
     * les etats, les capteurs et les reglages ; il manquait ici.
     *
     * Les commandes engendrees par une regle — types d'evenements, reglages et leur
     * curseur, details d'evenement — restent dans leurs boucles : leur nombre depend d'une
     * autre table, elles n'ont pas leur place dans une enumeration.
     *
     * « visible » est une valeur POSEE A LA CREATION, jamais reimposee ensuite : masquer
     * une commande depuis Jeedom doit tenir. Vingt de ces commandes la reimposaient a
     * chaque enregistrement de l'equipement, « Ouvrir la porte 2 » comprise — alors que la
     * documentation du client SIP promet que la masquer retire son bouton de la fenetre
     * d'appel. Meme regle que pour le nom, corrigee en juillet pour les memes raisons. */
    public static function get_command_list() {
        return array(
            'ldc_ON' => array(
                'name' => __('LDC - ON', __FILE__), 'type' => 'action', 'subType' => 'other', 'visible' => 1),
            'ldc_off' => array(
                'name' => __('LDC - OFF', __FILE__), 'type' => 'action', 'subType' => 'other', 'visible' => 1),
            'reboot' => array(
                'name' => __('Reboot', __FILE__), 'type' => 'action', 'subType' => 'other', 'visible' => 1),
            'open' => array(
                'name' => __('Ouvrir la porte', __FILE__), 'type' => 'action', 'subType' => 'other', 'visible' => 1),
            'open2' => array(
                'name' => __('Ouvrir la porte 2', __FILE__), 'type' => 'action', 'subType' => 'other', 'visible' => 1),
            'close' => array(
                'name' => __('Fermer la porte', __FILE__), 'type' => 'action', 'subType' => 'other', 'visible' => 0),
            'close2' => array(
                'name' => __('Fermer la porte 2', __FILE__), 'type' => 'action', 'subType' => 'other', 'visible' => 0),
            'snapshot' => array(
                'name' => __('Prendre un snapshot', __FILE__), 'type' => 'action', 'subType' => 'other', 'visible' => 1,
                'template' => array('dashboard' => ''),
                'display' => array('icon' => '<i class="fa fa-image"></i>')),
            'modifyConfig' => array(
                'name' => __('Modifier la configuration', __FILE__), 'type' => 'action', 'subType' => 'message', 'visible' => 0,
                'display' => array(
                    'title_placeholder' => __('ID de la commande à modifier', __FILE__),
                    'message_placeholder' => __('Valeur', __FILE__),
                    'message_cmd_type' => 'action',
                    'message_cmd_subtype' => 'message')),
            'sendSnapshot' => array(
                'name' => __('Envoyer un snapshot', __FILE__), 'type' => 'action', 'subType' => 'message', 'visible' => 0,
                'configuration' => array('request' => '-'),
                'display' => array(
                    'title_placeholder' => __('Nombre captures ou options', __FILE__),
                    'message_placeholder' => __('Commande message d\'envoi des captures', __FILE__),
                    'message_cmd_type' => 'action',
                    'message_cmd_subtype' => 'message')),
            'Open_Snapshots_Folder' => array(
                'name' => __('Ouvrir le dossier des captures', __FILE__), 'type' => 'action', 'subType' => 'other', 'visible' => 1,
                'template' => array('dashboard' => 'snapshot_folder')),
            'Lastest_Snapshot_Path' => array(
                'name' => __('Chemin du dernier snapshot', __FILE__), 'type' => 'info', 'subType' => 'string', 'visible' => 0),
            'Lastest_Snapshot_URL' => array(
                'name' => __('Dernier snapshot', __FILE__), 'type' => 'info', 'subType' => 'string', 'visible' => 0,
                'template' => array('dashboard' => 'lastsnapshot', 'mobile' => 'lastsnapshot')),
            'cmos_normal' => array(
                'name' => __('CMOS - Normal', __FILE__), 'type' => 'action', 'subType' => 'other', 'visible' => 1),
            'cmos_lowlight' => array(
                'name' => __('CMOS - Low Light', __FILE__), 'type' => 'action', 'subType' => 'other', 'visible' => 1),
            'cmos_wdr' => array(
                'name' => __('CMOS - WDR', __FILE__), 'type' => 'action', 'subType' => 'other', 'visible' => 1),
            'stream_mjpeg' => array(
                'name' => __('Stream MJPEG', __FILE__), 'type' => 'info', 'subType' => 'string', 'visible' => 1,
                'template' => array('dashboard' => 'mjpegstream', 'mobile' => 'mjpegstream')),
            'Last event' => array(
                'name' => __('Last event', __FILE__), 'type' => 'info', 'subType' => 'string', 'visible' => 0),
            'sip_client' => array(
                'name' => __('Client SIP', __FILE__), 'type' => 'info', 'subType' => 'string', 'visible' => 0,
                'template' => array('dashboard' => 'sipclient')),
            'backlight_schedule' => array(
                'name' => __('Rétroéclairage - planning actif', __FILE__), 'type' => 'info', 'subType' => 'binary', 'visible' => 0),
            'backlight_hours' => array(
                'name' => __('Rétroéclairage - horaires', __FILE__), 'type' => 'info', 'subType' => 'string', 'visible' => 0),
            'backlight_hours_set' => array(
                'name' => __('Rétroéclairage - définir les horaires', __FILE__), 'type' => 'action', 'subType' => 'message', 'visible' => 0,
                'display' => array(
                    'title_placeholder' => __('Début, format HHMMSS', __FILE__),
                    'message_placeholder' => __('Fin, format HHMMSS', __FILE__))),
            'configureDoorbell' => array(
                'name' => __('Configurer le portier', __FILE__), 'type' => 'action', 'subType' => 'other', 'visible' => 0,
                'display' => array('icon' => '<i class="fas fa-cogs"></i>')),
        );
    }

    /* Cree ou met a jour une commande a partir de sa description.
     *
     * Le nom et la visibilite ne sont poses qu'a la creation : ce sont des choix que
     * l'utilisateur peut reprendre, et les reimposer a chaque enregistrement les effacait
     * sans un mot. Le type, le sous-type, le gabarit et les reglages d'affichage, eux,
     * sont structurels : le plugin les maintient. */
    private function poserCommande($_lid, $_def) {
        $cmd = $this->getCmd($_def['type'], $_lid);
        if (!is_object($cmd)) {
            $cmd = new gds3710Cmd();
            $cmd->setIsVisible(isset($_def['visible']) ? $_def['visible'] : 0);
        }
        if (trim((string) $cmd->getName()) === '') {
            $cmd->setName($_def['name']);
        }
        $cmd->setEqLogic_id($this->getId());
        $cmd->setLogicalId($_lid);
        $cmd->setType($_def['type']);
        $cmd->setSubType($_def['subType']);
        if (isset($_def['template'])) {
            foreach ($_def['template'] as $support => $gabarit) {
                $cmd->setTemplate($support, $gabarit);
            }
        }
        if (isset($_def['display'])) {
            foreach ($_def['display'] as $clef => $valeur) {
                $cmd->setDisplay($clef, $valeur);
            }
        }
        if (isset($_def['configuration'])) {
            foreach ($_def['configuration'] as $clef => $valeur) {
                $cmd->setConfiguration($clef, $valeur);
            }
        }
        $cmd->save();
        return $cmd;
    }

    /*     * *********************Méthodes d'instance************************* */

    public function preInsert() {
        
    }

    public function postInsert() {
        
    }

    public function preSave() {
        
    }

    //public function parseCommaString($stringToParse){
        //$resultArray = explode(',', $stringToParse);
        
    //}

    public function postSave() {

        /* Un nom n'est pose que s'il est vide. postSave() le reposait auparavant a
         * chaque enregistrement pour les commandes historiques : renommer « Ouvrir la
         * porte 2 » en « Ouverture complete » tenait jusqu'au prochain enregistrement
         * de l'equipement, puis disparaissait sans un mot. Les commandes ajoutees plus
         * tard etaient deja protegees ; la regle vaut maintenant pour toutes. */

        // On vérifie que la clef secrète à bien été créée sinon, on la génère.
        $KEY = $this->getConfiguration('secretkey');
        if($KEY == ''){
            log::add('gds3710', 'debug', 'No secretkey has been detected... creating a new one.');
            $this->setConfiguration('secretkey',md5(microtime().rand()));
        }

        // On utilise la MAC pour créer le logical ID de l'équipement
        $MAC = $this->getConfiguration('macaddress');
        $this->setLogicalId(strtolower($MAC));
        $this->save(true);
       
        /* Le type est obligatoire dans chaque getCmd() ci-dessous. La colonne logicalId est
         * en collation utf8mb3_unicode_ci, donc insensible a la casse : la commande action
         * 'reboot' et la commande info 'Reboot' (evenement 1102 du catalogue) sont
         * indistinguables pour MySQL. Sans filtre de type, getCmd() renvoyait l'une ou
         * l'autre selon l'ordre des lignes, et sur une reinstallation le code tentait de
         * renommer la commande info en « Reboot », nom deja porte par la commande action.
         * Jeedom refusait, postSave() avortait, et l'equipement devenait insauvegardable.
         * C'est le bug « Une commande portant ce nom (Reboot) existe deja ». */

        /* Toutes les commandes fixes de l'equipement, decrites dans get_command_list().
         * Elles occupaient ici 23 blocs recopies, soit plus de 400 lignes. */
        foreach (gds3710::get_command_list() as $lid => $def) {
            $this->poserCommande($lid, $def);
        }

        /* Ces deux valeurs ne viennent pas du portier : le widget les lit telles quelles.
         * Elles sont posees apres la creation, une commande devant exister pour recevoir
         * un evenement. */
        $stream = $this->getCmd('info', 'stream_mjpeg');
        if (is_object($stream)) {
            $stream->event('/plugins/gds3710/core/php/camera.php?id=' . $this->getId());
        }
        /* Le client SIP ne porte que l'id de l'equipement : le widget s'en sert pour aller
         * chercher sa configuration par un appel ajax authentifie, le mot de passe du
         * compte SIP n'ayant rien a faire dans la valeur d'une commande. */
        $sip = $this->getCmd('info', 'sip_client');
        if (is_object($sip)) {
            $sip->event((string) $this->getId());
        }

        // Création des autres commandes du portier
        $cmd_array = gds3710::get_GDS3710_event_list();
        foreach ($cmd_array as $row){
            $info = $this->getCmd('info', $row['short_name']);
            $isNew = !is_object($info);
            if ($isNew) {
                $info = new gds3710Cmd();
            }
            /* Le nom historique etait le simple numero de type (« 1102 »), illisible dans
             * une liste de 35 commandes. On le remplace par « 1102 - Reboot ». Le prefixe
             * numerique garantit lunicite : le libelle seul entrerait en collision avec la
             * commande action « Reboot ». Un nom deja personnalise par lutilisateur nest
             * pas ecrase. */
            if ($isNew || $info->getName() === (string) $row['type']) {
                $info->setName($row['type'] . ' - ' . $row['message']);
            }
            $info->setType('info');
            $info->setSubType('string');
            $info->setIsVisible(0);
            $info->setLogicalId($row['short_name']);
            $info->setEqLogic_id($this->getId());
            $info->save(); 
        }

        // Réglages de confort : une commande info + un curseur qui l'écrit
        foreach (gds3710::get_setting_list() as $lid => $def) {
            $info = $this->getCmd('info', $lid);
            if (!is_object($info)) {
                $info = new gds3710Cmd();
                $info->setIsVisible(0);
            }
            if (trim((string) $info->getName()) === '') {
                $info->setName($def['name']);
            }
            $info->setType('info');
            $info->setSubType('numeric');
            $info->setLogicalId($lid);
            $info->setEqLogic_id($this->getId());
            /* Plage d affichage seulement : le bornage reel des ecritures vers le
             * portier s appuie sur get_setting_list(), pas sur ces valeurs. */
            if ((string) $info->getConfiguration('minValue', '') === '') {
                $info->setConfiguration('minValue', $def['min']);
            }
            if ((string) $info->getConfiguration('maxValue', '') === '') {
                $info->setConfiguration('maxValue', $def['max']);
            }
            $info->save();

            $slider = $this->getCmd('action', $lid . '_set');
            if (!is_object($slider)) {
                $slider = new gds3710Cmd();
                $slider->setIsVisible(0);
            }
            if (trim((string) $slider->getName()) === '') {
                $slider->setName($def['name'] . ' ' . __('(réglage)', __FILE__));
            }
            $slider->setType('action');
            $slider->setSubType('slider');
            $slider->setLogicalId($lid . '_set');
            $slider->setEqLogic_id($this->getId());
            if ((string) $slider->getConfiguration('minValue', '') === '') {
                $slider->setConfiguration('minValue', $def['min']);
            }
            if ((string) $slider->getConfiguration('maxValue', '') === '') {
                $slider->setConfiguration('maxValue', $def['max']);
            }
            $slider->setValue($info->getId());   // le curseur affiche l'état réel
            $slider->save();
        }

        /* Le planning du retroeclairage et ses horaires sont decrits dans
         * get_command_list(). Les deux boutons qui les pilotent restent ici : ils
         * designent l'etat qu'ils modifient, dont l'identifiant n'existe qu'une fois
         * la commande enregistree. */
        $backlight = $this->getCmd('info', 'backlight_schedule');
        foreach (array('backlight_on' => __('Rétroéclairage - activer le planning', __FILE__),
                       'backlight_off' => __('Rétroéclairage - désactiver le planning', __FILE__)) as $blid => $label) {
            if (!is_object($backlight)) {
                break;
            }
            $cmd = $this->getCmd('action', $blid);
            if (!is_object($cmd)) {
                $cmd = new gds3710Cmd();
                $cmd->setIsVisible(0);
            }
            /* Le nom n'est pose que s'il est vide. Ces deux commandes avaient echappe a la
             * correction generale du renommage : leur nom d'origine revenait a chaque
             * enregistrement de l'equipement. */
            if (trim((string) $cmd->getName()) === '') {
                $cmd->setName($label);
            }
            $cmd->setType('action');
            $cmd->setSubType('other');
            $cmd->setLogicalId($blid);
            $cmd->setEqLogic_id($this->getId());
            $cmd->setValue($backlight->getId());
            $cmd->save();
        }

        /* Maintien de porte et detection de mouvement : une paire marche/arret chacun,
         * sur le modele du retroeclairage. Les commandes de maintien sont invisibles par
         * defaut — elles deverrouillent une porte et l y laissent, ce n est pas quelque
         * chose qui doit atterrir sur un dashboard par inadvertance. */
        foreach (array(
            'keep_open_1_on'  => array(__('Maintien porte 1 - activer', __FILE__),   'keep_open_1'),
            'keep_open_1_off' => array(__('Maintien porte 1 - désactiver', __FILE__), 'keep_open_1'),
            'keep_open_2_on'  => array(__('Maintien porte 2 - activer', __FILE__),   'keep_open_2'),
            'keep_open_2_off' => array(__('Maintien porte 2 - désactiver', __FILE__), 'keep_open_2'),
            'motion_on'       => array(__('Détection de mouvement - activer', __FILE__),   'motion_detection'),
            'motion_off'      => array(__('Détection de mouvement - désactiver', __FILE__), 'motion_detection'),
        ) as $lid => $def) {
            $cmd = $this->getCmd('action', $lid);
            if (!is_object($cmd)) {
                $cmd = new gds3710Cmd();
                $cmd->setIsVisible(0);
            }
            if (trim((string) $cmd->getName()) === '') {
                $cmd->setName($def[0]);
            }
            $cmd->setType('action');
            $cmd->setSubType('other');
            $cmd->setLogicalId($lid);
            $cmd->setEqLogic_id($this->getId());
            $cmd->save();
        }

        // Commandes issues de la décomposition des évènements
        foreach (gds3710::get_event_detail_list() as $lid => $def) {
            $cmd = $this->getCmd('info', $lid);
            if (!is_object($cmd)) {
                $cmd = new gds3710Cmd();
                $cmd->setIsVisible(0);
            }
            /* Meme regle : les neuf commandes de decomposition d'evenement reimposaient
             * elles aussi leur nom a chaque enregistrement. */
            if (trim((string) $cmd->getName()) === '') {
                $cmd->setName($def['name']);
            }
            $cmd->setType('info');
            $cmd->setSubType('string');
            $cmd->setLogicalId($lid);
            $cmd->setEqLogic_id($this->getId());
            $cmd->save();
        }

        /* Capteurs releves par cmd=get&type=sysinfo. Le plugin nappelait jamais cette
         * requete alors quelle expose gratuitement les entrees/sorties digitales, letat
         * des relais, deux temperatures, luptime et la version de firmware. */
        foreach (gds3710::get_state_list() as $lid => $def) {
            $cmd = $this->getCmd('info', $lid);
            if (!is_object($cmd)) {
                $cmd = new gds3710Cmd();
                $cmd->setIsVisible(0);
            }
            if (trim((string) $cmd->getName()) === '') {
                $cmd->setName($def['name']);
            }
            $cmd->setType('info');
            $cmd->setSubType($def['subType']);
            $cmd->setLogicalId($lid);
            $cmd->setEqLogic_id($this->getId());
            $cmd->save();
        }

        foreach (gds3710::get_sensor_list() as $lid => $def) {
            $cmd = $this->getCmd('info', $lid);
            if (!is_object($cmd)) {
                $cmd = new gds3710Cmd();
                $cmd->setIsVisible(0);
            }
            /* Nom, unite et historisation sont des valeurs par defaut, pas des
             * verites imposees : les reecrire a chaque enregistrement effacerait le
             * choix fait dans le tableau des commandes. On ne remplit que ce qui est
             * vide, ce qui rattrape aussi les installations anterieures aux unites. */
            if (trim((string) $cmd->getName()) === '') {
                $cmd->setName($def['name']);
            }
            $cmd->setType('info');
            $cmd->setSubType($def['subType']);
            $cmd->setLogicalId($lid);
            $cmd->setEqLogic_id($this->getId());
            if (isset($def['unite']) && trim((string) $cmd->getUnite()) === '') {
                $cmd->setUnite($def['unite']);
            }
            if (isset($def['historized']) && $cmd->getId() == '') {
                $cmd->setIsHistorized($def['historized']);
            }
            $cmd->save();
        }

        $this->lierCommandesAuxEtats();

        return;
    }

    /* Chaque bouton designe l etat qu il modifie. Jeedom affiche alors la valeur
     * courante sur la commande, et presente une paire marche/arret comme un
     * interrupteur plutot que comme deux boutons sans memoire.
     *
     * On ne pose la liaison que si elle est absente : un utilisateur qui aurait relie
     * une commande autrement garde son choix. */
    private function lierCommandesAuxEtats() {
        /* Chaque bouton designe l etat qu il modifie, et le widget souhaite lorsque cet
         * etat est binaire. « binarySwitch » presente un interrupteur, « binaryDefault »
         * un bouton qui montre sa position. */
        $liaisons = array(
            'ldc_ON'             => array('etat' => 'ldc_state',          'widget' => 'binarySwitch'),
            'ldc_off'            => array('etat' => 'ldc_state',          'widget' => 'binarySwitch'),
            'cmos_normal'        => array('etat' => 'cmos_mode'),
            'cmos_lowlight'      => array('etat' => 'cmos_mode'),
            'cmos_wdr'           => array('etat' => 'cmos_mode'),
            'open'               => array('etat' => 'doorctrl0'),
            'close'              => array('etat' => 'doorctrl0'),
            'open2'              => array('etat' => 'doorctrl1'),
            'close2'             => array('etat' => 'doorctrl1'),
            'backlight_on'       => array('etat' => 'backlight_schedule', 'widget' => 'binaryDefault'),
            'backlight_off'      => array('etat' => 'backlight_schedule', 'widget' => 'binaryDefault'),
            'backlight_hours_set'=> array('etat' => 'backlight_hours'),
            'keep_open_1_on'     => array('etat' => 'keep_open_1'),
            'keep_open_1_off'    => array('etat' => 'keep_open_1'),
            'keep_open_2_on'     => array('etat' => 'keep_open_2'),
            'keep_open_2_off'    => array('etat' => 'keep_open_2'),
            'motion_on'          => array('etat' => 'motion_detection', 'widget' => 'binarySwitch'),
            'motion_off'         => array('etat' => 'motion_detection', 'widget' => 'binarySwitch'),
        );
        $posees = 0;
        foreach ($liaisons as $action => $def) {
            $cmd = $this->getCmd('action', $action);
            $info = $this->getCmd('info', $def['etat']);
            if (!is_object($cmd) || !is_object($info)) {
                continue;
            }
            if (trim((string) $cmd->getValue()) !== '') {
                continue;
            }
            /* Un etat que l appareil ne renseigne pas ne vaut pas d etre affiche sur
             * un bouton : le portier repond « (null) » pour le relais de porte quand
             * l ouverture passe par un webrelais et non par son relais local. La
             * liaison se posera d elle-meme au prochain enregistrement si la valeur
             * apparait un jour. */
            $etatCourant = trim((string) $info->execCmd());
            if ($etatCourant === '' || $etatCourant === '(null)') {
                continue;
            }
            $cmd->setValue($info->getId());
            $this->poserWidgetBinaire($cmd, $info, $def);
            $cmd->save();
            $posees++;
        }

        /* Les liaisons deja en place doivent beneficier du widget elles aussi : on
         * repasse dessus sans toucher a la liaison. */
        foreach ($liaisons as $action => $def) {
            $cmd = $this->getCmd('action', $action);
            $info = $this->getCmd('info', $def['etat']);
            if (is_object($cmd) && is_object($info)
                && trim((string) $cmd->getValue()) === (string) $info->getId()) {
                if ($this->poserWidgetBinaire($cmd, $info, $def)) {
                    $cmd->save();
                }
            }
        }
        if ($posees > 0) {
            log::add('gds3710', 'info', $posees . ' commande(s) reliee(s) a leur etat.');
        }
        return $posees;
    }

    /* Un bouton dont l etat lie est binaire s affiche en bouton binaire : il montre
     * alors la position courante au lieu d etre un simple declencheur. Le reglage
     * n est pose que sur un widget encore par defaut, pour ne pas defaire un choix
     * de l utilisateur. */
    private function poserWidgetBinaire($_cmd, $_info, $_def) {
        if ($_info->getSubType() !== 'binary' || !isset($_def['widget'])) {
            return false;
        }
        $vise = 'core::' . $_def['widget'];
        /* setTemplate() prefixe par « core:: » : la valeur stockee pour un widget non
         * personnalise est « core::default », pas « default ».
         *
         * Les deux widgets binaires figurent parmi les valeurs remplacables : ils ne
         * peuvent venir que d une version anterieure de ce meme code, et un choix
         * personnel se reconnait a ce qu il en sort. */
        $remplacables = array('', 'default', 'core::default', 'core::binaryDefault', 'core::binarySwitch');
        $pose = false;
        foreach (array('dashboard', 'mobile') as $support) {
            $actuel = trim((string) $_cmd->getTemplate($support, ''));
            if ($actuel !== $vise && in_array($actuel, $remplacables, true)) {
                $_cmd->setTemplate($support, $_def['widget']);
                $pose = true;
            }
        }
        return $pose;
    }

    /* Releve periodique des capteurs et reparation de lURL du flux.
     *
     * cron15 et non cron : chaque authentification invalide la session administrateur
     * en cours sur le portier, y compris celle dun humain devant son interface web.
     * Les valeurs concernees (temperatures, uptime, firmware) evoluent lentement ;
     * les entrees digitales rapides passent de toute facon par les evenements. */
    public static function cron15() {
        foreach (eqLogic::byType('gds3710', true) as $eq) {

            /* LURL du flux se perd des quun vidage de cache remet les valeurs a blanc.
             * On la republie sans dependre du portier. */
            $stream = $eq->getCmd('info', 'stream_mjpeg');
            if (is_object($stream) && (string) $stream->execCmd() === '') {
                $stream->event('/plugins/gds3710/core/php/camera.php?id=' . $eq->getId());
                log::add('gds3710', 'info', 'URL du flux MJPEG republiee pour ' . $eq->getHumanName() . '.');
            }

            if (config::byKey('poll_sensors', 'gds3710', 1) != 1) {
                continue;
            }

            /* Une seule passe de lecture pour les reglages ET les etats. Les deux tables
             * partagent trois sections (« audio », « event », « sch_open_door ») : les
             * laisser lire chacune de leur cote envoyait 11 requetes la ou 8 suffisent,
             * sur un appareil qui ne tolere qu une session administrateur a la fois. */
            $lu = $eq->lireSections(array_unique(array_merge(
                gds3710::sectionsDe(gds3710::get_setting_list()),
                gds3710::sectionsDe(gds3710::get_state_list())
            )));
            $eq->refreshSettings($lu);
            $eq->refreshStates($lu);

            $info = $eq->readConfigSection('sysinfo');
            if ($info === null) {
                continue;
            }

            /* Un reset du portier, ou un changement dadresse de Jeedom, rendait la
             * remontee d evenements muette sans aucun signe. On le detecte au lieu de
             * laisser lutilisateur le decouvrir des mois plus tard. */
            $drift = $eq->checkEventNotificationConfig();
            if (is_array($drift) && count($drift) > 0) {
                $keys = implode(', ', array_keys($drift));
                log::add('gds3710', 'warning', 'La configuration de notification du portier ' . $eq->getHumanName()
                    . ' ne correspond plus a ce Jeedom (' . $keys . '). Utilisez la commande « Configurer le portier ».');
                if (cache::byKey('gds3710::drift::' . $eq->getId())->getValue(0) != 1) {
                    cache::set('gds3710::drift::' . $eq->getId(), 1, 86400);
                    message::add('gds3710', __('Le portier ', __FILE__) . $eq->getHumanName()
                        . __(' ne publie plus ses evenements vers ce Jeedom. Lancez la commande « Configurer le portier » de l equipement.', __FILE__));
                }
            }
            foreach (gds3710::get_sensor_list() as $lid => $def) {
                if (!array_key_exists($lid, $info)) {
                    continue;
                }
                $cmd = $eq->getCmd('info', $lid);
                if (!is_object($cmd)) {
                    continue;
                }
                $value = trim($info[$lid]);
                if ($def['subType'] === 'binary') {
                    $value = ($value === '' || $value === '0') ? 0 : 1;
                } elseif ($def['subType'] === 'numeric') {
                    if (!is_numeric($value)) { continue; }
                    $value = (float) $value;
                } elseif ($lid === 'Pfw_available_version') {
                    $value = ($value === '') ? __('à jour', __FILE__) : $value;
                }
                $eq->checkAndUpdateCmd($cmd, $value);
            }
        }
    }

    public function preUpdate() {
        $this->setCategory('security', 1);

        // Seul le champs adresse MAC est obligatoire.
        if ($this->getConfiguration('macaddress') == '') {
            throw new Exception(__('L\'adresse MAC du portier ne peut être vide', __FILE__));
        }
    }

    public function postUpdate() {
        
    }

    public function preRemove() {
        
    }

    public function postRemove() {
        
    }

    // public function toHtml($_version = 'dashboard') {
    //      $replace = $this->preToHtml($_version);
    //      if (!is_array($replace)) {
    //          return $replace;
    //      }
    //      $version = jeedom::versionAlias($_version);
    //      if ($this->getDisplay('hideOn' . $version) == 1) {
    //          return '';
    //      }
    //     /* ------------ Ajouter votre code ici ------------*/

    //     $replace['#MAC#'] = $this->getLogicalId();

    //     foreach ($this->getCmd('info') as $cmd) {

    //         //return $cmd->getLogicalId();
    //         // $replace['#' . $cmd->getLogicalId() . '_history#'] = '';
    //         // $replace['#' . $cmd->getLogicalId() . '_id#'] = $cmd->getId();
    //         // $replace['#' . $cmd->getLogicalId() . '#'] = $cmd->execCmd();
    //         // $replace['#' . $cmd->getLogicalId() . '_collect#'] = $cmd->getCollectDate();
    //         // if ($cmd->getLogicalId() == 'encours'){
    //         //     $replace['#thumbnail#'] = $cmd->getDisplay('icon');
    //         // }
    //         // if ($cmd->getIsHistorized() == 1) {
    //         //     $replace['#' . $cmd->getLogicalId() . '_history#'] = 'history cursor';
    //         // }
    //     }

    //     foreach ($this->getCmd('action') as $cmd) {
    //         //$replace['#' . $cmd->getLogicalId() . '_id#'] = $cmd->getId();
    //     }
    //     /* ------------ N'ajouter plus de code apres ici------------ */

    //      return $this->postToHtml($_version, template_replace($replace, getTemplate('core', $version, 'gds3710', 'gds3710')));
    // }
     

    
     /* Non obligatoire mais ca permet de déclencher une action après modification de variable de configuration
    public static function postConfig_<Variable>() {
    }
     */

    /*
     * Non obligatoire mais ca permet de déclencher une action avant modification de variable de configuration
    public static function preConfig_<Variable>() {
    }
     */

    /*     * **********************Getteur Setteur*************************** */
}

class gds3710Cmd extends cmd {
    /*     * *************************Attributs****************************** */


    /*     * ***********************Methode static*************************** */

    /* Ouvre ou ferme une porte. $_type vaut 1 pour ouvrir, 2 pour fermer ; $_porte
     * designe laquelle, et donc le PIN a presenter.
     *
     * Ce chemin n'utilise PAS la session administrateur : il s'authentifie par le PIN
     * distant sur /goform/apicmd, un mecanisme separe. Il ne perturbe donc pas la
     * session mise en cache.
     *
     * Les deux portes avaient chacune leur methode, identiques a un nom de cle pres. */
    private function open_door($_type, $_porte = 1) {
        $gds3710 = eqLogic::byId($this->getEqLogic_id());
        $cle = ($_porte == 2) ? 'remote_pin_2' : 'remote_pin';
        log::add('gds3710', 'info', 'Porte ' . $_porte . ' : '
            . (($_type == '1') ? 'ouverture' : 'fermeture') . ' demandee.');

        $ip = $gds3710->getConfiguration('ip');
        $remote_pin = $gds3710->getConfiguration($cle);
        $password = $gds3710->getConfiguration('password');

        $defi = gds3710::parseXml(
            self::httpApi('https://' . $ip . '/goform/apicmd?cmd=0&user=admin'), 'ouverture porte');
        if ($defi === null) {
            return;
        }
        $authcode = md5($defi->ChallengeCode[0] . ':' . $remote_pin . ':' . $password);
        $reponse = self::httpApi('https://' . $ip . '/goform/apicmd?cmd=1&user=admin&authcode='
            . $authcode . '&idcode=' . $defi->IDCode[0] . '&type=' . $_type);
        log::add('gds3710', 'debug', 'result : ' . gds3710::redact($reponse));
    }

    /* Requete simple vers l'API du portier, sans session. */
    private static function httpApi($_url) {
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $_url,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ));
        $resultat = curl_exec($ch);
        curl_close($ch);
        return $resultat;
    }

    /* Sections ou chercher un P-value pour verifier une ecriture. Le portier accepte
     * n'importe quel parametre avec un ResCode 0, y compris un parametre qu'il ne
     * connait pas : c'est ainsi que les commandes LDC ont fait semblant de fonctionner
     * pendant des annees apres le retrait du reglage par Grandstream. */
    private static $configSections = array('video', 'cmos', 'audio', 'osd', 'date', 'door',
                                           'play', 'log', 'access', 'net', 'sip', 'sysinfo');


    private function setConfig($id, $parameter_value, $_section = ''){

        if( $id == '' || $parameter_value == ''){
            log::add('gds3710', 'error', 'Parameter error. Aborting.');
            return;
        }

        $gds3710 = eqLogic::byId($this->getEqLogic_id());
        /* Une seule voie d'authentification pour toute la configuration : openSession(),
         * qui met sa session en cache. getAuthCookies() en ouvrait une neuve a chaque
         * appel — or le portier n'en tolere qu'une : la session du cron s'en trouvait
         * invalidee, et la lecture suivante echouait sans un mot. */
        $cookie_string = $gds3710->openSession();
        if ($cookie_string === null) {
            return;
        }

        $ip = $gds3710->getConfiguration('ip');

        /* Ecriture en POST, et non plus dans la chaine de requete. Le portier decode
         * l'encodage pourcent PUIS re-decoupe sa propre chaine de requete sur les « & » :
         * une valeur qui en contient — un gabarit d'URL, par exemple — etait tronquee au
         * premier, en silence et avec un ResCode 0. La valeur n'etait meme pas encodee ici,
         * donc une simple espace suffisait aussi a la couper. C'est le chemin de la commande
         * « Modifier la configuration », de LDC et des modes CMOS. */
        log::add('gds3710', 'debug', 'Ecriture de ' . $id . ' en POST sur ' . $ip);
        $result = gds3710::parseXml(gds3710::httpPostConfig($ip, $cookie_string, array($id => $parameter_value)),
                                    'requete configuration');
        log::add('gds3710', 'debug', 'Result is : '. print_r($result, true));

        /* Relecture : un ResCode 0 ne prouve rien, le portier repond OK meme pour un
         * parametre inexistant. On verifie que la valeur a bien ete prise. */
        $sections = $_section !== '' ? array($_section) : self::$configSections;
        $trouve = false;
        foreach ($sections as $section) {
            $lu = $gds3710->readConfigSection($section, $cookie_string);
            if (!is_array($lu) || !array_key_exists($id, $lu)) {
                continue;
            }
            $trouve = true;
            /* Le portier rend les caracteres speciaux sous forme d'entites : « &amp; » pour
             * un « & ». Sans decodage, une valeur desormais ecrite correctement serait
             * signalee comme non confirmee. */
            $relu = html_entity_decode((string) $lu[$id], ENT_QUOTES, 'UTF-8');
            if ($relu !== (string) $parameter_value) {
                log::add('gds3710', 'error', 'Ecriture non confirmee pour ' . $id . ' : lu "'
                    . $relu . '", attendu "' . $parameter_value . '".');
            }
            break;
        }
        if (!$trouve) {
            log::add('gds3710', 'error', 'Le parametre ' . $id . ' est inconnu de ce portier : '
                . 'ecriture acceptee mais sans effet. Il a peut-etre ete retire par une mise a '
                . 'jour du firmware.');
        }
    }
   
    /* Le reglage n est pas relisible et ne s applique qu au demarrage du portier :
     * le bouton ne produit donc aucun effet immediat, ce qui est normal. */
    private function ldc_ON(){
        log::add('gds3710', 'info', 'Activation du LDC. Le changement sera visible apres un redemarrage du portier.');
        $this->setConfig('P10573', '1');
        $this->getEqLogic()->refreshStates();
    }

    private function ldc_OFF(){
        log::add('gds3710', 'info', 'Desactivation du LDC. Le changement sera visible apres un redemarrage du portier.');
        $this->setConfig('P10573', '0');
        $this->getEqLogic()->refreshStates();
    }

    private function reboot(){
        log::add('gds3710', 'info', 'Requesting reboot');

        $gds3710 = eqLogic::byId($this->getEqLogic_id());
        $cookie_string = $gds3710->openSession();
        if ($cookie_string === null) {
            return;
        }

        $ip = $gds3710->getConfiguration('ip');

        $url = 'https://'.$ip.'/goform/config?cmd=reboot';

        $optArray = array(
            CURLOPT_URL => $url,
            CURLOPT_SSL_VERIFYPEER  => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIE => $cookie_string
        );

        log::add('gds3710', 'debug', 'Calling url : '.$url);

        $ch = curl_init();
        curl_setopt_array($ch, $optArray);

        $result = gds3710::parseXml(curl_exec($ch), 'requete configuration');
        log::add('gds3710', 'debug', 'Result is : '. print_r($result, true));

        /* Le portier redemarre : la session en cache ne vaut plus rien, et la garder
         * ferait echouer en silence la premiere lecture qui la reutiliserait. */
        $gds3710->oublierSession();
    }

    private function cmos_normal(){
        log::add('gds3710', 'info', 'Requesting CMOS NORMAL');
        $this->setConfig('P10572', '1', 'video');
    }

    private function cmos_lowlight(){
        log::add('gds3710', 'info', 'Requesting CMOS LOW LIGHT');
        $this->setConfig('P10572', '2', 'video');
    }

    private function cmos_wdr(){
        log::add('gds3710', 'info', 'Requesting CMOS WDR');
        $this->setConfig('P10572', '3', 'video');
    }


    private function take_snapshot(){
        log::add('gds3710', 'debug', 'Snapshot has been requested');

        $gds3710 = eqLogic::byId($this->getEqLogic_id());
        $ip = $gds3710->getConfiguration('ip');

        /* Authentification media : voir gds3710::sessionMedia(). Elle occupait ici une
         * soixantaine de lignes, options cURL recopiees trois fois comprises. */
        $cookies_string = $gds3710->sessionMedia();
        if ($cookies_string === null) {
            return null;
        }

        $url ='https://'.$ip.'/snapshot/view0.jpg';
        
        $now = new DateTime();
        $filename=$gds3710->getName()."_".$now->format("Y-m-d_H-i-s").'-'.mt_rand(100000, 999999);

        $dir = calculPath(config::byKey('recdir', 'gds3710')) . '/' . $gds3710->getId();

        /* Les echecs decriture etaient silencieux : mkdir() et fopen() netaient pas
         * verifies, la capture ne se creait pas et rien ne lexpliquait. Le mode 0777
         * est aussi remplace par 0775, suffisant pour lutilisateur du serveur web. */
        if (!file_exists($dir)) {
            log::add('gds3710', 'debug', "Directory doesn't exist, creating : ".$dir);
            if (!@mkdir($dir, 0775, true) && !is_dir($dir)) {
                log::add('gds3710', 'error', 'Impossible de creer le repertoire des captures : ' . $dir
                    . '. Verifiez le chemin configure et les droits du parent.');
                return null;
            }
        }

        if (!is_writable($dir)) {
            log::add('gds3710', 'error', 'Repertoire des captures non accessible en ecriture : ' . $dir
                . '. Il doit appartenir a lutilisateur du serveur web.');
            return null;
        }

        $output_file = $dir.'/'.$filename.'.jpg';
        $fp = @fopen($output_file, 'x');
        if ($fp === false) {
            log::add('gds3710', 'error', 'Impossible de creer le fichier de capture : ' . $output_file);
            return null;
        }
        log::add('gds3710', 'debug', 'Trying to create the capture under : '.$output_file);

        /* CURLOPT_RETURNTRANSFER figurait DEUX fois dans ce tableau, et CURLOPT_BINARYTRANSFER
         * n'a plus aucun effet depuis PHP 5.1.3. Le corps part dans $fp via CURLOPT_FILE. */
        $optArray = gds3710::optionsHttp(array(
            CURLOPT_URL => $url,
            CURLOPT_COOKIE => $cookies_string,
            CURLOPT_HEADER => false,
            CURLOPT_FILE => $fp,
        ));

        log::add('gds3710', 'debug', 'URL array : '.gds3710::redact($optArray));

        $ch = curl_init();
        curl_setopt_array($ch, $optArray);

        $data = curl_exec($ch);
        log::add('gds3710', 'debug', 'URL return : '.gds3710::redact($data));

        curl_close ($ch);
        fclose($fp);
        log::add('gds3710', 'debug', 'Closing the file');

        /* Le portier repond 200 meme quand l authentification a echoue : le corps est
         * alors son XML d erreur, qui etait enregistre tel quel en .jpg, publie comme
         * « dernier snapshot » et envoye par send_snapshot() le cas echeant. Une image
         * JPEG commence par les octets FF D8 : tout autre contenu est une capture ratee. */
        $entete = (string) @file_get_contents($output_file, false, null, 0, 2);
        if ($entete !== "\xFF\xD8") {
            log::add('gds3710', 'error', 'La capture recue n est pas une image JPEG '
                . '(authentification refusee ou portier en erreur) : fichier supprime.');
            @unlink($output_file);
            return null;
        }

        log::add('gds3710', 'debug', "Registering path to lastest picture");
        $eqLogic = $this->getEqLogic();
        $lastest_snapshot = $eqLogic->getCmd('info', 'Lastest_Snapshot_Path');
        $lastest_snapshot->event(realpath($output_file));
        $lastest_snapshot->save();

        log::add('gds3710', 'debug', "Registering URL to the lastest snapshot");
        $eqLogic = $this->getEqLogic();
        $lastest_snapshot_URL = $eqLogic->getCmd('info', 'Lastest_Snapshot_URL');
        $lastest_snapshot_URL->event(gds3710::urlPublique($output_file));
        $lastest_snapshot_URL->save();

        return $output_file;
    }

    // Take a number of snapshot and send them to a command.
    private function send_snapshot($nbsnap, $sendto){

        log::add('gds3710', 'debug', 'Starting sending '.$nbsnap.' snapshot(s) to command(s) '.$sendto);

        if ($nbsnap == '' || $nbsnap == 0) {
            log::add('gds3710', 'debug', 'Number of snapshot is zero. Aborting');
            return;
        }

        if ($sendto == '') {
            log::add('gds3710', 'debug', 'No command to send to. Aborting');
            return;
        }

        $files =array();

        for ($i = 1; $i <= $nbsnap; $i++) {
            $shot = $this->take_snapshot();
            if ($shot === null) { // portier injoignable : inutile denvoyer une image vide
                log::add('gds3710', 'error', 'Capture ' . $i . ' sur ' . $nbsnap . ' echouee, envoi interrompu.');
                break;
            }
            array_push($files, $shot);
        }

        if (count($files) === 0) {
            log::add('gds3710', 'error', 'Aucune capture disponible, rien a envoyer.');
            return;
        }

        $options = array();
        $options['files'] = $files;

        $cmds = explode('&&',  $sendto);

        foreach ($cmds as $id) {
            $cmd = cmd::byId(str_replace('#', '', $id));
            if (!is_object($cmd)) {
                log::add('gds3710', 'error', 'Error while sending snapshot, '.$cmd.' is not a cmd');
                continue;
            }
            try {
                $cmd->execCmd($options);
            } catch (Exception $e) {
                log::add('gds3710', 'error', __('[gds3710/send_snapshot] Erreur lors de l\'envoi des images : ', __FILE__) . $cmd->getHumanName() . ' => ' . log::exception($e));
            }
        }

    }

    /*     * *********************Methode d'instance************************* */

    /*
     * Non obligatoire permet de demander de ne pas supprimer les commandes même si elles ne sont pas dans la nouvelle configuration de l'équipement envoyé en JS
      public function dontRemoveCmd() {
      return true;
      }
     */

    public function execute($_options = array()) {

        $eqLogic = $this->getEqLogic();
        $lid = $this->getLogicalId();

        /* Curseurs de reglage : un seul traitement pour tous, plutot quun cas par valeur. */
        $settings = gds3710::get_setting_list();
        if (substr($lid, -4) === '_set' && isset($settings[substr($lid, 0, -4)])) {
            $def = $settings[substr($lid, 0, -4)];
            $value = isset($_options['slider']) ? (int) $_options['slider'] : null;
            if ($value === null || $value < $def['min'] || $value > $def['max']) {
                log::add('gds3710', 'error', 'Valeur hors bornes pour ' . $def['name']
                    . ' : ' . var_export($value, true) . ' (attendu ' . $def['min'] . ' a ' . $def['max'] . ').');
                return;
            }
            if ($eqLogic->writeConfig(array($def['p'] => $value), $def['section'])) {
                $eqLogic->refreshSettings();
            }
            return;
        }

        switch ($lid) {
            case 'open':
                $this->open_door('1');
                break;
            case 'close':
                $this->open_door('2');
                break;
            case 'open2':
            case 'close2':
                /* En mode webrelay (P15440=1) le portier n'a qu'UNE URL de relais : toute
                 * ouverture, quelle que soit la porte et quel que soit le PIN presente,
                 * declenche la meme action. La porte 2 de l'appareil est alors sans effet
                 * propre — deux boutons pour un seul geste.
                 *
                 * Une commande Jeedom peut donc lui etre associee : le bouton « porte 2 »
                 * execute alors cette commande au lieu d'interroger le portier. C'est ce
                 * qui permet, par exemple, d'ouvrir en grand un portail dont le portier ne
                 * commande que l'ouverture pietonne — et le bouton reste disponible dans
                 * la fenetre d'appel du client SIP, qui liste les commandes d'ouverture
                 * visibles de l'equipement. */
                $deleguee = trim((string) $eqLogic->getConfiguration('door2_cmd'));
                if ($deleguee !== '') {
                    if ($lid === 'close2') {
                        log::add('gds3710', 'info', 'Porte 2 deleguee a une commande Jeedom : '
                            . '« Fermer la porte 2 » est sans objet et n a rien execute.');
                        break;
                    }
                    /* Le selecteur rend la forme balisee « #[Objet][Eq][Cmd]# », la seule
                     * que cmd::byString() sache resoudre — le nom humain nu, lui, echoue.
                     * Une valeur saisie a la main sans les diese est rattrapee ici plutot
                     * que de faire echouer l'ouverture sur un detail de syntaxe. */
                    if (substr($deleguee, 0, 1) === '[') {
                        $deleguee = '#' . $deleguee . '#';
                    }
                    log::add('gds3710', 'info', 'Porte 2 : execution de la commande Jeedom ' . $deleguee . '.');
                    try {
                        scenarioExpression::createAndExec('action', $deleguee);
                    } catch (Exception $e) {
                        log::add('gds3710', 'error', 'La commande Jeedom associee a la porte 2 a echoue ('
                            . $deleguee . ') : ' . $e->getMessage());
                    }
                    break;
                }
                $this->open_door($lid === 'open2' ? '1' : '2', 2);
                break;
            case 'cmos_normal':
                $this->cmos_normal();
                break;
            case 'cmos_lowlight':
                $this->cmos_lowlight();
                break;
            case 'cmos_wdr':
                $this->cmos_wdr();
                break;
            case 'snapshot':
                $this->take_snapshot();
                break;
            case 'ldc_ON':
                $this->ldc_ON();
                break;
            case 'ldc_off':
                $this->ldc_OFF();
                break;
            case 'reboot':
                $this->reboot();
                break;
            case 'configureDoorbell':
                $eqLogic->pushEventNotificationConfig();
                break;
            /* Le portier refuse silencieusement certains parametres — les reglages de
             * date en sont la preuve. writeConfig() relit systematiquement apres
             * ecriture et journalise « Ecriture non confirmee » si l appareil n a pas
             * pris la valeur : un echec ne peut donc pas passer inapercu. */
            case 'keep_open_1_on':
            case 'keep_open_1_off':
            case 'keep_open_2_on':
            case 'keep_open_2_off':
                $porte2 = (strpos($lid, 'keep_open_2') === 0);
                $actif = (substr($lid, -3) === '_on') ? '1' : '0';
                if ($actif === '1') {
                    log::add('gds3710', 'warning', 'Maintien de porte ' . ($porte2 ? '2' : '1')
                        . ' active : la porte reste deverrouillee pendant la duree configuree.');
                }
                if ($eqLogic->writeConfig(array(($porte2 ? 'P15455' : 'P15429') => $actif), 'sch_open_door')) {
                    $eqLogic->refreshStates();
                }
                break;
            case 'motion_on':
            case 'motion_off':
                $actif = ($lid === 'motion_on') ? '1' : '0';
                if ($eqLogic->writeConfig(array('P10250' => $actif), 'event')) {
                    $eqLogic->refreshStates();
                }
                break;
            case 'backlight_on':
            case 'backlight_off':
                $on = ($this->getLogicalId() === 'backlight_on') ? '1' : '0';
                if ($eqLogic->writeConfig(array('P15594' => $on), 'door')) {
                    $eqLogic->refreshSettings();
                }
                break;
            case 'backlight_hours_set':
                $start = isset($_options['title']) ? preg_replace('/[^0-9]/', '', $_options['title']) : '';
                $end = isset($_options['message']) ? preg_replace('/[^0-9]/', '', $_options['message']) : '';
                if (strlen($start) !== 6 || strlen($end) !== 6) {
                    log::add('gds3710', 'error', 'Horaires attendus au format HHMMSS. Recu : ' . $start . ' et ' . $end . '.');
                    break;
                }
                if ($eqLogic->writeConfig(array('P15595' => $start, 'P15596' => $end), 'door')) {
                    $eqLogic->refreshSettings();
                }
                break;
            case 'sendSnapshot':
                if (!isset($_options['title'])) {
                    $_options['title'] = '';
                }
                if (!isset($_options['message'])) {
                    $_options['message'] = '';
                }
                $this->send_snapshot($_options['title'], $_options['message']);
                break;
            case 'modifyConfig':
                if (isset($_options['title']) && isset($_options['message'])) {
                    log::add('gds3710', 'debug', 'Trying to set config variable '.$_options['title'].' with value '.$_options['message']);
                    $this->setConfig($_options['title'], $_options['message']);
                }
                break;
        }
    }

    /*     * **********************Getteur Setteur*************************** */
}
