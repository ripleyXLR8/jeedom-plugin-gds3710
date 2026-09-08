<?php

/* Doublures du coeur de Jeedom, chargees a la place du vrai core.inc.php.
 *
 * gds3710.class.php commence par un require_once vers ../../../../core/php/core.inc.php :
 * le chemin est relatif au fichier de classe, donc la seule facon de charger la classe
 * hors d'une installation Jeedom est de reconstituer cette arborescence. bootstrap.php
 * la fabrique dans un repertoire temporaire et y depose ce fichier.
 *
 * On ne reproduit ici que ce dont les fonctions testees ont besoin. eqLogic et cmd sont
 * indispensables des le chargement (la classe en herite) ; log, config, cache et __()
 * sont appelees a l'execution. Tout le reste est volontairement absent : une methode qui
 * toucherait au reseau ou a la base n'est pas testable ainsi, et son absence de doublure
 * le fait echouer bruyamment plutot que silencieusement. */

class log {
    /* Les tests inspectent ce journal : c'est ainsi qu'on verifie qu'un chemin d'erreur
     * previent l'administrateur au lieu d'echouer sans un mot. */
    public static $lignes = array();

    public static function add($_plugin, $_niveau, $_message, $_code = null) {
        self::$lignes[] = array('plugin' => $_plugin, 'niveau' => $_niveau, 'message' => $_message);
    }

    public static function vider() {
        self::$lignes = array();
    }

    /* Renvoie les messages d'un niveau donne, ou tous si le niveau est vide. */
    public static function messages($_niveau = '') {
        $out = array();
        foreach (self::$lignes as $l) {
            if ($_niveau === '' || $l['niveau'] === $_niveau) {
                $out[] = $l['message'];
            }
        }
        return $out;
    }

    public static function exception($_e) {
        return $_e->getMessage();
    }
}

class config {
    public static $valeurs = array();

    public static function byKey($_key, $_plugin = 'core', $_default = '') {
        $k = $_plugin . '::' . $_key;
        return array_key_exists($k, self::$valeurs) ? self::$valeurs[$k] : $_default;
    }

    public static function save($_key, $_value, $_plugin = 'core') {
        self::$valeurs[$_plugin . '::' . $_key] = $_value;
    }
}

/* cache::byKey() rend un objet porteur d'un getValue($defaut) : la doublure respecte
 * cette forme, sans quoi les appelants s'ecrouleraient sur un tableau nu. */
class cacheValeur {
    private $valeur;

    public function __construct($_valeur) {
        $this->valeur = $_valeur;
    }

    public function getValue($_default = '') {
        return $this->valeur === null ? $_default : $this->valeur;
    }
}

class cache {
    public static $valeurs = array();

    public static function byKey($_key) {
        return new cacheValeur(array_key_exists($_key, self::$valeurs) ? self::$valeurs[$_key] : null);
    }

    public static function set($_key, $_value, $_lifetime = 0) {
        self::$valeurs[$_key] = $_value;
    }
}

class message {
    public static $messages = array();

    public static function add($_plugin, $_message, $_action = null, $_logicalId = null) {
        self::$messages[] = array('plugin' => $_plugin, 'message' => $_message);
    }
}

class eqLogic {
    protected $id;
    protected $name = '';
    protected $configuration = array();

    public function getId() { return $this->id; }
    public function getName() { return $this->name; }
    public function getHumanName() { return '[' . $this->name . ']'; }

    public function getConfiguration($_key, $_default = '') {
        return array_key_exists($_key, $this->configuration) ? $this->configuration[$_key] : $_default;
    }

    public function setConfiguration($_key, $_value) {
        $this->configuration[$_key] = $_value;
        return $this;
    }

    public static function byType($_type, $_onlyEnable = false) { return array(); }
    public static function byId($_id) { return null; }
    public function getCmd($_type = null, $_logicalId = null) { return null; }
    public function save($_direct = false) { return $this; }
}

class cmd {
    protected $id;
    protected $name = '';
    protected $logicalId = '';
    protected $type = '';
    protected $subType = '';
    protected $value = '';

    public function getId() { return $this->id; }
    public function getName() { return $this->name; }
    public function setName($_name) { $this->name = $_name; return $this; }
    public function getLogicalId() { return $this->logicalId; }
    public function setLogicalId($_id) { $this->logicalId = $_id; return $this; }
    public function getSubType() { return $this->subType; }
    public function getValue() { return $this->value; }
    public function save() { return $this; }
}

/* Traduction : les tests portent sur le francais, la fonction rend donc la chaine
 * telle quelle — exactement ce que fait Jeedom quand aucun catalogue ne correspond. */
if (!function_exists('__')) {
    function __($_texte, $_fichier = '') {
        return $_texte;
    }
}

if (!function_exists('calculPath')) {
    function calculPath($_chemin) {
        return $_chemin;
    }
}

if (!function_exists('init')) {
    function init($_name, $_default = '') {
        return isset($_REQUEST[$_name]) ? $_REQUEST[$_name] : $_default;
    }
}
