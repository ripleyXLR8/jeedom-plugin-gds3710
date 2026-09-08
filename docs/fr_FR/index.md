# Plugin GDS 3710
> Version du 5 mars 2019
> by Richard Perez | richard@perez-mail.fr

# Introduction

Ce plugin intègre le portier GrandStream GDS3710 dans Jeedom. Il permet de :

- Recevoir les évènements du portier et les exploiter dans des scénarios ou des commandes.
- Modifier la configuration du portier.
- Actionner les contacts secs qui ouvrent une porte, ou pilotent tout autre équipement.
- Afficher le flux MJPEG du portier dans un widget de dashboard ou de mobile.
- Enregistrer des captures extraites du flux MJPEG.
- Consulter ces captures dans une bibliothèque intégrée.
- Transmettre des captures à une autre commande (testé avec le plugin Telegram).
- Changer le mode du capteur vidéo (normal, faible luminosité, WDR).
- Configurer le portier lui-même par une seule commande, sans aucune saisie manuelle.
- Remonter les capteurs du portier : entrées et sorties digitales, état des relais, anti-arrachement, températures, uptime, version de firmware et mise à jour disponible.
- Purger automatiquement les captures au-delà d'une durée de rétention.
- Exposer chaque évènement décomposé en commandes : code, libellé, date, badge, utilisateur, porte, numéro SIP, dernière personne entrée et dernière alerte sécurité.
- Piloter les réglages du portier : luminosité des LED du clavier, luminosité, contraste et saturation de l'image, délai avant capture, raccrochage après une ouverture distante, volumes, et planning du rétroéclairage blanc.
- Enregistrer un **client SIP** depuis Jeedom et répondre aux appels du portier sur le dashboard, image et son compris.
- Piloter le **maintien de porte ouverte** : mode, durée, et depuis quand une porte est maintenue.
- Armer et désarmer la **détection de mouvement**, et en régler la sensibilité.
- Associer une **commande Jeedom à la porte 2**, indispensable en mode webrelay où le portier n'a qu'une seule URL de relais.

Il s'appuie sur la documentation publiée par GrandStream : http://www.grandstream.com/sites/default/files/Resources/gds37xx_http_api.pdf

# Configuration du portier GrandStream GDS3710
## Pré-requis
Afin de récupérer les évènements générés par le portier, nous utilisons la fonctionnalité "Event Notification" du GDS3710.

Versions de firmware :

- **1.0.11.18 minimum conseillé.** En dessous, le portier envoie ses notifications avec un `Content-Type` que PHP ne sait pas décoder. Le plugin sait désormais rattraper ce cas, mais la mise à jour reste préférable.
- **1.0.13.2 minimum si l'accès web de votre portier est réglé sur HTTPS** : avant cette version, l'API HTTP ne répondait pas dans ce mode.
- Le plugin est testé jusqu'à la version **1.0.13.15** (juillet 2025).

## Configuration de la fonctionnalité "Event Notification"
- Rendez-vous dans l'interface de gestion de votre GDS3710 puis dans Maintenance -> Event Notification.
- Cochez la case "Enable Event Notification".
- Sélectionnez le type de communication avec le serveur "http" ou "https" selon la configuration de votre serveur Jeedom.
- **Fortement recommandé** : saisissez un identifiant et un mot de passe que votre portier devra fournir à Jeedom pour publier un évènement. Ils doivent être reportés à l'identique dans la configuration du plugin.
- Dans champs "HTTP/HTTPS Server", entrez la chaine suivante en remplacant IP_DE_VOTRE_JEEDOM par l'adresse IP de votre serveur Jeedom : `"IP_DE_VOTRE_JEEDOM/plugins/gds3710/core/php/jeeGDS3710.php"`.

> Utilisez bien l'**adresse IP locale** de Jeedom, et non un nom de domaine public. En passant par l'extérieur, la requête revient par votre routeur et Jeedom voit l'adresse de celui-ci au lieu de celle du portier, ce qui fait échouer le contrôle d'origine décrit plus bas.
- Dans le champs URL Template, entrez la chaine suivante : `mac=${MAC}&content=${WARNING_MSG}&type=${TYPE}&date=${DATE}&card=${CARDID}&sip=${SIPNUM}&username=${USERNAME}&doornum=${DOOR_NUM}`.

> Les deux dernières variables (`USERNAME` et `DOOR_NUM`) étaient absentes des versions précédentes de cette documentation alors que le plugin les exploite. Sans elles, le nom de la personne et le numéro de porte ne remontent pas dans les tags de scénario.

- La méthode HTTP (POST ou GET) n'a pas d'importance : le plugin accepte les deux.
- Sauvegarder la configuration.

![GDS3710 Configuration](../assets/images/ConfigGDS3710.png)

## Configuration de l'authentification pour le flux MJPEG
- Rendez-vous dans l'interface de gestion de votre GDS3710 puis dans System Settings -> Access Settings.
- Sélectionnez le mode d'authentification du flux MJPEG (MJPEG Authentication Mode). Nous vous conseillons le mode "Challenge+Response" pour plus de sécurité.

## Activation de l'API HTTP pour l'ouverture de la porte
- Rendez-vous dans l'interface de gestion de votre GDS3710 puis dans Door System Settings -> Basic Settings.
- Cochez la case "Enable HTTP API Remote Open Door".
- Choisissez un PIN pour l'option "Remote PIN to Open Door".
- Sauvegardez la configuration.

NB : Assurez-vous d'avoir changer le mot-de-passe par défaut du compte admin avant d'activer cette fonctionnalité.

## Relevez de l'adresse IP et de l'adresse Mac de votre portier
- Rendez-vous dans l'interface de gestion de votre GDS3710 puis dans Status -> Network info.
- Relevez l'adresse Mac et l'adresse IP de votre portier, nous en aurons besoin plus tard.

# Configuration du plugin GDS3710
## Configuration générale
- Allez à la page de configuration du plugin et saisissez l'identifiant et le mot-de-passe que vous avez choisis à l'étape "Configuration de la fonctionnalité 'Event Notification'".
- Renseignez un répertoire pour le stockage des captures d'écran. Par défaut ce répertoire est : "plugins/gds3710/data/records".

## Création et configuration de votre équipement
- Une fois le plugins installé, créez un nouvel équipement "GDS3710" et activez le.
- Entrez l'adresse MAC (sans les ":") de votre portier dans le champs correspondant.
- Entrez l'Adresse IP de votre portier dans le champs correspondant.
- Saisissez le mot-de-passe du compte admin dans le champs correspondant.
- Saisissez le remote PIN dans le champs correspondant (il s'agit du PIN permettant d'ouvrir la porte).
- Sélectionnez le mode d'authentification du flux MJPEG que vous avez choisis précédement.
- Sauvegardez les modifications apportées à l'équipement.

**C'est terminé, tout est configuré.**

# Configuration automatique du portier

L'équipement expose une commande **« Configurer le portier »** qui écrit elle-même, sur l'appareil, tout ce qu'il faut pour que les évènements remontent : activation de la notification, adresse et protocole de ce Jeedom, gabarit d'URL complet, méthode POST, et identifiants repris de la configuration du plugin (générés s'ils sont vides). Chaque écriture est relue et comparée avant d'être annoncée comme réussie.

C'est la méthode recommandée : la saisie manuelle du gabarit d'URL est la première cause de panne de ce plugin.

L'adresse utilisée est l'**adresse interne** de Jeedom (Réglages → Système → Configuration → Réseaux), donc l'adresse locale et non un nom de domaine public — voir la section Sécurité pour la raison.

Toutes les 15 minutes, le plugin vérifie que la configuration du portier correspond toujours à ce Jeedom. En cas d'écart — réinitialisation de l'appareil, changement d'adresse de Jeedom — un avertissement part dans le log et un message au centre de messages. Sans ce contrôle, la remontée d'évènements s'arrête sans le moindre signe.

# Capteurs du portier

Le plugin relève toutes les 15 minutes les informations que le portier expose déjà : les deux entrées digitales, la sortie digitale, l'état des deux relais de porte, le contact anti-arrachement, la température de la carte mère et celle du capteur (toutes deux historisées), l'uptime, la version de firmware et la disponibilité d'une mise à jour.

⚠️ **Le GDS3710 n'accepte qu'une seule session administrateur.** Chaque relève invalide donc une session éventuellement ouverte sur l'interface web du portier. C'est pourquoi l'intervalle est de 15 minutes et non d'une minute. Décochez « Remonter les capteurs du portier » dans la configuration du plugin le temps d'une session de configuration sur l'appareil.

# Purge des captures

Le champ **« Conserver les captures pendant (jours) »** supprime chaque nuit les captures plus anciennes. À `0`, valeur par défaut, aucune purge n'a lieu : c'est le comportement historique, où le répertoire grossit indéfiniment.

# Exploiter les évènements

Chaque évènement du portier reste disponible sous sa forme brute — le JSON complet, dans la commande du type concerné et dans « Last event ». Mais neuf commandes portent désormais les mêmes informations **décomposées**, renseignées à chaque évènement quel que soit son type :

| Commande | Contenu |
|---|---|
| Dernier évènement - code | le code numérique, par exemple `301` |
| Dernier évènement - libellé | son intitulé, par exemple `Open Door via Private PIN` |
| Dernier évènement - date | l'horodatage transmis par le portier |
| Dernier badge | numéro du badge RFID utilisé |
| Dernier utilisateur | nom associé au badge ou au code PIN |
| Dernière porte utilisée | numéro de porte |
| Dernier numéro SIP | extension appelée |
| Dernière personne entrée | qui est entré, et quand |
| Dernière alerte sécurité | dernier évènement de sécurité |

**Dernière personne entrée** ne se met à jour que sur les évènements où quelqu'un s'est identifié pour ouvrir : elle retient le nom, à défaut le numéro de badge, à défaut le libellé. Un appui sur la sonnette n'efface donc pas le nom de la personne entrée juste avant.

**Dernière alerte sécurité** couvre l'arrachement, l'alarme sous contrainte, l'alarme d'entrée digitale et les codes PIN erronés répétés. Ces quatre évènements écrivent aussi un avertissement dans le log et une entrée au centre de messages.

Le catalogue couvre 41 types d'évènements, relevés sur un portier en firmware 1.0.13.15. Un type que le plugin ne connaîtrait pas est accepté sans erreur : il alimente « Last event » et les commandes décomposées, avec son code en guise de libellé, et un avertissement dans le log.

Les commandes d'évènement, autrefois nommées par leur simple code (`100`, `301`, `1102`), s'appellent maintenant `1102 - Reboot`. Un nom que vous avez personnalisé n'est pas écrasé.

# Réglages du portier

Neuf réglages sont pilotables depuis Jeedom. Chacun se présente en deux commandes : une **info** qui affiche la valeur réellement lue sur l'appareil, et un **curseur** qui l'écrit.

| Réglage | Plage |
|---|---|
| LED clavier - veille | 1 à 255 |
| LED clavier - appui | 1 à 255 |
| Image - luminosité, contraste, saturation | 0 à 128 |
| Délai avant capture après appui sonnette | 0 à 10 s |
| Raccrochage après ouverture distante | 3 à 1800 s |
| Volume système | 0 à 6 |
| Volume sonnerie | 0 à 6 |

Le **planning du rétroéclairage blanc** dispose de son propre jeu : un état, les horaires configurés, deux actions pour activer ou désactiver le planning, et une commande message pour définir l'intervalle au format `HHMMSS`. Couplé au coucher du soleil, c'est le cas « éclairer l'entrée la nuit ».

⚠️ **Les réglages vidéo ne s'appliquent qu'au redémarrage du portier.** Écrire un mode CMOS ou la correction de distorsion ne modifie pas l'image sur le moment : l'appareil enregistre la valeur et ne l'applique qu'au démarrage suivant. Un bouton qui semble « ne rien faire » n'est donc pas forcément cassé — utilisez la commande **Reboot** pour constater le résultat.

## Les commandes sont reliées à leur état

Chaque bouton d'action désigne la commande info qu'il modifie. Jeedom affiche alors la valeur courante sur le bouton, et présente une paire marche/arrêt comme un **interrupteur** plutôt que comme deux boutons sans mémoire.

`LDC - ON` et `LDC - OFF` pointent sur l'état du LDC, les trois modes CMOS sur le mode courant, le planning du rétroéclairage sur son état, et chaque curseur de réglage sur la valeur lue. La liaison est posée automatiquement lors de l'enregistrement de l'équipement.

Quand l'état lié est **binaire**, le bouton reçoit en plus un widget adapté, sur le dashboard comme sur mobile : `LDC - ON` / `LDC - OFF` s'affichent en **interrupteur** (`binarySwitch`), la paire du rétroéclairage en **bouton binaire** (`binaryDefault`), qui montre sa position. Les modes CMOS en sont exclus à juste titre : leur état est une chaîne à trois valeurs, pas un binaire.

Un widget que vous avez choisi vous-même n'est jamais remplacé. Seuls le widget par défaut et les deux widgets binaires posés par le plugin sont susceptibles d'évoluer, puisqu'ils ne peuvent venir que de lui.

Deux garde-fous. Une liaison déjà présente n'est jamais remplacée : si vous en avez établi une autre, elle est conservée. Et un état que l'appareil ne renseigne pas n'est pas relié — le portier répond `(null)` pour le relais de porte lorsque l'ouverture passe par un webrelais et non par son relais local, et afficher cette valeur sur un bouton n'apprendrait rien. La liaison se posera d'elle-même le jour où la valeur apparaît.

## États lus sur l'appareil

Douze états sont remontés, depuis quatre sections de configuration du portier :

| Commande | Paramètre | Valeurs |
|---|---|---|
| Mode CMOS | `P10572` | Normal, Low Light, WDR |
| LDC (correction de distorsion) | `P10573` | actif ou inactif |
| Fréquence secteur | `P12314` | 50 Hz ou 60 Hz |
| Vitesse d'obturation | `P10503` | Auto, 1/30 s … 1/10000 s |
| Codec audio | `P14000` | PCMU, PCMA, G722 |
| Horodatage incrusté | `P10044` | actif ou inactif |
| Texte incrusté, et son contenu | `P10045`, `P10040` | |
| NTP actif, serveur NTP | `P5006`, `P30` | |
| Heure d'été | `P10004` | active ou inactive |
| Fuseau horaire | `P14046` | |

Ils sont relus à chaque cycle de quinze minutes — une seule requête par section, quel qu'en soit le nombre d'états — et immédiatement après une commande `LDC - ON` ou `LDC - OFF` : la valeur stockée change tout de suite, même si son effet sur l'image attend le redémarrage.

💡 Trois de ces états méritent un coup d'œil sur une installation européenne :

- la **fréquence secteur** réglée sur 60 Hz fait scintiller l'image sous éclairage artificiel ;
- l'**heure d'été** désactivée décale d'une heure, en été, l'horodatage de tous les évènements remontés par le portier — y compris l'heure des appels non décrochés ;
- le **codec audio** en PCMU alors que le serveur SIP propose mieux.

Ces réglages se changent dans l'interface du portier ; le plugin les affiche pour que l'écart se voie.

⚠️ **Un réglage retiré par une mise à jour du firmware ne provoque aucune erreur côté portier** : celui-ci répond `ResCode 0 / OK` à l'écriture d'un paramètre qu'il ne connaît pas. Le plugin relit donc systématiquement ce qu'il vient d'écrire et signale dans son log tout paramètre absent de l'appareil, à l'exception des quelques paramètres non relisibles listés ci-dessus.

Toute écriture est bornée à la plage du réglage, puis relue sur l'appareil avant que la commande info ne soit mise à jour : une valeur refusée par le portier n'apparaîtra jamais comme appliquée.

⚠️ Le planning du rétroéclairage exige le **firmware 1.0.13.9 ou supérieur**, et les réglages de luminosité de la LED le **1.0.13.5**. Sur un firmware antérieur, ces commandes resteront sans effet.

# Le tableau des commandes

L'onglet **Commandes** de l'équipement présente, pour chaque commande, sa **valeur actuelle** — avec la date de collecte en infobulle — ainsi que les réglages habituels de Jeedom : affichage, historisation, **minimum**, **maximum** et **unité**.

Ces champs vous appartiennent. Le plugin ne renseigne un nom, une unité ou une plage que lorsqu'ils sont vides : vos modifications ne sont jamais écrasées par un enregistrement de l'équipement, ni par une mise à jour.

⚠️ Le minimum et le maximum d'une commande servent **à l'affichage**, notamment à la course des curseurs. Ils ne relâchent pas le contrôle des écritures vers le portier : celui-ci s'appuie sur la plage réelle du réglage, indépendamment de ce que porte la commande. Une valeur hors plage est refusée et signalée dans le log.

# Client SIP

Le plugin embarque un client SIP qui permet de recevoir l'appel du portier et de lui répondre directement depuis le dashboard, image et son compris.

## Pré-requis

- **Un serveur SIP acceptant le WebSocket** (Asterisk, FreePBX, XiVO, UCM Grandstream…). Le portier et Jeedom s'y enregistrent comme deux clients ordinaires. Sur un UCM Grandstream, il faut activer le module WebRTC et donner le droit correspondant à l'extension utilisée.
- **Jeedom servi en HTTPS.** Les navigateurs refusent l'accès au micro et à la caméra hors contexte sécurisé. Sans cela le widget affiche le problème sur son bouton et n'essaie même pas de s'enregistrer.
- Un **certificat valide sur le serveur SIP** : une connexion `wss://` vers un certificat non approuvé est refusée par le navigateur, sans possibilité d'exception manuelle.

## Configuration

Dans l'onglet configuration de l'équipement, section « Configuration client SIP » :

| Champ | Contenu |
|---|---|
| Adresse du serveur SIP | l'URL du websocket, par exemple `wss://mon-jeedom/sipws` (voir la section CSP ci-dessous) ou `wss://mon-ipbx.local:8089/ws` |
| URI du client SIP | l'extension utilisée par Jeedom, `sip:1003@mon-ipbx.local` |
| Mot de passe du client SIP | le mot de passe de cette extension |
| URI du portier SIP | l'extension du portier, appelable depuis le dashboard |
| Média acceptés / proposés / sortants | ce que le client accepte et propose, par direction |

Utilisez des **adresses joignables depuis le navigateur**, pas un nom de domaine qui ne résout plus.

Le mot de passe du compte SIP n'est **jamais** placé dans la valeur d'une commande. Il est transmis au widget par un appel authentifié, soumis à la session Jeedom et aux droits sur l'équipement. La valeur de la commande ne contient que l'identifiant de l'équipement.

## Voir le visiteur avant de décrocher

Quand le portier appelle, le widget affiche son image **dès la sonnerie**, avant tout décrochage. L'aperçu disparaît lorsque l'appel est pris, la vidéo temps réel prenant le relais.

Chaque cadre vidéo n'apparaît que s'il porte réellement un flux : quand votre caméra n'est pas envoyée — le cas courant, l'en-tête `Permissions-Policy` l'interdisant — le cadre « votre image » ne s'affiche pas du tout, au lieu de rester un rectangle vide. Les lecteurs sont libérés à la fin de l'appel, pour qu'aucune image figée ne subsiste.

L'image affichée est le flux MJPEG que le plugin sert déjà, relayé et authentifié par Jeedom. Le portier annonce pourtant sa propre adresse dans un en-tête `Call-Info` de l'INVITE, mécanisme qu'exploitent les téléphones Grandstream :

```
Call-Info: <https://ADRESSE-DU-PORTIER:443/capture/8001> ;purpose=GDS-view
```

Cette adresse n'est pas utilisable ici. Sur un GDS3710 en 1.0.13.15 elle répond `404`, y compris pendant l'appel et avec une session ouverte sur l'appareil ; et elle serait de toute façon servie en HTTPS avec un certificat auto-signé, qu'un navigateur refuse sans afficher la moindre erreur. Le flux du plugin n'a ni l'un ni l'autre de ces défauts.

⚠️ **L'extension utilisée par Jeedom doit être appelée par le portier**, sinon le widget ne sonnera jamais. Si votre portier appelle un groupe de sonnerie, ajoutez-y l'extension de Jeedom : c'est une omission facile, puisque le client SIP fonctionne parfaitement en émission sans cela.

## Ouvrir la porte depuis la fenêtre d'appel

La fenêtre d'appel présente un bouton par commande d'ouverture **visible** de l'équipement — « Ouvrir la porte » et « Ouvrir la porte 2 ». Masquer l'une de ces commandes dans Jeedom retire son bouton, et la renommer renomme le bouton : il n'y a aucun réglage propre au widget.

Ces boutons exécutent les commandes du plugin, qui passent par l'API HTTP du portier. C'est un choix délibéré face à l'autre voie possible, l'envoi du code par tonalités DTMF : le chemin HTTP est déjà éprouvé, il ne demande pas de confier un second secret au navigateur, et il fonctionne **même hors appel**.

Le clavier reste utilisable pour la méthode DTMF, si vous la préférez ou si le portier est joint depuis un autre client SIP : composez le code d'ouverture pendant l'appel, **terminé par `#`** — le portier n'accepte pas le code sans ce terminateur. Cette voie exige que *Enable DTMF Open Door* soit actif sur l'appareil.

## Signaler les appels non décrochés

Une option de la configuration de l'équipement publie un message dans le **centre de messages de Jeedom** lorsque le portier a sonné sans que personne ne décroche : l'heure de la sonnerie, et un lien vers la capture prise à cet instant. Elle est **désactivée par défaut**, et le délai avant conclusion est réglable (45 secondes par défaut, minimum 10).

⚠️ **Une limite qu'il faut comprendre avant d'activer l'option.** Le portier n'émet aucun évènement de fin d'appel : ni durée, ni statut. Ses trois évènements d'appel — `CallOutLog`, `CallInLog`, `CallLogDoorBellCall` — annoncent seulement qu'un appel a commencé. Le plugin ne peut donc constater qu'une chose : que **personne n'a décroché depuis Jeedom**. Si vous répondez sur un autre poste — un moniteur d'intérieur, un téléphone — le plugin l'ignore et signalera quand même un appel non décroché. Le message est formulé en conséquence et ne prétend pas davantage.

La capture est prise **dès la sonnerie**, sans attendre le délai : quelques secondes plus tard, le visiteur peut avoir quitté le champ.

Elle apparaît sous forme de **lien**, pas de vignette. Le centre de messages de Jeedom fait passer son contenu par `htmlspecialchars` puis ne conserve que les balises `<i>` et `<a>` : une image ne peut pas y être intégrée.

Le signalement est évalué par le cron du plugin, qui s'exécute chaque minute. Le message peut donc arriver avec ce retard — sans conséquence pour un appel déjà manqué.

## Le titre de la fenêtre d'appel

La fenêtre annonce son état plutôt que de rester muette : le nom de l'équipement au repos, « Appel entrant » pendant la sonnerie, « En communication » une fois l'appel pris.

## Le clavier

La fenêtre d'appel comporte un clavier à douze touches, dont le rôle change selon l'état :

- **Au repos**, il compose un numéro. Le bouton d'appel affiche alors « Appeler *numéro* » et joint cette destination au lieu du portier. Le domaine est repris de l'URI du portier, il suffit donc de saisir l'extension. Le bouton **Effacer** remet la composition à zéro, et celle-ci l'est également après chaque appel : le suivant repart sur le portier.
- **En communication**, les touches envoient des **tonalités DTMF**. C'est par ce canal que le GDS3710 reçoit son code d'ouverture de porte à distance — le champ *Remote PIN to Open Door* de sa configuration. Composez le code pendant l'appel pour ouvrir sans quitter le dashboard.

## Politique de sécurité du navigateur (CSP)

L'image Docker de Jeedom envoie un en-tête `Content-Security-Policy` sans directive `connect-src`. Le navigateur retombe donc sur `default-src 'self'` et **refuse toute connexion websocket vers un autre domaine** — y compris votre serveur SIP. Le widget détecte ce cas et l'affiche sur son bouton.

Aucun plugin ne peut lever cette restriction : elle s'applique à la page du dashboard, servie par le cœur de Jeedom, et les modules Apache nécessaires à un relais (`mod_proxy_wstunnel`, `mod_rewrite`) ne sont pas chargés dans l'image officielle.

### Solution recommandée : publier le websocket sur le domaine de Jeedom

Plutôt que d'affaiblir la politique de sécurité, relayez le websocket SIP depuis le domaine de Jeedom lui-même. L'URL devient alors *same-origin*, ce que `'self'` autorise déjà : **la CSP reste inchangée**.

Exemple pour nginx, dans le bloc `server` de Jeedom :

```nginx
location /sipws {
    rewrite ^/sipws(/.*)?$ /ws break;
    proxy_pass http://ADRESSE-DU-SERVEUR-SIP:PORT;

    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;

    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_http_version 1.1;
    proxy_read_timeout 3600s;
}
```

Le `rewrite` traduit `/sipws` vers le chemin réel du websocket sur votre serveur SIP — `/ws` sur un UCM Grandstream. Renseignez ensuite `wss://VOTRE-JEEDOM/sipws` comme adresse du serveur SIP dans la configuration de l'équipement.

Le `proxy_read_timeout` évite que nginx coupe la connexion après soixante secondes sans trafic, ce qui déconnecterait le client SIP en pleine veille.

Sous **Nginx Proxy Manager**, la même chose se fait sans toucher aux fichiers : onglet *Custom locations* de l'hôte Jeedom, location `/sipws`, schéma `http`, l'adresse et le port du serveur SIP, puis la ligne `rewrite` dans le champ avancé de cette location — la roue dentée à droite. Les en-têtes websocket et votre liste d'accès y sont ajoutés automatiquement. Attention à ne pas confondre avec l'onglet *Advanced* de l'hôte : une location écrite là n'hérite **pas** de la liste d'accès, il faut y reposer les règles à la main.

**Conséquence à connaître** : le dashboard doit être ouvert par ce même domaine. Si vous accédez à Jeedom par son adresse IP, `wss://VOTRE-JEEDOM/sipws` n'est plus *same-origin* et la CSP bloque de nouveau.

### Autre solution : remplacer l'en-tête

Si vous ne pouvez pas relayer le websocket, remplacez l'en-tête au niveau du reverse proxy :

```nginx
proxy_hide_header Content-Security-Policy;
add_header Content-Security-Policy "default-src 'self' file: data: blob: filesystem:; connect-src 'self' wss://VOTRE-SERVEUR-SIP; script-src 'self' 'unsafe-inline' 'unsafe-eval'; img-src 'self' * data:; style-src 'self' 'unsafe-inline'; worker-src blob:; frame-src 'self' *.jeedom.com data:;" always;
```

Le `proxy_hide_header` est indispensable : lorsque plusieurs en-têtes CSP sont présents, le navigateur applique leur **intersection**. Ajouter un second en-tête plus permissif ne relâche donc rien, il faut retirer celui de Jeedom.

Une surcharge dans le `.htaccess` de Jeedom fonctionne également, mais ce fichier appartient au cœur et sera écrasé à sa prochaine mise à jour.

## Caméra interdite par l'en-tête `Permissions-Policy`

La même image Docker envoie un second en-tête, `Permissions-Policy`, qui contient `camera=()`. Celui-ci **interdit la caméra à la page elle-même**, indépendamment de toute autorisation : le navigateur refuse sans rien demander. JsSIP rapporte alors `User Denied Media Access`, un message trompeur puisqu'aucun refus de votre part n'a eu lieu. Le micro, lui, n'est pas restreint.

Le widget détecte ce cas et **poursuit l'appel en audio seul**, tout en continuant de réclamer le flux vidéo du portier : vous voyez et entendez le visiteur, il vous entend. Envoyer sa propre caméra vers un portier dépourvu d'écran n'apporte rien. Le même repli s'applique si la machine n'a tout simplement pas de webcam.

Si vous tenez à émettre votre image, remplacez l'en-tête sur votre reverse proxy. Attention à un piège de nginx : un `add_header` placé dans le bloc `server` est **ignoré** dès qu'une `location` en définit un autre — et celle qui sert Jeedom en définit un pour le HSTS. Il faut donc le poser dans cette `location` :

```nginx
proxy_hide_header Permissions-Policy;
add_header Permissions-Policy "accelerometer=(),battery=(),fullscreen=(self),geolocation=(),camera=(self),ambient-light-sensor=(self),autoplay=(self)" always;
```

## Débogage SIP

L'option **Débogage SIP** de la configuration de l'équipement active la journalisation détaillée de JsSIP dans la console du navigateur. Elle est précieuse pour diagnostiquer un échec d'appel : elle donne les messages SIP complets et, surtout, la cause réelle d'une erreur WebRTC que le widget ne peut afficher que sous forme générique.

⚠️ **Elle expose le mot de passe du compte SIP.** JsSIP journalise l'intégralité de son objet de configuration au démarrage, mot de passe compris, en clair dans la console. N'activez cette option que le temps d'un diagnostic, et **désactivez-la ensuite**. Si vous avez partagé un journal obtenu ainsi, changez le mot de passe de l'extension.

## Les deux réglages « HACK »

Le client produit un message d'invitation très long, que certains serveurs refusent au-delà d'une taille limite. Les deux champs en bas de section permettent de l'alléger :

- **Codec(s) à supprimer** : par exemple `VP8,VP9,rtx,red,ulpfec` ne laisse que le H.264.
- **Suppression de ligne de l'INVITE** : une expression régulière pour retirer des lignes supplémentaires.

Si l'appel échoue avec une erreur de type « Unknown error » côté serveur, c'est la première piste à essayer.

# Sécurité

Deux protections encadrent la remontée d'évènements.

**Contrôle de l'adresse d'origine.** Actif par défaut et sans configuration : un évènement n'est accepté que s'il provient de l'adresse IP renseignée pour l'équipement. L'adresse MAC du portier, qui identifie l'équipement dans la requête, est inscrite sur l'appareil : elle ne constitue pas un secret. Sans ce contrôle, toute machine du réseau pourrait publier de faux évènements et déclencher vos scénarios.

Si votre Jeedom est derrière un NAT ou un reverse proxy qui masque l'adresse réelle du portier, décochez ce contrôle dans la configuration générale du plugin. Le symptôme est explicite dans le log : `Evenement refuse : recu depuis <adresse> alors que le portier <mac> est configure sur <adresse>`.

**Protection par mot de passe.** Activée par défaut sur les nouvelles installations, elle est vivement conseillée dès lors que des évènements du portier déclenchent des actions sensibles comme une ouverture de porte. Sur une installation existante, une mise à jour du plugin ne modifie pas votre réglage : pensez à l'activer.

**Accès aux captures et au flux vidéo.** Ils ne sont accessibles qu'à un utilisateur connecté à Jeedom, ou avec une clef API valide.

# Utilisation
## Principe de fonctionnement
Chaque évènement envoyé par le GDS3710 comporte un type dont voici la liste (extrait de la documentation du GDS3710 sur la fonctionnalité "event notification", disponible ici : http://www.grandstream.com/sites/default/files/Resources/gds_event_logs_guide.pdf):

| Type d'évènement | Nom | Description de l'évènement|
| ------------ | ------------ |------------ |
|100 |Open Door via Card|Indicates that someone opens the door via card or key fob.|
| 101  |  Open Door via Card (over Wiegand) | Indicates that someone opens the door via card or key fob using Wiegand interface connected to GDS.|
| 200  | Visiting Log  | Indicates that door has been opened for visitor which pressed door bell button.|
|300|Open Door via Universal PIN |Indicates that door has been opened successfully using local PIN code via GDS keypad.|
|301 |Open Door via Private PIN |Indicates that someone opened the door successfully using their private PIN code via GDS keypad.|
|302 |Open Door via Guest PIN |Indicates that a guest used “Guest PIN” code to open the door using GDS keypad.|
|400 |Open Door via DI |Indicates that door has been opened using DI (Digital Input) Signal, such as using a push button.|
|500 |Call Out Log |Indicates the GDS unit initiated a call out, for example when someone uses the keypad to dial a number or press door bell button which preconfigured destination number.|
|501 |Call In Log |Indicates that call has been received by the GDS unit.|
|504 |Call Log (Door Bell Call) |Indicates that someone has initiated a call using door bell button.|
|600 |Open Door via Card and PIN |Indicates that someone used his RFID card or key fob, plus his own private password to authenticate and open the door.|
|601 |Keep Door Open (Immediately)|Key door Open (immediately) action has been performed from the web Interface.|
|602 |Keep Door Open (Scheduled)|Key door Open (immediately) action has been set from the web Interface and the event is triggered.|
|700 |Open Door via Remote PIN |Indicates that someone did send remote PIN code to open the door using GDS manager tool for example.|
|800 |HTTP API Open Door |Indicates that someone did send remote PIN code to open the door HTTP API command.|
|900 |Motion Detection |Indicates that motion detection is triggered.|
|1000 |DI Alarm |Indicates that alarm IN is triggered.|
|1100 |Dismantle by Force |Indicates that the unit has been dismantled by force.|
|1101 |System up |Indicates that the system is UP|
|1102 |Reboot |Indicates that the GDS unit has been rebooted.|
|1103 |Reset (Clear All Data) |Factory reset (clear all data) has been performed.|
|1104 |Reset (Retain Network Data Only) |Factory reset (Retain Network Data Only) has been performed.|
|1105|Reset (Retain Only Card Information)|Factory reset (Retain Only Card Information) has been performed.|
|1106|Reset (Retain Network Data and Card Information) |Factory reset (Retain Network Data and Card Information) has been performed.|
|1107 |Reset (Wiegand) |Factory reset using Wiegand module has been performed on the unit.|
|1108 |Config Update |Indicates that the system’s configuration has been updated.|
|1109 |Firmware Update (1.0.0.0)|Indicates that the system’s firmware has been upgraded.|
|1200 |Hostage Alarm |Indicates that someone has entered the hostage alarm PIN code to open the door.|
|1300 |Invalid Password |Indicates that someone has entered wrong password PIN code to open the door for 5 attempts and corresponding alarm action has been triggered.|
|1400|Mainboard Temperature(32°C) Normal |Indicates that device’s mainboard temperature is normal, (around 32°C).|
|1401|Mainboard Temperature(32°C) Too Low |Indicates that device’s mainboard temperature is too low.|
|1402|Mainboard Temperature(32°C) Too high |Indicates that device’s mainboard temperature is too high.|
|1403 |Sensor Temperature(32°C) Normal |Indicates that device's sensor temperature is normal, (around 32°C).|
|1404 |Sensor Temperature(32°C) Too Low |Indicates that device's sensor temperature is normal too low.|
|1405 |Sensor Temperature(32°C) Too High |Indicates that device's sensor temperature is normal too high.|

## Utilisation des évènements déclenchés avec des commandes d'actions
Pour chaque code d'évènement vous avez la possibilité dans les onglets "Appel", "Ouverture Porte", "Maintient de l'ouverture", "Sécurité", "Surveillance Matériel" et "Surveillance Logiciel" de créer une liste de commandes qui seront exécutées lors de la réception de ces évènements. Utilisez simplement le bouton "Ajouter une action", présent à coté de chaque type d'évènement puis sélectionner l'action à réaliser. Une fois les actions ajoutées, vous avez la possibilité de changer l'ordre d'éxécution en les faisant glisser.

## Utilisation des évènements déclenchés avec des scénarios
L'ajout d'un scénario en réponse à un évènement reçu se fait en saisissant "scenario" dans le champs "action" après avoir cliqué sur le bouton "Ajouter une action". Une nouvelle boite de dialogue vous permettra alors de sélectionner le scénario à exécuter.

Lors de l'exécution du scénario, les informations reçues par jeedom seront transmises au scénario par les biais des Tags suivant :

|Tag|Contenu|
|-------|--------|
|#mac#|Contient l'adresse Mac de l'appareil ayant envoyé la notification|
|#content# |Contient un message de description de la notification envoyé par le GDS3710|
|#type#|Contient le type de la notification|
|#date#|Contient l'heure et la date de la notification|
|#sip#|Contient le numéro SIP relatif à la notification|
|#card#|Contient le numéro de la carte relatif à la notification|

## Utilisation des commandes de type INFO de l'équipement
L'onglet "commandes" contient des commandes de type info contenant pour chaque type d'évènement le dernier évènement reçue au format JSON. La commande "Last event" contient la dernier évèneement réceptionné.

## Utilisation des commandes de type ACTION de l'équipement
L'équipement dispose de commandes de type ACTION permettant de réaliser les actions suivantes :
- Ouverture de la porte.
- Fermeture de la porte.
- Réalisation d'une capture d'écran.

## Envoi de captures du flux MJPEG via un scénario
Le plugin vous permet de transmettre des captures du flux MJPEG par l'intermédiaire d'un plugin tiers (testé avec Telegram).
- Ajoutez un bloc d'action dans un scénario et sélectionnez la commande "[Envoyer un snapshot]" de votre équipement GDS3710.
- Dans le champs "Nombre captures ou options" entrez le nombre de captures à envoyer.
- Dans le champs "Commande message d'envoi des captures" sélectionner la commande pour envoyer la ou les captures (il s'agit de la commande de votre bot Telegram).

![Envoyer un snapshot dans un scénario](../assets/images/EnvoyerCaptureGDS3710.png)

# La porte 2 peut piloter une commande Jeedom

⚠️ **En mode webrelay (`P15440=1`), le portier n'a qu'une seule URL de relais.** Toute ouverture — clavier, badge, PIN distant 1 *ou* 2, et les deux boutons du plugin — appelle cette unique URL et déclenche donc la même action. La porte 2 n'a aucun effet propre, et les deux boutons sont indiscernables.

La configuration de l'équipement permet d'associer une **commande Jeedom** à la porte 2. Le bouton « Ouvrir la porte 2 » exécute alors cette commande au lieu d'interroger le portier. C'est ainsi qu'un portail dont le portier ne commande que le vantail piéton obtient un second bouton pour l'ouverture complète — et ce bouton reste disponible dans la fenêtre d'appel du client SIP, qui liste les commandes d'ouverture visibles de l'équipement.

💡 Pensez à **renommer la commande** en conséquence — « Ouverture complète », par exemple. **Un nom que vous avez changé n'est jamais remis** : le plugin ne remplit qu'un nom vide. Cela vaut pour toutes les commandes de l'équipement, y compris les plus anciennes.

Laissez le champ vide pour interroger le portier comme avant. « Fermer la porte 2 » n'a alors plus de sens et n'exécute rien, ce qui est journalisé.

# Maintien de porte ouverte et détection de mouvement

Deux groupes de l'API du portier que le plugin ignorait jusqu'ici.

## Maintien de porte ouverte

Chaque porte rapporte son **mode** — désactivé, immédiat ou planifié —, la **durée** du maintien en minutes, et, lorsqu'elle est maintenue, **depuis quand**. Deux commandes l'activent et le désactivent, un curseur règle la durée entre 5 et 480 minutes.

⚠️ **Ces commandes sont créées masquées.** Activer le maintien **déverrouille la porte et l'y laisse** pendant toute la durée configurée : ce n'est pas quelque chose qui doit atterrir sur un dashboard par inadvertance. Rendez-les visibles délibérément, une fois que vous savez que vous en voulez.

La commande « Porte 1 forcée ouverte depuis » vaut `(null)` tant que la porte n'est pas maintenue : un scénario peut donc vérifier qu'aucune porte n'est restée ouverte.

## Détection de mouvement

Son état, sa **sensibilité** (0 à 100) et son **planning d'alarme** sont rapportés ; deux commandes l'arment et la désarment. C'est ce qui la rend utile depuis un scénario : armer en partant, désarmer en rentrant. Le portier émet déjà l'évènement de type 900 lorsqu'elle se déclenche.

⚠️ **Les régions de détection sont rapportées mais jamais écrites.** Les huit régions doivent être définies ensemble et se dessinent dans l'interface du portier. Sur un appareil où aucune n'est définie — l'état d'usine, toutes les coordonnées à zéro — armer la détection risque fort de ne rien déclencher. La commande « Détection - régions » existe précisément pour que cela se voie, au lieu de rester une énigme.

Les réglages de détection vivent dans la section `event` du portier, qui renvoie le mot de passe administrateur en clair dans `P2`. Le plugin ne journalise jamais une section brute, et `redact()` masque `P2` de toute façon.

# Langues

**L'interface du plugin est traduite** en anglais, en allemand et en espagnol. Le catalogue vit dans `core/i18n/` et couvre **232 chaînes** ; `tools/extract_i18n.py` le tient en phase avec le code, et un contrôle refuse toute chaîne ajoutée sans traduction. Le français est la langue source : il n'a pas de catalogue.

Les **noms de commandes** sont traduits là où ils sont déclarés : une installation neuve dans une autre langue obtient donc des noms de commandes traduits. ⚠️ **Les commandes déjà existantes ne sont jamais renommées**, ni par une mise à jour ni par un changement de langue. Le plugin ne remplit qu'un nom vide — c'est aussi ce qui protège une commande que vous auriez renommée vous-même.

# Tests unitaires

Le dossier `tests/` porte une **suite de tests unitaires** qui tourne **sans installation Jeedom et sans aucune dépendance à installer** : ni Composer, ni PHPUnit. `tests/bootstrap.php` reconstitue l'arborescence minimale que la classe du plugin attend et la charge face à des doublures du cœur de Jeedom, ce qui permet d'éprouver directement les fonctions pures.

Lancez-les avec `./.lint.sh --tests`, ou `php tests/run.php` si PHP est disponible localement. Elles s'exécutent aussi sur **PHP 8.1, 8.2 et 8.3** à chaque publication.

Ce qu'elles couvrent : le masquage des secrets avant journalisation, les réponses malformées du portier, le calcul de l'URL d'une capture, et la **cohérence des tables de déclaration** — ce dernier contrôle aurait attrapé deux défauts réellement livrés par ce plugin : un libellé de section écrit une fois avec accent et une fois sans, qui produisait deux onglets identiques, et un identifiant de commande entrant en collision avec un autre à la casse près, qui rendait l'équipement insauvegardable.

⚠️ **Cette documentation, elle, n'existe qu'en français.** Les quatre dossiers de langue attendus par Jeedom contiennent le même texte français.
