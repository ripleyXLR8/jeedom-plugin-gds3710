# Change Log - Plugin GDS 3710

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
