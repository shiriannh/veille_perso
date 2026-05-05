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

## Priorite 1 - Finalisee

Aucun chantier bloquant ouvert sur le socle 1.6.

Etat de suivi apres validation :

- 40 Entry restent stockees en `media_type = other` dans la base locale testee ;
- `app:promote-media-types --dry-run` ne trouve actuellement aucune promotion automatique fiable ;
- les prochains enrichissements de mappings devront partir de cas reels vus dans le diagnostic, pas de suppositions larges ;
- un export `app:media-type-report --output=...` est disponible pour conserver un avant/apres des recalibrations.

## Priorite 2 - Finalisee

Migration progressive enums -> references effectuee sans suppression des colonnes historiques.

Etat :

- Realisation : pagination partagee, actions Entry de masse, raccourcis de vues, filtres Source, dernier ImportRun par Source, compteurs dashboard et ajustement CSS `.page` ont ete implementes.

- les formulaires Entry et Source utilisent les references actives pour les choix metier principaux ;
- `Entry` possede des relations nullable vers media type, decision, clickbait level et analysis language ;
- `Source` possede des relations nullable vers source type et fetch mode ;
- les anciennes colonnes enum restent la source de compatibilite pour les filtres, imports et analyses existants ;
- `app:sync-reference-fields` synchronise les anciennes valeurs vers les nouvelles relations ;
- les slugs des referentiels restent uniques ;
- l'Admin expose une vue "References inactives".

## Priorite 3 - Finalisee

Profil d'interet administre en base et exploite par l'analyse.

Etat :

- ecran Admin "Profil d'interet" ajoute ;
- mots-cles positifs, negatifs, expressions, licences, studios, auteurs et medias preferes administrables ;
- poids simple par regle : faible, normal, fort ;
- profil et poids de Source ajoutés au formulaire Source ;
- profils de Source utilises par `MediaTypeResolver` et `EntryAnalyzer` ;
- page "Tags d'interet" ajoutee ;
- page "Tags auto-generes a valider" ajoutee ;
- action de validation des tags auto-generes ajoutee ;
- trace conservee dans les notes du tag valide ;
- pagination ajoutee sur l'ecran Entrées avec choix 5/10/25/tout ;
- boutons de la liste Entrées harmonises pour eviter les libelles sur deux lignes.

## Priorite 4 - Finalisee

Corrections manuelles d'analyse ajoutees.

Etat :

- action "Corriger le media final" disponible depuis la fiche Entry ;
- correction media marquee avec `media_type_origin = manual`, confiance media 100, et protection contre les promotions automatiques futures ;
- action "Corriger la decision" disponible depuis la fiche Entry, avec trace dans les signaux ;
- action "Corriger les tags detectes" disponible depuis la fiche Entry, utilisable aussi pour masquer un tag sur cette Entry ;
- table `analysis_correction` ajoutee pour journaliser ancienne valeur, nouvelle valeur, raison et date ;
- page Admin "Corrections analyse" ajoutee pour suivre les faux positifs et faux negatifs corriges ;
- commande `app:export-analysis-corrections` ajoutee ;
- README mis a jour sur l'influence des corrections manuelles ;
- passe UI flat design ajoutee : marges desktop limitees a 5vw/80px, pager Entry centre, tableau Entry mieux aligne, panneaux de correction harmonises.

## Priorite 5 - Finalisee

Exploitation quotidienne renforcee.

Etat :

- Pagination etendue aux autres listes longues : Source, ImportRun, Review, Tag et SynthesisReport.
- Conservation des filtres actifs apres les actions de masse et suppressions depuis les listes.
- Bouton "Reanalyser les resultats filtres" disponible sur la liste Entry.
- Bouton "Detecter les tags des resultats filtres" disponible sur la liste Entry.
- Raccourci de vue "A verifier" ajoute pour les Entry `maybe_relevant`.
- Raccourci de vue "Other / non classe" ajoute pour surveiller les medias non resolus.
- Raccourci de vue "Putaclic suspect" ajoute pour les Entry bruitees.
- Action "Marquer comme ignore" ajoutee depuis la liste Entry, avec confirmation et CSRF.
- Action "Marquer comme pertinent" ajoutee depuis la liste Entry, sans creer de Review finale.
- Dernier ImportRun affiche directement dans la liste Source avec statut et compteurs.
- Nombre d'Entry par Source affiche dans la liste Source.
- Filtre Source "dernier import en erreur" ajoute.
- Filtre Source "active RSS uniquement" ajoute.
- Compteur dashboard d'Entry non analysees ajoute.
- Compteur dashboard d'Entry pertinentes sans Review ajoute.
- Compteur dashboard d'Entry jamais synthetisees ajoute.
- `.page` recentree avec largeur maximale et marges horizontales reduites pour mieux exploiter l'ecran desktop.

## Priorite 6 - Finalisee

Workflow Review enrichi.

Etat :

- vue "Brouillons auto" ajoutee via un raccourci filtre sur les fiches auto-creees ;
- statut de Review ajoute : brouillon, a completer, terminee ;
- date de decision ajoutee sur Review ;
- champ "prochaine action" ajoute : acheter, regarder, lire, tester, attendre promo, surveiller, ignorer ;
- vues rapides "A acheter", "A lire" et "A tester" ajoutees ;
- filtre Review par tag detecte de l'Entry liee ajoute ;
- filtre Review par decision initiale de l'Entry liee ajoute ;
- filtre Review par media final conserve et enrichi dans la vue ;
- comparaison `interest_level`, score d'analyse et verdict final affichee dans la liste et la fiche Review ;
- bouton "Transformer le brouillon en fiche active" ajoute ;
- bouton "Refuser le brouillon" ajoute, sans suppression de l'Entry associee.

## Priorite 7 - Finalisee

Imports et sources fiabilises.

Etat :

- bouton "Tester le flux RSS" ajoute sur la fiche Source ;
- test RSS avec controle DNS, HTTP, XML invalide et flux vide ;
- resultat du test RSS affiche : titre du flux, date du dernier item et nombre d'items lus ;
- previsualisation RSS ajoutee avant creation effective des Entry ;
- actions separees "Importer puis analyser" et "Importer sans analyser" ;
- import Source CSV en mode simulation, sans persistence ;
- modele CSV telechargeable depuis la page d'import Source ;
- rapport d'erreurs CSV telechargeable quand des lignes sont invalides ;
- controle de doublon Source configurable : nom seul, flux RSS seul, ou couple nom + flux ;
- champ "priorite de source" ajoute pour ordonner les imports globaux ;
- commande `app:import-source --dry-run <sourceId>` ajoutee ;
- option `--limit=<n>` ajoutee sur `app:import-source` pour limiter les items et sur `app:import-sources` pour limiter les premieres sources actives ;
- protection contre les flux RSS trop volumineux documentee : inspection limitee a 2,5 Mo, import refuse au-dessus de 500 items ;
- detection des flux RSS qui changent d'URL canonique avec le meme GUID, avec rafraichissement de l'URL et signal d'analyse ;
- correction CSS de la cellule `table-actions` de la liste Entry pour conserver l'alignement vertical des boutons et des bordures de ligne.

## Priorite 8 - Finalisee

Syntheses manuelles enrichies.

Etat :

- formulaire de generation de synthese avec periode personnalisable ;
- option d'inclusion par media final ;
- option d'exclusion des contenus commerciaux ;
- option d'inclusion des `maybe_relevant` avec seuil minimal de score ;
- seuil minimal d'`interest_level` ajoute ;
- previsualisation de synthese avant validation, sans marquer les Entry comme synthetisees ;
- bouton "Regenerer" avec les memes criteres ;
- export Markdown ajoute ;
- export HTML autonome imprimable ajoute ;
- export PDF garde hors perimetre volontaire pour eviter une dependance lourde ;
- sommaire automatique par media final conserve via regroupement par media ;
- section "Signaux faibles a surveiller" ajoutee pour les contenus `maybe_relevant` inclus ;
- section "Bruit editorial detecte" ajoutee pour les contenus pertinents mais suspects ;
- les contenus commerciaux sont exclus des recommandations si l'option est cochee, a partir de signaux simples documentes ;
- relation `SynthesisReport` -> `Review` ajoutee pour tracer les fiches enrichies liees aux Entry synthetisees.

## Priorite 9 - Finalisee

Recherche et consultation renforcees.

Etat :

- recherche texte etendue au titre, contenu brut, URL, source, tags personnels, tags detectes et categories RSS ;
- tri par score de pertinence ajoute ;
- tri par score clickbait ajoute ;
- tri par `interest_level` ajoute ;
- tri par date d'analyse ajoute ;
- filtre par categorie RSS brute ajoute ;
- filtre "deja synthetisee / jamais synthetisee" ajoute ;
- raccourcis dashboard "avec fiche", "sans fiche", "a synthetiser" et "mode compact" ajoutes ;
- raccourcis de filtres predefinis ajoutes : "Livres SFF", "Jeux video", "BD", "Manga", "Comics", "JDR", "Figurines" ;
- mode compact de la liste Entry ajoute ;
- affichage plus riche des extraits RSS, tronque proprement ;
- categories RSS visibles dans la liste Entry quand elles existent ;
- indicateur "source neuve / fiable / mixte / bruyante" calcule depuis l'historique des decisions et affiche sur la liste Entry.

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

## Priorite 11 - Finalisee

Qualite UX et responsive.

Etat :

- libelles principaux harmonises autour de "Entree", "Fiche" et "Synthese" ;
- boutons principaux, secondaires et dangereux conserves et mieux adaptes au responsive ;
- messages flash affiches avec role `status` et libelles d'actions plus explicites sur les parcours touches ;
- confirmations explicites ajoutees ou renforcees sur suppressions et actions massives ;
- aide contextuelle courte ajoutee sur Import CSV, Source RSS, Analyse et Synthese ;
- page "Aide rapide" ajoutee pour expliquer le workflow Source -> Import -> Entree -> Analyse -> Fiche -> Synthese ;
- etats vides utiles ajoutes sur les listes principales avec action probable ;
- indication visuelle des filtres actifs ajoutee ;
- bouton "Copier le lien filtre" ajoute pour partager ou reutiliser une vue ;
- responsive global renforce : navigation mobile scrollable, grilles adaptatives, actions empilables, tables consultables sur petits ecrans ;
- Tailwind non retenu pour cette passe afin de rester sans pipeline front supplementaire et de ne pas casser la structure Twig existante.

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
- Page Admin "Diagnostic media final" pour surveiller les Entry encore stockees en `media_type = other`.
- Action Admin de reanalyse ciblee des Entry `other`.
- Filtre Entry par origine du media final (`mediaTypeOrigin`).
- Badge detail Entry enrichi avec media final, media detecte, origine et confiance.
- Commande `app:media-type-report` pour afficher medias finaux et origines.
- Commande `app:promote-media-types --dry-run` pour simuler les promotions sans ecriture.
- Page Admin "Mappings media actifs" documentant categories RSS, tags, profils source et origines.
- Tests unitaires ajoutes pour `MediaTypeResolver` et `RssCategoryMapper` sur les mappings critiques.
- Runner PHPUnit installe en dependance dev et valide avec `vendor/bin/phpunit`.
- Raccourci dashboard vers le diagnostic des medias `other`.
- Filtre rapide "Medias other" sur la liste Entry.
- Action "Re-analyser le filtre courant" sur la liste Entry.
- Export texte du rapport media avec `app:media-type-report --output=...`.
- Migration progressive enums vers references sur Entry et Source.
- Commande `app:sync-reference-fields`.
- Vue Admin "References inactives".
- Profil d'interet administrable.
- Profil et poids de Source.
- Validation Admin des tags auto-generes.
- Pagination et boutons harmonises sur la liste Entrées.
- Corrections manuelles de media, decision et tags depuis la fiche Entry.
- Page Admin "Corrections analyse".
- Commande `app:export-analysis-corrections`.
- Passe UI flat design : marges desktop plus lisibles, pager centre, tableau Entry aligne, panneaux sobres.
- Pagination partagee sur Sources, Imports, Fiches, Tags Admin et Syntheses.
- Actions Entry de masse : reanalyse, detection de tags, marquage pertinent/ignore.
- Filtres Source RSS active / dernier import en erreur.
- Dernier ImportRun affiche dans la liste Source.
- Nouveaux compteurs dashboard d'exploitation quotidienne.
- Marges `.page` reduites et recentrees.
- Workflow Review : statuts, prochaine action, date de decision et traitement des brouillons auto-crees.

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
