Rapport complet des travaux effectués : Gestion de campagnes d'emailing
Ce rapport présente une description détaillée des fonctionnalités que vous avez implémentées, de la création d'un serveur SMTP à la gestion complète d'une campagne d'emailing. Chaque étape est expliquée à l'aide de scénarios concrets pour en illustrer le fonctionnement.

1. Création et gestion des serveurs SMTP
La base de toute campagne emailing est le serveur SMTP (Simple Mail Transfer Protocol), qui permet d'envoyer les emails.

Scénario
Un utilisateur souhaite utiliser son compte SMTP de "SendGrid". Il se rend sur la page de création d'un serveur SMTP et saisit les informations requises : nom du serveur, adresse de l'API (https://api.sendgrid.com/v3/mail/send) et une clé d'API (optionnelle). Il choisit également si ce serveur est actif et sa priorité. Après validation, le serveur de l'API de SendGrid est enregistré et prêt à être utilisé pour des campagnes.

2. Création et gestion des listes de contacts
Avant de lancer une campagne, il faut définir les destinataires.

Scénario
L'utilisateur souhaite importer une liste de contacts. Plutôt que de les saisir un par un, il télécharge un fichier JSON contenant l'adresse email de chaque contact. Le système traite ce fichier de manière optimisée grâce à la bibliothèque JsonMachine, sans surcharger la mémoire du serveur, même pour un grand nombre de contacts. Le processus d'importation est géré par une tâche en arrière-plan (ProcessCampaignImport Job), ce qui permet à l'utilisateur de continuer à utiliser l'application pendant que l'importation se termine.

3. Création et lancement d'une campagne
Une fois que les listes de contacts sont prêtes, l'utilisateur peut créer et lancer sa campagne.

Scénario
L'utilisateur crée une nouvelle campagne pour promouvoir une offre spéciale.

Détails de la campagne : Il donne un nom à sa campagne ("Offre Spéciale d'Été"), un objet d'email ("Économisez 50% sur notre nouvelle collection !") et un statut ("brouillon").

Configuration : Il choisit le modèle d'email HTML à utiliser. Il sélectionne ensuite les listes de diffusion ciblées (par exemple, "Clients fidèles" et "Inscrits à la newsletter") et associe plusieurs serveurs API pour répartir la charge d'envoi et optimiser la vitesse. Pour chaque serveur API, il définit l'adresse et le nom de l'expéditeur, la fréquence d'envoi et le nombre maximum d'emails par jour pour éviter d'être bloqué.

Lancement : Une fois la campagne configurée, il clique sur "Lancer". Le statut de la campagne passe à "active" et une tâche en arrière-plan (ProcessCampaignEmails Job) est lancée.

4. Exécution de la campagne : Envoi d'emails via API
Le processus d'exécution de la campagne est central. C'est ici que l'application va interagir avec les serveurs d'API distants pour acheminer les messages, sans utiliser directement le protocole SMTP.

Scénario
La tâche d'arrière-plan (ProcessCampaignEmails Job) est activée. Elle a pour rôle de gérer l'envoi de chaque email individuel.

Préparation de la requête API : Pour chaque email à envoyer, la tâche récupère les informations du serveur API associé (l'adresse de l'API et la clé d'API (optionnel)). Le système prépare alors toutes les données de l'email (destinataire, objet, contenu HTML, etc.) et les regroupe dans un format JSON, prêt à être envoyé. C'est à cette étape que le contenu HTML est enrichi avec un pixel de suivi invisible, des liens raccourcis et un lien de désabonnement unique pour chaque contact.

Envoi à distance via une requête HTTP : Le système utilise ensuite une requête HTTP POST pour envoyer le paquet de données JSON à l'URL de l'API distante. Cette communication est gérée de manière sécurisée et ne nécessite pas de configurer manuellement le client SMTP, ce qui simplifie le processus. C'est le service distant (par exemple, SendGrid) qui se charge alors de l'envoi effectif de l'email vers le destinataire. La fonction sendToRemoteEmailApi dans le job gère cet appel, renvoyant un statut de succès ou d'échec.

Gestion des échecs et failover : Si l'envoi échoue avec le premier serveur API, le système ne s'arrête pas. Il essaie automatiquement le serveur suivant dans la liste (mécanisme de failover), garantissant une meilleure résilience de la campagne et maximisant le taux d'envoi. Chaque tentative et chaque résultat sont enregistrés dans la base de données via un journal (EmailLog).

Tracking et journalisation : Une fois l'email envoyé, le système crée un journal d'email (EmailLog) qui enregistre les détails de l'envoi, comme l'adresse du destinataire, le statut d'envoi et l'heure. Cette journalisation est cruciale pour le suivi de la campagne. Chaque email contient également une balise de suivi invisible et des liens raccourcis.

Ouverture : Quand le destinataire ouvre l'email, la balise de suivi se charge, déclenchant une requête vers l'URL de suivi de l'application. Cette requête est captée par la route track.open dans web.php et mise à jour dans la base de données via le TrackingController.

Clics : De même, chaque lien est transformé en URL courte (par exemple, http://votredomaine.com/l/abcde). Quand le destinataire clique, la route track.click est appelée, le TrackingController enregistre le clic, et le destinataire est redirigé vers l'URL d'origine.

Ces étapes permettent un contrôle précis et une visibilité complète sur le chemin de chaque email, de l'application jusqu'à la boîte de réception du destinataire, en passant par le serveur API distant.

5. Gestion de la campagne en cours
L'utilisateur peut suivre et contrôler sa campagne en temps réel.

Scénario
Pendant que la campagne est active, l'utilisateur remarque que le taux d'ouverture est faible et souhaite faire une pause pour ajuster le contenu.

Mise en pause : Il se rend sur la page de la campagne et clique sur le bouton "Pause". Le statut de la campagne passe à "en pause", et la tâche d'envoi est suspendue.

Reprise : Après avoir modifié le modèle d'email, il clique sur "Reprendre". Le statut redevient "active", et la tâche reprend l'envoi des emails là où elle s'était arrêtée.

6. Fin de la campagne et statistiques
Une fois tous les emails envoyés, le système fournit un rapport détaillé.

Scénario
La campagne a terminé l'envoi de tous les emails. Le statut passe à "terminée".

Statistiques : L'utilisateur consulte la page des statistiques pour obtenir un rapport complet de la campagne. Il voit des métriques clés comme le nombre total d'emails envoyés, le taux d'ouverture, le taux de clics et le nombre de désabonnements.

Analyse : Grâce à ces informations, il peut évaluer l'efficacité de sa campagne et prendre des décisions pour les prochaines. Par exemple, si le taux d'ouverture est faible, cela pourrait indiquer que l'objet de l'email n'était pas assez accrocheur.

Ces fonctionnalités permettent une gestion complète et efficace des campagnes d'emailing, de la configuration initiale à l'analyse des résultats, en passant par le suivi et le contrôle en temps réel.
