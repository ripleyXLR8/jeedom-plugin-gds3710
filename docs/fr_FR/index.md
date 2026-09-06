# Plugin GDS 3710
> Version du 5 mars 2019
> by Richard Perez | richard@perez-mail.fr

# Introduction
Ce plugin permet l'intégration du portier GrandStream GDS3710 dans Jeedom. Dans sa version actuelle (5 mars 2019), il permet de :
- Récupérer es évènements du portier et de les gérer via des scénariis ou des commandes.
- D'afficher le flux MJPEG du portier dans un widget de dashboard ou de mobile.
- D'enregistrer des images extraites du flux MJPEG.
- De consulter les images enregistrées via une bibliothèque intégrée.
- D'envoyer des images enregistrées via une autre commmande (testé avec le plugin Telegram).

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

Sept réglages sont pilotables depuis Jeedom. Chacun se présente en deux commandes : une **info** qui affiche la valeur réellement lue sur l'appareil, et un **curseur** qui l'écrit.

| Réglage | Plage |
|---|---|
| LED clavier - veille | 1 à 255 |
| LED clavier - appui | 1 à 255 |
| Image - luminosité, contraste, saturation | 0 à 128 |
| Délai avant capture après appui sonnette | 0 à 10 s |
| Raccrochage après ouverture distante | 3 à 1800 s |

Le **planning du rétroéclairage blanc** dispose de son propre jeu : un état, les horaires configurés, deux actions pour activer ou désactiver le planning, et une commande message pour définir l'intervalle au format `HHMMSS`. Couplé au coucher du soleil, c'est le cas « éclairer l'entrée la nuit ».

⚠️ **Un réglage retiré par une mise à jour du firmware ne provoque aucune erreur côté portier** : celui-ci répond `ResCode 0 / OK` à l'écriture d'un paramètre qu'il ne connaît pas. Le plugin relit donc systématiquement ce qu'il vient d'écrire et signale dans son log tout paramètre absent de l'appareil. C'est ce mécanisme qui a permis d'identifier la disparition du réglage LDC.

Toute écriture est bornée à la plage du réglage, puis relue sur l'appareil avant que la commande info ne soit mise à jour : une valeur refusée par le portier n'apparaîtra jamais comme appliquée.

⚠️ Le planning du rétroéclairage exige le **firmware 1.0.13.9 ou supérieur**, et les réglages de luminosité de la LED le **1.0.13.5**. Sur un firmware antérieur, ces commandes resteront sans effet.

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