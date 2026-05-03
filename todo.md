# TODO produit

Cette liste regroupe des evolutions utiles pour une application locale de veille culturelle. Elle reste volontairement pragmatique : pas de scraping, pas d'API publique, pas de workers distribues, pas de moteur vectoriel, pas de suppression automatique destructive.

## Priorite 1 - Stabiliser l'exploitation quotidienne

- Ajouter une pagination simple sur les listes longues d'Entry, Source, ImportRun et SynthesisReport.
- Conserver les filtres actifs apres une action de masse ou une navigation retour depuis une fiche detail.
- Ajouter un bouton "Reanalyser les resultats filtres" sur la liste des Entry.
- Ajouter un bouton "Detecter les tags des resultats filtres" sur la liste des Entry.
- Ajouter une vue dediee "A verifier" pour les Entry `maybe_relevant`, triees par score decroissant.
- Ajouter une vue dediee "Other / non classe" pour surveiller les Entry dont le media detecte reste `other` ou vide.
- Ajouter une vue dediee "Putaclic suspect" pour les Entry `suspicious` ou `clickbait`.
- Ajouter une action "Marquer comme ignore" depuis la liste des Entry, avec confirmation et CSRF.
- Ajouter une action "Marquer comme pertinent" depuis la liste des Entry, sans creer de Review automatiquement.
- Afficher le dernier ImportRun directement dans la liste des Source avec statut, date et compteurs.
- Afficher le nombre d'Entry par Source dans la liste des Source.
- Ajouter un filtre Source "dernier import en erreur".
- Ajouter un filtre Source "active RSS uniquement".
- Ajouter un compteur global d'Entry non analysees sur le dashboard.
- Ajouter un compteur global d'Entry sans Review et pertinentes sur le dashboard.
- Ajouter un compteur global d'Entry jamais synthetisees sur le dashboard.
- Ajouter un nettoyage visuel des badges pour distinguer clairement type manuel, type detecte, decision et putaclic.

## Priorite 2 - Ameliorer la qualite de la veille

- Ajouter un ecran simple de configuration du profil d'interet au lieu de modifier uniquement le code ou le YAML.
- Permettre d'ajouter des mots-cles positifs, negatifs, licences, studios et medias preferes depuis l'interface.
- Ajouter une notion de poids par mot-cle d'interet.
- Ajouter une notion de poids par Source pour renforcer ou diminuer son influence dans l'analyse.
- Ajouter un champ "profil de source" editable : livres SFF, jeux video, BD/manga/comics, JDR, figurines, generaliste.
- Ajouter une commande de diagnostic `app:explain-entry-analysis <entryId>` affichant les blocs de score en console.
- Ajouter une page "Pourquoi cette decision ?" plus lisible pour une Entry, avec signaux groupes par source du signal.
- Ajouter un indicateur de confiance globale de l'analyse, distinct du score de pertinence.
- Ajouter une detection plus fine des contenus commerciaux : precommande, promo, abonnement, DLC, battle pass, edition collector.
- Ajouter une categorie de decision optionnelle "commercial_signal" si les contenus commerciaux deviennent utiles a suivre sans etre des recommandations.
- Ajouter des dictionnaires dedies par domaine : jeux video, livres SFF, BD/manga/comics, JDR, figurines.
- Ajouter des tests unitaires sur les exemples metier importants : Canard PC, ActuSF, ActuaBD, categories "Romans VF", Battlefield / EA / battle pass.
- Ajouter une page de suivi des faux positifs et faux negatifs corriges manuellement.
- Utiliser les corrections manuelles comme donnees de calibration deterministe, sans ML.

## Priorite 3 - Imports et sources

- Ajouter un bouton "Tester le flux RSS" sur la fiche Source.
- Afficher le titre du flux, la date du dernier item et le nombre d'items lus lors du test RSS.
- Ajouter une validation plus explicite de l'URL RSS avec retour detaille : DNS, HTTP, XML invalide, flux vide.
- Ajouter une previsualisation d'import RSS avant creation effective des Entry.
- Ajouter une option "Importer sans analyser" et une option "Importer puis analyser".
- Ajouter une option d'import Source CSV en mode simulation, sans persistence.
- Ajouter un modele CSV telechargeable depuis la page d'import Source.
- Ajouter un rapport d'import CSV exportable en texte ou CSV lorsque des lignes sont invalides.
- Ajouter un controle de doublon Source configurable : nom seul, flux RSS seul, ou couple nom + flux.
- Ajouter un champ "priorite de source" pour ordonner les imports globaux.
- Ajouter une commande `app:import-source --dry-run <sourceId>`.
- Ajouter une commande `app:import-sources --limit=<n>` pour tester les premieres sources actives.
- Ajouter une protection contre les flux RSS trop volumineux avec limite documentee.
- Ajouter une detection des flux RSS qui changent d'URL canonique mais gardent le meme GUID.

## Priorite 4 - Reviews et workflow Entry -> Review

- Ajouter une vue "Entrees pretes a ficher" : `relevant`, sans Review, non synthetisees.
- Ajouter une action rapide "Creer une Review brouillon" depuis une Entry pertinente.
- Pre-remplir la Review avec les tags detectes, la source, l'URL, l'extrait et la raison de pertinence.
- Ajouter un statut de Review : brouillon, a completer, terminee.
- Ajouter un champ "date de decision" sur Review.
- Ajouter un champ "prochaine action" sur Review : acheter, surveiller, lire, tester, attendre promo, ignorer.
- Ajouter une vue "A acheter / a lire / a tester" basee sur les Reviews.
- Ajouter un filtre Review par tags detectes de l'Entry liee.
- Ajouter un filtre Review par decision initiale de l'Entry liee.
- Ajouter une comparaison entre score d'analyse de l'Entry et verdict final de la Review.

## Priorite 5 - Syntheses

- Ajouter un formulaire de generation de synthese avec periode personnalisable.
- Ajouter une option d'inclusion par type de media dans les syntheses.
- Ajouter une option d'exclusion des contenus commerciaux dans les syntheses.
- Ajouter une option d'inclusion des `maybe_relevant` avec seuil minimal de score.
- Ajouter une previsualisation de la synthese avant validation.
- Ajouter un bouton "Regenerer cette synthese" avec les memes criteres.
- Ajouter un export Markdown de synthese.
- Ajouter un export HTML autonome imprimable.
- Ajouter un export PDF uniquement si la generation reste simple et fiable.
- Ajouter un sommaire automatique par media dans la synthese.
- Ajouter une section "Signaux faibles a surveiller" pour les contenus `maybe_relevant` proches du seuil.
- Ajouter une section "Bruit editorial detecte" pour garder la trace des tendances clickbait sans les melanger aux recommandations.
- Ajouter une relation claire entre Review et SynthesisReport si une fiche enrichie remplace une Entry brute dans les syntheses futures.

## Priorite 6 - Recherche et consultation

- Ajouter une recherche texte sur titre, contenu brut, tags personnels, tags detectes et source.
- Ajouter un tri par score de pertinence.
- Ajouter un tri par score clickbait.
- Ajouter un tri par date d'analyse.
- Ajouter un filtre par categorie RSS brute.
- Ajouter un filtre "a ete synthetise / jamais synthetise".
- Ajouter un filtre "a une Review / sans Review" sur les vues dashboard.
- Ajouter des raccourcis de filtres predefinis : "Livres SFF", "Jeux video", "BD/Manga/Comics", "JDR", "Figurines".
- Ajouter un mode compact de la liste Entry pour lire beaucoup de lignes rapidement.
- Ajouter un affichage plus riche des extraits RSS, tronque proprement.
- Ajouter un indicateur "source fiable / source bruyante" calcule depuis l'historique des decisions.

## Priorite 7 - Donnees, maintenance et robustesse

- Ajouter des fixtures de demonstration anonymes pour tester l'interface sans importer de vraies sources.
- Ajouter un jeu de tests fonctionnels pour les routes principales : Source, Entry, Review, ImportRun, SynthesisReport.
- Ajouter des tests de repository pour les filtres Entry et Review.
- Ajouter des tests unitaires pour `RssCategoryMapper`.
- Ajouter des tests unitaires pour `EntryTagDetector`.
- Ajouter des tests unitaires pour `EntryAnalyzer`.
- Ajouter une commande `app:healthcheck` qui verifie base, migrations, sources actives et dernier import.
- Ajouter une commande `app:stats` pour resumer sources, entries, reviews, decisions, erreurs d'import.
- Ajouter une commande de sauvegarde locale documentee pour PostgreSQL.
- Ajouter une commande de restauration locale documentee.
- Ajouter une page d'administration locale listant version app, version schema, dernier import, dernier rapport et derniere analyse.
- Ajouter une migration de donnees si des valeurs historiques `comic` doivent etre reparties en `bd`, `manga` ou `comics`.
- Ajouter une politique de retention configurable pour les ImportRun anciens.
- Ajouter une politique de retention configurable pour les Entry ignorees, sans suppression automatique par defaut.

## Priorite 8 - Qualite UX

- Uniformiser les libelles entre "Entry", "Entree", "Fiche" et "Review" dans l'interface.
- Uniformiser les boutons principaux, secondaires et dangereux.
- Ajouter des messages flash plus precis avec nom de l'entite concernee.
- Ajouter des confirmations explicites pour les actions destructives ou massives.
- Ajouter une aide contextuelle courte sur les pages Import CSV, Source RSS, Analyse et Synthese.
- Ajouter une page "Aide rapide" expliquant le workflow Source -> Import -> Entry -> Analyse -> Review -> Synthese.
- Ajouter un etat vide utile sur chaque liste, avec le bouton d'action le plus probable.
- Ajouter une indication visuelle quand une page affiche des filtres actifs.
- Ajouter un bouton "Copier le lien filtre" pour partager ou reutiliser une vue.

## Hors perimetre volontaire

- Pas de scraping HTML.
- Pas d'authentification tant que l'application reste locale.
- Pas d'API publique.
- Pas de worker distribue.
- Pas de message bus obligatoire.
- Pas de moteur de recherche externe.
- Pas de vector database.
- Pas de pipeline ML.
- Pas de suppression automatique irreversible des Entry.
