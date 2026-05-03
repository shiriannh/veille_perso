# TODO produit

Cette liste tient compte de l'etat courant apres les evolutions 1.6 :

- import RSS et CSV fonctionnels ;
- analyse post-RSS deterministe ;
- tags detectes separes des tags personnels ;
- tables de reference administrables via l'onglet Admin ;
- `Tag` devenu une entite metier ;
- media final promu dans `media_type` depuis les categories RSS, les tags, le media detecte, la source et le contenu ;
- `interest_level` calcule automatiquement ;
- fiches brouillon creees automatiquement pour les Entry a fort interet ;
- syntheses manuelles HTML.

La philosophie reste : local first, monolithe Symfony/Twig, pas de scraping, pas d'API publique, pas de workers distribues, pas de moteur vectoriel, pas de suppression automatique destructive.

## Priorite 1 - Fiabiliser le socle 1.6

- Ajouter une page de diagnostic "Media final" listant les Entry encore en `media_type = other`, avec source, categories RSS, detected tags, score et raison d'analyse.
- Ajouter une action de reanalyse ciblee depuis cette page pour retraiter uniquement les Entry `other`.
- Ajouter un filtre Entry par `mediaTypeOrigin` pour distinguer `manual`, `rss_category`, `detected_tags`, `detected_media_type`, `source_profile`, `unknown`.
- Ajouter un badge plus lisible sur Entry detail : media final, media detecte, origine, confiance.
- Ajouter une commande `app:media-type-report` pour afficher la repartition des medias finaux et des origines.
- Ajouter une commande `app:promote-media-types --dry-run` pour simuler les promotions sans ecriture.
- Ajouter des tests unitaires pour `MediaTypeResolver`.
- Ajouter des cas de test pour les mappings critiques : `jeu-video`, `manga`, `anime`, `bd`, `comics`, `book`, `ttrpg`, `figurines`.
- Ajouter des tests de non-regression sur la promotion depuis categories RSS : `Romans VF`, `Space Opera`, `Manga`, `Bande dessinee`, `Comics`, `TTRPG`.
- Documenter dans une page Admin les slugs media attendus et les mappings actifs.

## Priorite 2 - Terminer la migration progressive enums -> references

- Remplacer progressivement les `ChoiceType` bases sur enums par des `EntityType` bases sur les tables de reference administrables.
- Commencer par `MediaTypeReference` sur le formulaire Entry, en conservant une compatibilite avec l'ancien enum tant que necessaire.
- Ajouter sur Entry une vraie relation nullable vers `MediaTypeReference` quand la transition est stabilisee.
- Ajouter une relation vers `DecisionTypeReference` si les decisions doivent devenir entierement configurables.
- Ajouter une relation vers `ClickbaitLevelReference` si les niveaux clickbait doivent etre administres au-dela des labels.
- Ajouter une relation vers `AnalysisLanguageReference` si les langues doivent etre enrichies ou filtrees via Admin.
- Eviter de supprimer les colonnes enum avant une migration de donnees complete et verifiee.
- Ajouter une commande `app:sync-reference-fields` pour aligner les valeurs enum historiques avec les references.
- Ajouter des contraintes d'unicite et de coherence sur les references critiques si elles manquent.
- Ajouter une vue Admin "References inactives" pour retrouver rapidement les valeurs desactivees.

## Priorite 3 - Administrer le profil d'interet

- Ajouter un ecran Admin "Profil d'interet" pour eviter de modifier uniquement le code ou le YAML.
- Permettre d'ajouter des mots-cles positifs, negatifs, licences, studios, auteurs et medias preferes depuis l'interface.
- Ajouter un poids simple par mot-cle : faible, normal, fort.
- Ajouter un poids par Source : faible, normal, forte, source bruyante.
- Ajouter un champ "profil de source" administrable : livres SFF, jeux video, BD/manga/comics, manga, JDR, figurines, generaliste.
- Permettre d'associer un profil de source a une Source depuis le formulaire Source.
- Utiliser le profil de source administre dans `MediaTypeResolver` et `EntryAnalyzer`.
- Ajouter une page listant les tags actifs lies aux centres d'interet.
- Ajouter un workflow de validation des tags auto-generes : activer, renommer, fusionner, ignorer.
- Ajouter une trace simple indiquant quels tags ont ete auto-crees pendant une analyse.

## Priorite 4 - Exploiter les corrections manuelles

- Ajouter une action "Corriger le media final" directement depuis la fiche Entry.
- Quand un media est corrige manuellement, fixer `media_type_origin = manual` et empecher les futures promotions automatiques de l'ecraser.
- Ajouter une action "Corriger la decision" depuis Entry detail, avec trace dans les signaux.
- Ajouter une action "Corriger les tags detectes" ou "masquer ce tag pour cette Entry".
- Ajouter une page de suivi des faux positifs et faux negatifs corriges manuellement.
- Utiliser les corrections manuelles comme donnees de calibration deterministe, sans ML.
- Ajouter une commande `app:export-analysis-corrections` pour documenter les corrections utiles.
- Ajouter une section README expliquant comment une correction manuelle influence les futures analyses.

## Priorite 5 - Ameliorer l'exploitation quotidienne

- Ajouter une pagination simple sur les listes longues d'Entry, Source, ImportRun, Review, Tag et SynthesisReport.
- Conserver les filtres actifs apres une action de masse ou une navigation retour depuis une fiche detail.
- Ajouter un bouton "Reanalyser les resultats filtres" sur la liste Entry.
- Ajouter un bouton "Detecter les tags des resultats filtres" sur la liste Entry.
- Ajouter une vue dediee "A verifier" pour les Entry `maybe_relevant`, triees par score et `interest_level`.
- Ajouter une vue dediee "Other / non classe" pour surveiller les medias non resolus.
- Ajouter une vue dediee "Putaclic suspect" pour les Entry `suspicious` ou `clickbait`.
- Ajouter une action "Marquer comme ignore" depuis la liste Entry, avec confirmation et CSRF.
- Ajouter une action "Marquer comme pertinent" depuis la liste Entry, sans creer de Review finale.
- Afficher le dernier ImportRun directement dans la liste Source avec statut, date et compteurs.
- Afficher le nombre d'Entry par Source dans la liste Source.
- Ajouter un filtre Source "dernier import en erreur".
- Ajouter un filtre Source "active RSS uniquement".
- Ajouter un compteur dashboard d'Entry non analysees.
- Ajouter un compteur dashboard d'Entry pertinentes sans Review.
- Ajouter un compteur dashboard d'Entry jamais synthetisees.

## Priorite 6 - Fiches brouillon et workflow Review

- Ajouter une vue "Fiches brouillon auto-creees" pour traiter les brouillons generes par `interest_level` 4 ou 5.
- Ajouter un statut de Review plus complet : brouillon, a completer, terminee.
- Ajouter une date de decision sur Review.
- Ajouter un champ "prochaine action" : acheter, surveiller, lire, tester, attendre promo, ignorer.
- Ajouter une vue "A acheter / a lire / a tester" basee sur les Reviews.
- Ajouter un filtre Review par tags detectes de l'Entry liee.
- Ajouter un filtre Review par decision initiale de l'Entry liee.
- Ajouter un filtre Review par media final de l'Entry liee.
- Ajouter une comparaison entre `interest_level`, score d'analyse et verdict final de Review.
- Ajouter un bouton "Transformer le brouillon en fiche active".
- Ajouter un bouton "Refuser le brouillon" qui ne supprime pas l'Entry.

## Priorite 7 - Imports et sources

- Ajouter un bouton "Tester le flux RSS" sur la fiche Source.
- Afficher le titre du flux, la date du dernier item et le nombre d'items lus lors du test RSS.
- Ajouter une validation plus explicite de l'URL RSS : DNS, HTTP, XML invalide, flux vide.
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

## Priorite 8 - Syntheses

- Ajouter un formulaire de generation de synthese avec periode personnalisable.
- Ajouter une option d'inclusion par media final.
- Ajouter une option d'exclusion des contenus commerciaux.
- Ajouter une option d'inclusion des `maybe_relevant` avec seuil minimal de score ou d'`interest_level`.
- Ajouter une previsualisation de synthese avant validation.
- Ajouter un bouton "Regenerer cette synthese" avec les memes criteres.
- Ajouter un export Markdown.
- Ajouter un export HTML autonome imprimable.
- Ajouter un export PDF uniquement si la generation reste simple et fiable.
- Ajouter un sommaire automatique par media final.
- Ajouter une section "Signaux faibles a surveiller" pour les contenus `maybe_relevant` proches du seuil.
- Ajouter une section "Bruit editorial detecte" pour conserver les tendances clickbait hors recommandations.
- Ajouter une relation claire entre Review et SynthesisReport si une fiche enrichie remplace une Entry brute dans les syntheses futures.

## Priorite 9 - Recherche et consultation

- Ajouter une recherche texte sur titre, contenu brut, tags personnels, tags detectes, tags de reference et source.
- Ajouter un tri par score de pertinence.
- Ajouter un tri par score clickbait.
- Ajouter un tri par `interest_level`.
- Ajouter un tri par date d'analyse.
- Ajouter un filtre par categorie RSS brute.
- Ajouter un filtre "a ete synthetise / jamais synthetise".
- Ajouter un filtre "a une Review / sans Review" sur le dashboard.
- Ajouter des raccourcis de filtres predefinis : "Livres SFF", "Jeux video", "BD", "Manga", "Comics", "JDR", "Figurines".
- Ajouter un mode compact de la liste Entry.
- Ajouter un affichage plus riche des extraits RSS, tronque proprement.
- Ajouter un indicateur "source fiable / source bruyante" calcule depuis l'historique des decisions.

## Priorite 10 - Donnees, maintenance et robustesse

- Ajouter des fixtures de demonstration anonymes pour tester l'interface sans importer de vraies sources.
- Ajouter un jeu de tests fonctionnels pour les routes principales : Source, Entry, Review, ImportRun, SynthesisReport, Admin.
- Ajouter des tests de repository pour les filtres Entry et Review.
- Ajouter des tests unitaires pour `RssCategoryMapper`.
- Ajouter des tests unitaires pour `EntryTagDetector`.
- Ajouter des tests unitaires pour `EntryAnalyzer`.
- Ajouter des tests unitaires pour `InterestLevelCalculator`.
- Ajouter des tests unitaires pour `DraftReviewCreator`.
- Ajouter une commande `app:healthcheck` qui verifie base, migrations, sources actives et dernier import.
- Ajouter une commande `app:stats` pour resumer sources, entries, reviews, medias, decisions, erreurs d'import.
- Ajouter une commande de sauvegarde locale documentee pour PostgreSQL.
- Ajouter une commande de restauration locale documentee.
- Ajouter une page Admin locale listant version app, version schema, dernier import, dernier rapport et derniere analyse.
- Ajouter une politique de retention configurable pour les ImportRun anciens.
- Ajouter une politique de retention configurable pour les Entry ignorees, sans suppression automatique par defaut.

## Priorite 11 - Qualite UX

- Uniformiser les libelles entre "Entry", "Entree", "Fiche" et "Review" dans l'interface.
- Uniformiser les boutons principaux, secondaires et dangereux.
- Ajouter des messages flash plus precis avec nom de l'entite concernee.
- Ajouter des confirmations explicites pour les actions destructives ou massives.
- Ajouter une aide contextuelle courte sur les pages Import CSV, Source RSS, Analyse, Admin et Synthese.
- Ajouter une page "Aide rapide" expliquant le workflow Source -> Import -> Entry -> Analyse -> Review -> Synthese.
- Ajouter un etat vide utile sur chaque liste, avec le bouton d'action le plus probable.
- Ajouter une indication visuelle quand une page affiche des filtres actifs.
- Ajouter un bouton "Copier le lien filtre" pour partager ou reutiliser une vue.

## Fait recemment

- Import RSS avec categories `category` / `categorie`.
- Detection de tags automatiques separee des tags personnels.
- Tables de reference administrables et onglet Admin.
- Entite `Tag` avec activation/desactivation.
- Auto-enrichissement prudent de tags inactifs a valider.
- Calcul automatique de `interest_level`.
- Creation automatique de fiches brouillon pour `interest_level` 4 ou 5.
- Promotion du media final dans `media_type` depuis tags, categories RSS, media detecte, source et contenu.
- Ajout de `media_type_origin`.

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
