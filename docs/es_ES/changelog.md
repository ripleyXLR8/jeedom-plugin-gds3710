# Change Log - Plugin GDS 3710

### 07/09/2026 (traductions et intégration continue)
- **L'interface du plugin est traduite** en anglais, en allemand et en espagnol. Le plugin n'avait aucun fichier de langue : tout s'affichait en français, y compris pour une installation configurée autrement. Le catalogue couvre **152 chaînes** ; `tools/extract_i18n.py` l'extrait du code et signale toute chaîne ajoutée sans traduction. La documentation, elle, reste en français.
- **Le dépôt a une intégration continue.** Les contrôles ne tournaient qu'à la main, depuis le poste du mainteneur. Ils s'exécutent désormais à chaque publication, sur **PHP 8.1, 8.2 et 8.3** : syntaxe PHP, syntaxe du JavaScript de la page d'équipement et du widget SIP, complétude des traductions, et cohérence entre le README et la documentation. La CI appelle le même script que le mainteneur, sans recopier la liste des contrôles.
### 06/09/2026 (fiabilité des écritures)
- Les commandes **LDC ON / OFF sont conservées**, après une fausse alerte. Le portier ne renvoie `P10573` dans aucune de ses sections de configuration, ce qui avait fait conclure à tort que le réglage n'existait plus. Il est en réalité bien accepté et appliqué, mais **seulement au redémarrage suivant** — d'où un bouton qui paraissait sans effet. Les commandes sont recréées sur les installations où la version précédente les avait supprimées, et la relecture ne signale plus ce paramètre comme une erreur.
- **Toute écriture de configuration est désormais relue** sur le portier. Un paramètre inconnu de l'appareil est signalé dans le log au lieu de passer pour un succès.

### 06/09/2026 (catalogue d'évènements)
- Six types d'évènements ajoutés, relevés dans le firmware 1.0.13.15 du portier : `102` tentative d'ouverture non autorisée, `401` ouverture par SI, `1002` anomalie porte/serrure, `1110` accès hors planning, `1500` connexion administrateur, `1503` déconnexion administrateur.
- Les types 102, 1002 et 1110 sont traités comme des évènements de sécurité : entrée au centre de messages et mise à jour de « Dernière alerte sécurité ».

### 06/09/2026 (client SIP)
- Le **client SIP** est disponible sur la version stable. Il permet de répondre au portier depuis le dashboard.
- Le mot de passe du compte SIP ne transite plus par la valeur d'une commande — où il était persisté, historisé et exposé par l'API — mais par un appel authentifié soumis aux droits sur l'équipement.
- Les six réglages de média audio et vidéo, que le widget lisait mais que l'interface n'a jamais proposés, sont désormais configurables. Ils étaient auparavant tous inactifs, ce qui empêchait toute négociation.
- Les pré-requis (contexte sécurisé, bibliothèque chargée) sont vérifiés et signalés en clair sur le widget, au lieu d'un échec silencieux.
- Nettoyage des valeurs de commande corrompues par un défaut d'affichage corrigé en mars 2023 mais jamais purgé.

### 06/09/2026 (bloc C)
- Les évènements sont **décomposés** en neuf commandes exploitables : code, libellé, date, badge, utilisateur, porte, numéro SIP, dernière personne entrée et dernière alerte sécurité. Le JSON brut reste disponible.
- Les évènements de sécurité (arrachement, contrainte, alarme d'entrée, PIN erronés) écrivent au centre de messages.
- Les commandes d'évènement portent un nom lisible : `1102 - Reboot` au lieu de `1102`.
- Sept **réglages du portier** pilotables : luminosité de la LED du clavier au repos et à l'appui, luminosité, contraste et saturation de l'image, délai avant capture, raccrochage après ouverture distante. Chaque réglage associe une commande info et un curseur.
- **Planning du rétroéclairage blanc** : activation, horaires, et lecture de l'état.

### 06/09/2026 (bloc B)
- Correction du bug « Une commande portant ce nom (Reboot) existe déjà », ouvert depuis 2020 : la collation de la base rendait indistinguables la commande action `reboot` et la commande d'évènement `Reboot`.
- Les évènements sont désormais distribués à **tous** les équipements partageant une adresse MAC, et non plus à un seul.
- Une adresse MAC inconnue répond 404, une requête sans type 400 — au lieu de 200 dans les deux cas.
- Nouvelle commande **« Configurer le portier »** : écrit sur l'appareil toute la configuration de notification, puis la relit pour confirmer.
- Remontée des **capteurs du portier** toutes les 15 minutes : entrées/sorties digitales, relais, anti-arrachement, deux températures historisées, uptime, firmware, mise à jour disponible.
- **Purge configurable des captures**, désactivée par défaut.
- Les échecs d'écriture de capture ne sont plus silencieux, et l'URL du flux MJPEG se répare toute seule.
- Correction du `.htaccess`, livré sans les `Options` qu'exige sa règle de réécriture.

### 06/09/2026
- **Sécurité** : `camera.php` diffusait le flux vidéo du portier sans aucun contrôle d'accès ; il exige désormais une session Jeedom ou une clef API valide.
- **Sécurité** : suppression de deux injections de commande shell, dans la suppression des captures et dans le téléchargement d'archives.
- **Sécurité** : durcissement de l'endpoint de notification d'évènements — contournement de la clef API corrigé, Digest résistant au rejeu, et nouveau contrôle de l'adresse d'origine actif par défaut.
- **Sécurité** : les mots de passe, jetons de session et URL signées ne sont plus écrits dans le log du plugin.
- **Compatibilité PHP 8** : correction de plusieurs erreurs fatales, dont celle qui interrompait toute remontée d'évènements dès qu'un firmware émettait un type inconnu du plugin.
- Les évènements envoyés en **GET** sont désormais acceptés, en plus du POST.
- **Documentation** : le gabarit d'URL était incomplet — il manquait `username` et `doornum`, pourtant exploités par le plugin. Ajout des seuils de firmware réels et d'une section Sécurité.
- Suppression de code mort (démon Python 2 jamais lancé, bibliothèque JsSIP non référencée, polices dupliquées).

### 20/09/2019
- Correction de bugs + ajout de la possibilité d'activer le second contact sec.

### 11/03/2019
- Ajout de la commande permettant de modifier la configuration du GDS.

### 09/03/2019
- Ajout de récupérer le chemin d'accès à la dernière capture d'écran.

### 05/03/2019
- Ajout de la possibilité d'envoyer les snapshots via une autre commande.

### 02/02/2019
- Correction d'un bug empêchant l'apparation des commandes "Prendre un snapshot" et "Historique des snapshopt"

### 01/02/2019
- Ajout des commandes "Prendre un snapshot" et "Historique des snapshopt"

### 04/01/2019
- Ajout de ligne de debug lors de l'éxécution des actions

### 01/01/2019
- Correction d'un bug d'éxécution des actions détecté par @moicphil

### 27/12/2018
- Correction d'un bug d'affichage du flux MJPEG detecté par @xiringuito

### 22/12/2018
- Amélioration du widget dashboard et mobile.

### 03/12/2018
- Intégration de l'image du portier dans le widget via l'autthentification simple ou challenge-response.

### 27/11/2018
- Ajout de la possibilité de masquer une commande dans la liste des commandes.

### 26/11/2018
- Sécurisation de la communication entre le portier et Jeedom via l'authentification http du portier.

### 19/11/2018
- Ajout d'une commande permettant l'ouverture de la porte via l'API http.

### 14/11/2018 : Version initiale
- Prise en compte des évènements envoyé par le portier et association avec des commandes et des scénarios Jeedom.
