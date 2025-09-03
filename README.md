README : Système Avancé de Gestion de Campagnes d'Emailing

Ce document présente l'architecture et le fonctionnement détaillé de la plateforme de gestion de campagnes d'emailing. Le système est conçu pour une performance, une scalabilité et une robustesse maximales, permettant de gérer des campagnes de plusieurs milliards de contacts.

Flux de Travail et Architecture
Le système suit un flux logique, de la configuration des ressources à l'analyse des résultats. Chaque étape est conçue pour être à la fois puissante et intuitive.
1. Configuration des Canaux d'Envoi (Serveurs SMTP/API)
La base du système est la capacité à se connecter à n'importe quel service d'envoi d'emails via API. Cela offre une flexibilité et une performance bien supérieures au protocole SMTP traditionnel.

Scénario Concret

Un administrateur souhaite intégrer un nouveau service, "Brevo", pour ses campagnes.
Création du Canal : Il se rend dans la section "Serveurs SMTP" et clique sur "Ajouter".
Configuration : Il remplit les champs suivants :
Nom : Brevo - Compte Principal (un nom facile à identifier).
URL : https://api.brevo.com/v3/smtp/email (l'endpoint de l'API de Brevo pour l'envoi).
Clé API : Il colle la clé API fournie par Brevo. Ce champ est sécurisé (type password).
Statut : Il coche la case "Actif" pour le rendre disponible pour les campagnes.
Sauvegarde : Le canal Brevo est maintenant enregistré et prêt à être utilisé. L'administrateur peut en ajouter d'autres (SendGrid, Mailgun, etc.) pour diversifier ses options d'envoi.

2. Création et Lancement d'une Campagne (Processus en 2 Étapes)
La création d'une campagne est un processus guidé en deux temps, conçu pour éviter les erreurs et assurer une configuration complète.
Étape 1 : Définition de la Campagne
Cette étape se concentre sur le "quoi" : le message et son apparence.
Scénario Concret
L'utilisateur veut lancer une campagne promotionnelle pour le Black Friday.
Initialisation : Il clique sur "Nouvelle Campagne".
Informations Générales : Il remplit les champs de base :
Nom de la campagne : Black Friday 2025 - Ventes Flash
Objet du mail : ⚡ Offres Exclusives : Jusqu'à -70% !
Template HTML : Il sélectionne un modèle d'email préalablement créé.
Validation : Il clique sur "Passer à l'étape suivante". La campagne est créée en base de données avec le statut draft (brouillon), et l'utilisateur est automatiquement redirigé vers l'étape 2.
Étape 2 : Configuration Technique et Lancement
Cette étape se concentre sur le "comment" et le "à qui" : les canaux d'envoi, les contacts et les règles de diffusion.
Scénario Concret (suite)
L'utilisateur est maintenant sur la page d'édition de sa campagne "Black Friday 2025".
Sélection des Fichiers de Contacts :
Il sélectionne un ou plusieurs fichiers JSON contenant ses listes de contacts. Le système est conçu pour gérer des fichiers de très grande taille.
Exemple : nouveaux_inscrits.json et clients_vip.json.
Configuration des Canaux d'Envoi :
Il clique sur "Ajouter un canal d'envoi" et configure une première ligne :
Expéditeur : L'équipe Promo / offres@mondomaine.com
Serveur API : Il sélectionne Brevo - Compte Principal.
Règles de diffusion :
Fréquence (min): Laisse vide pour un envoi maximal.
Max/jour: 10000 (pour respecter le quota journalier de Brevo).
Date de départ: Laisse vide pour un départ immédiat.
Il ajoute un deuxième canal d'envoi :
Expéditeur : Le Service Client / support@mondomaine.com
Serveur API : Il sélectionne SendGrid - Secours.
Règles de diffusion :
Fréquence (min): 1 (pour un envoi plus lent, 1 email par minute).
Max/jour: 5000.
Lancement Final :
L'utilisateur clique sur "Enregistrer et lancer l'import".
Le système valide toutes les informations. La campagne passe au statut scheduled (planifiée).
Un Job d'importation (ProcessCampaignImport) est immédiatement lancé en arrière-plan.

3. Importation des Contacts : Un Processus Robuste et Scalable
Le système est conçu pour importer des milliards de contacts sans jamais saturer la mémoire du serveur.
Fonctionnement Technique
Job d'Importation (ProcessCampaignImport) :
Ce Job reçoit la liste des fichiers JSON à traiter.
Il crée une table dédiée en base de données pour la campagne (ex: contacts_campaign_123) pour isoler les données et optimiser les performances.
Streaming de Données :
Grâce à la bibliothèque JsonMachine, le Job lit les fichiers JSON en streaming, c'est-à-dire qu'il ne charge jamais le fichier entier en mémoire. Il traite les contacts un par un.
Traitement par Lots (upsert) :
Les contacts sont regroupés par lots (ex: 2000 contacts) et insérés en base de données avec une seule requête upsert.
Gestion des Doublons : Si un email existe déjà dans le lot ou dans la table, il est automatiquement mis à jour avec les nouvelles informations au lieu d'être dupliqué. Cela garantit une liste de contacts toujours propre et à jour.
Fin de l'Importation :
Une fois tous les fichiers traités, le Job met à jour le nombre final de contacts dans la campagne et son statut passe de processing (en cours de traitement) à scheduled (prête à être lancée).

4. Exécution de la Campagne : Un "Essaim" de Jobs Travailleurs
C'est le cœur du système, conçu pour une vitesse et une résilience maximales. Il ne s'agit pas d'un seul script qui envoie les emails les uns après les autres.
Fonctionnement Technique
Job Orchestrateur (ProcessCampaignEmails) :
Quand la campagne est lancée (statut running), ce Job unique et léger est exécuté.
Son seul rôle est de lancer un "essaim" de Jobs travailleurs : un Job SendCampaignBatch pour chaque canal d'envoi configuré.
Dans notre scénario, il lance 2 jobs : un pour Brevo, un pour SendGrid. Ces deux jobs s'exécuteront en parallèle.
Job Travailleur (SendCampaignBatch) : C'est le bourreau de travail. Chaque instance est indépendante et gère un seul canal d'envoi.
Gestion des Règles de Diffusion :
Date de départ (scheduled_at) : S'il est dans le futur, le Job se met en pause et ne reprendra qu'à la date prévue.
Quota Journalier (max_daily_sends) : Avant chaque envoi, le Job vérifie un compteur atomique dans le cache (ex: Redis). Si le quota est atteint, le Job s'arrête. Il sera automatiquement "réveillé" le lendemain par une tâche planifiée (campaigns:relaunch-stalled).
Fréquence (send_frequency_minutes) : Après chaque envoi réussi, le Job fait une pause (sleep()) pour respecter la cadence demandée.
Consommation Mémoire Nulle : Il utilise DB::cursor() pour récupérer les contacts à envoyer. Cette méthode ne charge qu'un seul contact en mémoire à la fois, même s'il y a des milliards de lignes dans la table.
Verrouillage Atomique : Pour éviter qu'un même contact soit envoyé par deux jobs en parallèle, le Job utilise une transaction de base de données. Il crée d'abord un EmailLog pour "réserver" le contact. Si un autre job tente de faire de même, la base de données lèvera une erreur, et seul le premier job réussira.
Envoi via API HTTP : Le Job prépare les données (personnalisation, tracking) et envoie une requête HTTP POST à l'URL de l'API (Brevo, SendGrid, etc.) avec la clé API dans les en-têtes. Il n'y a aucune interaction directe avec le protocole SMTP.
Suivi de la Progression :
Après chaque envoi réussi, le Job incrémente un compteur global sent_count sur la campagne. La barre de progression est mise à jour en temps réel en comparant sent_count au nbre_contacts total.

5. Suivi et Analyse (Tracking)
Chaque email envoyé est enrichi pour permettre un suivi précis des interactions.
Pixel de Suivi : Un pixel invisible est ajouté. Son chargement appelle une URL unique qui marque l'email comme "ouvert".
Liens Trackés : Chaque lien est transformé en une URL de redirection unique. Un clic est d'abord enregistré dans la base de données avant que l'utilisateur ne soit redirigé vers la destination finale.
Désinscription : Un lien de désinscription unique est ajouté, permettant de blacklister un contact en un clic.
