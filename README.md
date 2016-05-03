Paypal Https
========================

Ce module a pour but de faciliter vos paiements grâce à l'API de paypal

Procédure
--------------


  * importer le dossier PaypalBundle dans votre dossier Src

  * Ajouter dans le composer la dépendance au sdk de paypal en entrant la commande suivante dans le composer.json
   "paypal/rest-api-sdk-php": "^1.7.0"

  Il ne vous reste plus qu'à utiliser le service paypal_interface depuis n'importe lequel de vos bundles, comme dans l'exemple disponible dans le DefaultController.