# veille_perso

Outil local de veille culturelle pour repérer, centraliser, trier et annoter des œuvres.

La V1 reste volontairement simple : Symfony rend les pages HTML avec Twig, PostgreSQL stocke les sources, les entrées repérées et les fiches personnelles, Apache sert l’application, Adminer permet d’inspecter la base.

La V1.2 ajoute uniquement l’import RSS manuel : une source configurée avec une URL de flux peut être importée à la demande, sans worker, sans scraping HTML et sans API.

La V1.3 consolide l’exploitation quotidienne : filtres et tris sur les listes, dashboard plus utile, historique d’import filtrable, workflow Entry vers Review plus visible et suppressions plus sûres.

## Stack

- Symfony 7.4
- PHP 8.4
- PostgreSQL 16
- Apache 2.4
- Adminer
- Docker Compose

## Lancement local

Prérequis : Docker et Docker Compose.

```bash
docker compose up --build
```

Dans un autre terminal :

```bash
docker compose exec app php bin/console doctrine:migrations:migrate
```

Fixtures minimales facultatives :

```bash
docker compose exec app php bin/console doctrine:fixtures:load
```

URLs locales :

- Application : http://localhost:8080
- Adminer : http://localhost:8081

Connexion Adminer :

- Système : PostgreSQL
- Serveur : database
- Utilisateur : veille
- Mot de passe : veille
- Base : veille_perso

## Commandes utiles

```bash
docker compose exec app php bin/console cache:clear
docker compose exec app php bin/console doctrine:migrations:status
docker compose exec app php bin/console doctrine:schema:validate
docker compose exec app php bin/console app:import-source 1
docker compose exec app php bin/console app:import-source 1 --dry-run
docker compose exec app php bin/console app:import-source 1 --limit=10
docker compose exec app php bin/console app:import-sources
docker compose exec app php bin/console app:import-sources --limit=5
docker compose exec app php bin/console app:analyze-new-entries
docker compose exec app php bin/console app:reanalyze-entries --all
docker compose exec app php bin/console app:detect-entry-tags
docker compose exec app php bin/console app:seed-reference-data
docker compose exec app php bin/console app:generate-draft-reviews
docker compose exec app php bin/console app:media-type-report
docker compose exec app php bin/console app:promote-media-types --dry-run
docker compose exec app php bin/console app:export-analysis-corrections
docker compose exec app php bin/console app:healthcheck
docker compose exec app php bin/console app:stats
docker compose exec app php bin/console app:prune-import-runs
docker compose exec app php bin/console app:prune-import-runs --force
docker compose exec app php bin/console app:prune-ignored-entries
docker compose exec app php bin/console app:prune-ignored-entries --force
docker compose exec app vendor/bin/phpunit
```

## Donnees de demonstration

Les fixtures chargent un jeu anonyme et local : sources fictives, entrees RSS representatives, tags, imports, reviews et une synthese demo.

```bash
docker compose exec app php bin/console doctrine:fixtures:load
```

Elles ne dependent d'aucun flux externe et servent a tester l'interface ou les workflows sans utiliser de donnees personnelles.

## Exploitation locale

Deux commandes donnent une vue rapide de l'etat local :

```bash
docker compose exec app php bin/console app:healthcheck
docker compose exec app php bin/console app:stats
```

`app:healthcheck` verifie la base, la derniere migration connue, les references critiques, les sources actives, le dernier import, les Entry non analysees et les Entry encore en media `other`.

`app:stats` resume les volumes : Source, Entry, Review, brouillons, erreurs d'import recentes, repartition par media final et repartition par decision.

L'interface expose aussi `Admin > Statut local` avec les memes indicateurs utiles : version locale, schema, dernier import, derniere analyse, dernier rapport de synthese et dernier import en erreur.

## Sauvegarde et restauration PostgreSQL

Sauvegarde locale simple depuis Docker Compose :

```bash
mkdir -p backups
docker compose exec database pg_dump -U veille -d veille_perso --clean --if-exists > backups/veille_perso_$(date +%Y%m%d_%H%M%S).sql
```

Sous PowerShell, exemple equivalent :

```powershell
New-Item -ItemType Directory -Force backups
docker compose exec database pg_dump -U veille -d veille_perso --clean --if-exists | Out-File -Encoding utf8 backups\veille_perso_$(Get-Date -Format yyyyMMdd_HHmmss).sql
```

Restauration locale :

```bash
docker compose exec -T database psql -U veille -d veille_perso < backups/veille_perso.sql
docker compose exec app php bin/console doctrine:migrations:migrate
```

Avant restauration, verifier le fichier cible : cette operation remplace l'etat courant de la base.

## Retention locale

Aucune suppression n'est automatique.

Les seuils sont configurables dans `.env` :

```dotenv
IMPORT_RUN_RETENTION_DAYS=90
IGNORED_ENTRY_RETENTION_DAYS=180
```

Les commandes de nettoyage sont manuelles et en dry-run par defaut :

```bash
docker compose exec app php bin/console app:prune-import-runs
docker compose exec app php bin/console app:prune-ignored-entries
```

Pour appliquer reellement :

```bash
docker compose exec app php bin/console app:prune-import-runs --force
docker compose exec app php bin/console app:prune-ignored-entries --force
```

La retention des Entry ignorees ne cible que des Entry importees, anciennes, sans Review et sans synthese associee.

## Media final 1.6

La V1.6 recentre l'application sur un media final exploitable.

Champs utilises sur `Entry` :

- `mediaType` / colonne `media_type` : media final utilise par les filtres, le scoring, les syntheses et les fiches ;
- `detectedMediaType` / colonne `detected_media_type` : media detecte automatiquement ;
- `mediaDetectionConfidence` : confiance de detection ;
- `mediaTypeOrigin` / colonne `media_type_origin` : origine du media final (`manual`, `rss_category`, `detected_tags`, `detected_media_type`, `source_profile`, `title_content`, `imported`, `detected`, `auto`, `unknown`).

Strategie retenue : l'ancien champ `media_type` n'est pas supprime. Il devient la valeur finale. S'il vaut `other` ou provient d'une origine automatique, l'analyse peut le promouvoir. S'il est defini manuellement depuis un formulaire, il est marque `manual` et n'est pas ecrase automatiquement.

Hierarchie des signaux pour determiner le media :

1. categories RSS explicites ;
2. `detectedTags` et mappings metier maintenables ;
3. `detectedMediaType` deja calcule ;
4. profil de source ;
5. titre, contenu brut et URL.

Mappings principaux tags -> media final :

- `jeu-video`, `video_game`, `video-game`, `gaming`, `fps`, `jrpg` => `video_game` ;
- `manga` => `manga` ;
- `anime` => `anime` ;
- `bd`, `bande-dessinee` => `bd` ;
- `comics`, `comic` => `comics` ;
- `livre`, `roman`, `romans`, `novel`, `books` => `book` ;
- `jdr`, `jeu-de-role`, `ttrpg` => `ttrpg` ;
- `figurines`, `miniatures`, `wargame` => `figurines`.

Usages migrés vers le media final :

- listes et pages detail Entry ;
- filtres Entry et Review ;
- scoring de pertinence ;
- calcul de `interestLevel` ;
- syntheses et regroupement par media ;
- creation automatique de fiches brouillon ;
- imports RSS et commandes de reanalyse.

Pour migrer l'existant :

```bash
docker compose exec app php bin/console doctrine:migrations:migrate
docker compose exec app php bin/console app:reanalyze-entries --all
docker compose exec app php bin/console app:media-type-report
```

Pour fiabiliser le socle 1.6 au quotidien :

- la liste Entry permet de filtrer par origine du media final avec `mediaTypeOrigin` ;
- l'Admin expose `Diagnostic media final`, qui liste les Entry encore stockees en `media_type = other` et propose une reanalyse ciblee ;
- l'Admin expose `Mappings media actifs`, qui documente les categories RSS, tags, profils source et origines reconnus ;
- `app:media-type-report` affiche la repartition des medias finaux et des origines ;
- `app:media-type-report --output=var/media-type-report.txt` exporte le meme rapport en texte ;
- `app:promote-media-types --dry-run` simule les promotions possibles sans ecriture.

## Corrections manuelles d'analyse

La fiche Entry permet de corriger trois points sans repasser par le formulaire complet :

- media final ;
- decision finale ;
- tags detectes.

Chaque correction ajoute une ligne dans `analysis_correction` avec l'ancienne valeur, la nouvelle valeur, la raison optionnelle et la date. L'Admin expose la page `Corrections analyse` pour suivre les faux positifs, faux negatifs et ajustements utiles.

Regles d'influence :

- une correction de media force `mediaTypeOrigin` a `manual` et fixe la confiance media a 100 ;
- les futures analyses ne doivent pas ecraser un media final marque `manual` ;
- une correction de decision ajoute un signal d'analyse et complete la raison si une note est saisie ;
- une correction de tags remplace la liste `detectedTags` de cette Entry seulement, ce qui permet de masquer un tag bruité localement.

Ces corrections ne declenchent pas de ML et ne modifient pas automatiquement les dictionnaires. Elles servent de calibration deterministe : on les exporte, on observe les motifs recurrents, puis on enrichit les mappings ou le profil d'interet explicitement.

Export :

```bash
docker compose exec app php bin/console app:export-analysis-corrections
docker compose exec app php bin/console app:export-analysis-corrections --output=var/analysis-corrections.csv
```

## Workflow des fiches

Les fiches `Review` portent maintenant un workflow simple :

- `draft` : brouillon, notamment les fiches creees automatiquement depuis les Entry a fort `interestLevel` ;
- `to_complete` : fiche active a enrichir ;
- `completed` : decision prise.

Une fiche peut aussi porter une `nextAction` : acheter, regarder, lire, tester, attendre promo, surveiller ou ignorer. La date `decisionAt` est renseignee automatiquement quand une fiche passe en statut terminee si elle n'etait pas deja definie.

La liste `Fiches` permet de traiter les brouillons auto-crees, de filtrer par statut, prochaine action, media final, decision initiale de l'Entry, tag detecte et source. Les raccourcis `A acheter`, `A lire` et `A tester` sont de simples vues filtrees, sans workflow supplementaire.

Depuis un brouillon, deux actions rapides existent :

- `Transformer en fiche active` : passe le brouillon en fiche a completer ;
- `Refuser le brouillon` : marque la fiche comme terminee, verdict `Passer`, prochaine action `Ignorer`, sans supprimer l'Entry associee.

## Structure

```text
project-root/
├─ AGENTS.md
├─ docker-compose.yml
├─ README.md
├─ docker/
│  ├─ apache/
│  │  └─ vhost.conf
│  └─ php/
│     ├─ Dockerfile
│     └─ entrypoint.sh
└─ app/
   ├─ bin/
   ├─ config/
   ├─ migrations/
   ├─ public/
   ├─ src/
   │  ├─ Controller/
   │  ├─ Entity/
   │  ├─ Enum/
   │  ├─ Form/
   │  ├─ Repository/
   │  └─ DataFixtures/
   ├─ templates/
   ├─ translations/
   ├─ composer.json
   └─ .env
```

## Modèle V1

### Source

Origine d’une information : site, RSS, newsletter, chaîne, podcast ou autre source manuelle.

Une source peut rester manuelle ou passer en mode d’import RSS avec `fetchMode` et `feedUrl`. Les champs `lastFetchedAt`, `lastSuccessAt`, `lastErrorAt` et `lastErrorMessage` gardent le dernier état d’import visible depuis la fiche source.

### Entry

Contenu brut repéré. Une entrée garde la donnée d’origine dans `rawContent`, le type d’œuvre, l’URL source, un niveau d’intérêt, un statut et des tags personnels.

Les entrées importées gardent aussi les métadonnées RSS utiles : `externalId`, `canonicalUrl`, `publishedAt`, `importedAt`, `rawPayload` et `sourceHash`.

### Review

Fiche synthétique personnelle : résumé, points forts, points faibles, note personnelle, verdict et score.

Choix de relation : `Review` est liée à `Entry` en `OneToOne`. Pour la V1, l’objectif est d’avoir une fiche personnelle canonique par œuvre repérée. Une `Entry` peut exister sans `Review`, ce qui permet de capturer vite une piste avant de l’enrichir. Si une V2 demande plusieurs avis, historiques ou relectures, cette relation pourra évoluer vers un modèle `ManyToOne` ou vers une table d’historique.

Le tableau de bord affiche aussi les entrées “À ficher”, pour garder visible le stock de pistes repérées qui n’ont pas encore de jugement personnel.

### ImportRun

Historique d’un import RSS manuel : source, début, fin, statut, nombre d’items récupérés, créés, ignorés, et message d’erreur éventuel. L’historique est consultable dans l’onglet “Imports” et depuis chaque source.

## Import RSS

Configurer une source :

1. Créer ou modifier une source.
2. Choisir le mode d’import `RSS`.
3. Renseigner l’URL du flux dans `URL du flux RSS`.
4. Garder la source active.

Importer une source précise :

```bash
docker compose exec app php bin/console app:import-source <sourceId>
```

Importer toutes les sources RSS actives :

```bash
docker compose exec app php bin/console app:import-sources
```

L’interface HTML propose aussi un bouton “Importer” sur la page d’une source RSS.
La page “Imports” propose un bouton global équivalent à `app:import-sources`.

La fiche Source propose aussi :

- `Tester le flux RSS` : verifie DNS, HTTP, validite XML et flux vide, puis affiche le titre du flux, le dernier item et le nombre d'items lus ;
- `Previsualiser` : affiche les premiers items, leurs dates, categories et liens sans creer d'Entry ;
- `Importer puis analyser` : cree les nouvelles Entry et lance l'analyse post-RSS ;
- `Importer sans analyser` : cree les nouvelles Entry, detecte les tags de base et laisse l'analyse pour une commande ulterieure.

La commande `app:import-source <sourceId> --dry-run` lit le flux sans persistence. L'option `--limit=<n>` limite le nombre d'items importes ou previsualises pour cette source. La commande globale `app:import-sources --limit=<n>` limite l'import aux `n` premieres sources actives, ordonnees par priorite d'import puis par nom.

Protection volume : l'inspection RSS coupe l'apercu a 20 items et refuse les documents superieurs a 2,5 Mo. L'import effectif refuse un flux qui expose plus de 500 items afin d'eviter un import massif accidentel en usage local.

Déduplication, par priorité :

1. `externalId` : `guid` RSS ou `id` Atom.
2. URL canonique : `link` RSS ou lien Atom `alternate`.
3. Hash métier `sourceHash` : titre normalisé + URL canonique + date de publication.

Si une entrée existante correspond à l’un de ces critères pour la même source, l’item est ignoré et compté dans `skippedCount`.

Si un flux garde le meme `guid` mais change d'URL canonique, l'Entry existante est conservee, son URL canonique est rafraichie et un signal d'analyse `rss guid identique avec url canonique modifiee` est ajoute.

## Import CSV des sources

La page Sources propose un bouton `Importer un CSV`. L'import est strictement transactionnel : toutes les lignes sont validees avant insertion. Si une erreur est detectee, aucune source n'est creee. La page d'import propose aussi une simulation sans persistence, un modele CSV telechargeable et un rapport d'erreurs telechargeable quand des lignes sont invalides.

Format retenu :

- encodage UTF-8, BOM UTF-8 tolere ;
- separateur point-virgule `;` ;
- premiere ligne obligatoire avec les en-tetes exacts :

```csv
name;type;url;isActive;fetchMode;feedUrl;notes
```

Exemple valide :

```csv
name;type;url;isActive;fetchMode;feedUrl;notes
Actu SF;website;https://example.org;true;rss;https://example.org/feed.xml;Veille science-fiction
Site manuel;website;https://example.net;oui;manual;;A consulter ponctuellement
```

Valeurs autorisees :

- `type` : `website`, `rss`, `newsletter`, `youtube`, `podcast`, `social`, `other` ;
- `fetchMode` : `manual`, `rss` ;
- `isActive` : `true`, `false`, `1`, `0`, `oui`, `non`, `yes`, `no`.

Regles de validation :

- `name` obligatoire ;
- `url` et `feedUrl` doivent etre des URL valides si renseignees ;
- si `fetchMode` vaut `rss`, `feedUrl` est obligatoire ;
- si `fetchMode` ne vaut pas `rss`, `feedUrl` peut rester vide ;
- les lignes vides sont ignorees ;
- les valeurs texte sont nettoyees avec `trim` ;
- les erreurs sont remontees avec numero de ligne.

Regle de doublon configurable au moment de l'import :

- `Nom seul` : le nom de Source doit etre unique, sans tenir compte de la casse ;
- `Flux RSS seul` : l'URL de flux doit etre unique lorsqu'elle est renseignee ;
- `Nom + flux RSS` : le couple nom normalise + URL de flux normalisee doit etre unique.

Les doublons sont controles dans le CSV lui-meme et par rapport aux sources deja presentes en base. En mode simulation, les memes controles sont effectues mais aucune Source n'est persistee.

## Analyse post-RSS

La V1.5 ameliore le moteur deterministe sans changer l'architecture. Depuis `rules-v3`, l'analyse suit une hierarchie de signaux explicite :

1. import RSS ;
2. categories RSS `category` / `categorie`, conservees dans `rawPayload.categories` ;
3. profil de source, deduit du nom, de l'URL, du flux et des notes de la Source ;
4. tags detectes ;
5. titre, resume RSS, URL et source ;
6. detection de langue `fr`, `en`, `mixed` ou `unknown` ;
7. scoring media, thematique, qualite editoriale et clickbait ;
8. appel IA optionnel uniquement sur les cas ambigus ;
9. stockage de la decision finale et de ses raisons sur `Entry`.

Les entrees sont classees, jamais supprimees automatiquement.

Decisions possibles :

- `relevant` : contenu clairement pertinent ;
- `maybe_relevant` : contenu a verifier manuellement ;
- `ignored` : contenu hors profil ;
- `clickbait` : contenu classe comme putaclic.

Les champs d'analyse principaux sur `Entry` sont : `detectedMediaType`, `mediaDetectionConfidence`, `thematicScore`, `editorialQualityScore`, `normalizedTitle`, `normalizedContent`, `relevanceScore`, `clickbaitScore`, `decision`, `decisionReason`, `analysisSignals`, `matchedPositiveKeywords`, `matchedNegativeKeywords`, `clickbaitSignals`, `clickbaitLevel`, `analysisLanguage`, `aiAnalyzedAt`, `aiModel`, `aiRawResult`, `analysisVersion` et `analysisStatus`.

Le profil d'interet est configure dans `app/config/services.yaml` avec `app.analysis.positive_keywords`, `app.analysis.negative_keywords`, `app.analysis.boosted_phrases`, `app.analysis.excluded_phrases`, `app.analysis.minimum_relevance_score`, `app.analysis.clickbait_suspicion_threshold` et `app.analysis.clickbait_threshold`.

La logique de score reste simple et deterministe :

- score media : type detecte, categories RSS, profil de source, tags de media preferes, coherence avec le type manuel ;
- score thematique : dictionnaires FR / EN, genres, licences, univers, studios, editeurs suivis, profil utilisateur et categories RSS ;
- penalites : sujets a eviter et contenus a declasser ;
- qualite editoriale : bonus pour critique, analyse, interview, review ; malus pour rumeur, drama, polemique ;
- pertinence finale : combinaison media 34 %, thematique 46 %, qualite editoriale 20 %, avec bonus profil de source et penalite clickbait plus prudente.

La detection clickbait FR/EN cherche des signaux explicites : lexique sensationnaliste, promesses vides, rumeurs/polemiques, ponctuation excessive, majuscules insistantes et listes creuses.

Commandes d'analyse :

```bash
docker compose exec app php bin/console app:analyze-new-entries
docker compose exec app php bin/console app:analyze-new-entries --limit=50
docker compose exec app php bin/console app:reanalyze-entries <entryId>
docker compose exec app php bin/console app:reanalyze-entries --all
docker compose exec app php bin/console app:reanalyze-entries <entryId> --force-ai
```

L'interface Entry affiche les badges de decision, le score de pertinence, le niveau putaclic, le media final, le media detecte, l'origine, la confiance, les categories RSS exploitees, les raisons et les mots-cles detectes. La liste permet aussi de filtrer par media final, media detecte, origine du media final, decision, niveau putaclic, source, tag detecte et langue.

### IA optionnelle

L'application fonctionne sans IA. Par defaut, l'analyse IA est desactivee :

```dotenv
OPENAI_ANALYSIS_ENABLED=0
OPENAI_ANALYSIS_MODEL=gpt-5.4-mini
OPENAI_API_KEY=
```

Si `OPENAI_ANALYSIS_ENABLED=1` et `OPENAI_API_KEY` est renseignee, le service IA peut etre appele seulement quand le score de pertinence ou le score putaclic est ambigu, ou avec l'option `--force-ai`. Le resultat brut est conserve dans `aiRawResult`, avec le modele et la date d'analyse.

## Tags detectes automatiquement

Les tags personnels restent dans `personalTags`. Les tags deduits par l'application sont stockes separement dans `detectedTags`, avec une proposition prudente de type dans `detectedMediaType`. Le type manuel `mediaType` n'est jamais ecrase automatiquement.

La detection est lancee :

- a la creation d'une Entry depuis un flux RSS ;
- manuellement depuis la fiche Entry avec le bouton `Recalculer les tags` ;
- en console avec les commandes ci-dessous.

```bash
docker compose exec app php bin/console app:detect-entry-tags
docker compose exec app php bin/console app:detect-entry-tags --limit=50
docker compose exec app php bin/console app:detect-entry-tags <entryId>
docker compose exec app php bin/console app:detect-entry-tags --all
docker compose exec app php bin/console app:rebuild-entry-tags --all
```

La liste des Entry propose aussi un filtre simple par tag detecte exact, par media detecte et par langue d'analyse.

Les dictionnaires sont centralises dans `app/src/Service/EntryTagDetector.php`. Exemple de structure :

```php
'battlefield' => ['battlefield'],
'battlefield-6' => ['battlefield 6', 'battlefield vi'],
'monetisation' => ['monetisation', 'microtransaction', 'loot box'],
```

Pour enrichir la detection, ajouter un tag canonique et ses variantes dans `TAG_DICTIONARY`. Pour proposer un type d'oeuvre quand le signal est fort, ajouter le tag dans `MEDIA_TYPE_BY_TAG`. Les regles restent deterministes : pas d'IA, pas de NLP avance, pas de taxonomie multi-entites.

Le profil d'interet de l'analyse de pertinence est dans `app/src/Service/EntryAnalyzer.php`, constantes `INTERESTS`, `SOURCE_PROFILES`, `CLICKBAIT_SIGNALS` et `LANGUAGE_MARKERS`. Exemple :

```php
'licenses' => ['battlefield', 'final fantasy', 'warhammer 40k'],
'avoid' => ['people', 'celebrity', 'drama'],
'deprioritize' => ['battle pass', 'microtransaction', 'loot box'],
'video_games' => [
    'needles' => ['canard pc', 'gamekult', 'actugaming'],
    'media' => ['video_game'],
    'primaryMedia' => 'video_game',
    'tags' => ['jeu-video', 'rpg', 'fps'],
],
```

Les profils de source renforcent le media probable, les tags probables et le score de pertinence. Ils servent a reduire les faux `ignored` quand une source est deja specialisee : SFF / romans, BD / manga / comics, jeux video, JDR ou figurines.

Les categories RSS `category` et `categorie` sont conservees dans `rawPayload.categories` et passent par `app/src/Service/RssCategoryMapper.php`. Elles ont un poids fort : media detecte, tags utiles, signaux visibles dans l'analyse.

Mappings principaux :

- `roman`, `romans`, `roman-vf`, `romans-vf`, `roman-vo`, `romans-vo`, `livre`, `livres`, `novel`, `novels`, `book`, `books` => type detecte `Livre` ;
- `manga` => type detecte `Manga` ;
- `manhwa` / `manwha` => type detecte `Manhwa` ;
- `manhua` => type detecte `Manhua` ;
- `bd`, `bande dessinee` => type detecte `BD` ;
- `comics`, `comic` => type detecte `Comics` ;
- `jdr`, `jeu de role`, `ttrpg` => type detecte `JDR` ;
- `figurines`, `miniatures` => type detecte `Figurines` ;
- `anime` => type detecte `Anime` ;
- `serie`, `series` => type detecte `Serie` ;
- `jeu video`, `video game` => type detecte `Jeu video` ;
- `space opera` => tag `space-opera` ;
- `transhumanisme` ou `transhumanism` => tag `transhumanisme` ;
- `roman vf` / `romans vf` ajoutent le tag `vf`, `roman vo` / `romans vo` ajoutent le tag `vo`.

Priorite : les categories RSS peuvent renseigner `detectedMediaType` seulement s'il est encore vide. Les profils de source peuvent proposer un type uniquement quand le profil est mono-media et qu'aucune categorie RSS ne tranche. Rien n'ecrase le `mediaType` manuel.

Decision finale : `clickbait` n'est applique directement que si le bruit est fort et la pertinence faible. Un contenu coherent avec une source specialisee ou des categories RSS fortes passe plus facilement en `maybe_relevant` plutot qu'en `ignored`.

## Tables de reference et admin

La migration `Version20260503220000` introduit des tables de reference administrables :

- `media_type_reference` ;
- `tag` ;
- `source_type_reference` ;
- `fetch_mode_reference` ;
- `decision_type_reference` ;
- `clickbait_level_reference` ;
- `analysis_language_reference`.

Strategie de migration retenue : les colonnes enum historiques restent en place pour ne pas casser les filtres, imports, analyses et donnees existantes. Les nouvelles tables servent de referentiels administrables et de point d'extension. Les valeurs existantes sont migrees/seedees, puis l'application remplace progressivement les usages directs des enums par ces references.

Depuis `Version20260504120000` et `Version20260504121000`, `Entry` et `Source` possedent aussi des relations nullable vers ces references :

- `Entry.mediaTypeReference`, synchronise avec `mediaType` ;
- `Entry.decisionTypeReference`, synchronise avec `decision` ;
- `Entry.clickbaitLevelReference`, synchronise avec `clickbaitLevel` ;
- `Entry.analysisLanguageReference`, synchronise avec `analysisLanguage` ;
- `Source.sourceTypeReference`, synchronise avec `type` ;
- `Source.fetchModeReference`, synchronise avec `fetchMode`.

Les formulaires Entry et Source utilisent deja les references actives pour les champs `media type`, `source type` et `fetch mode`, tout en recopiant la valeur choisie dans l'ancien enum compatible.

Un onglet `Admin` donne acces aux CRUD sobres de ces referentiels. La suppression est volontairement remplacee par une desactivation quand la valeur peut deja etre liee a des donnees metier. La page `References inactives` permet de retrouver rapidement les valeurs desactivees.

Commande de synchronisation :

```bash
docker compose exec app php bin/console app:seed-reference-data
docker compose exec app php bin/console app:sync-reference-fields
docker compose exec app php bin/console app:sync-reference-fields --dry-run
```

## Profil d'interet administre

La priorite 3 ajoute un ecran Admin `Profil d'interet` base sur `interest_profile_rule`.

Chaque regle contient :

- une categorie : mot-cle positif, mot-cle negatif, expression favorisee, expression ecartee, media prefere, licence, studio ou auteur ;
- une valeur texte ;
- un poids simple : faible, normal ou fort ;
- un statut actif/inactif.

L'analyse continue d'utiliser les valeurs historiques de `services.yaml`, puis ajoute les regles actives gerees en base. Les poids modifient directement le score thematique : faible pèse moins, fort pèse davantage.

Les Sources peuvent aussi porter :

- un `sourceProfile` : livres SFF, jeux video, BD/manga/comics, manga, JDR, figurines, generaliste bruyant ;
- un `sourceWeight` : faible, normal, fort ou source bruyante.

Ces champs renforcent le profil de source dans `EntryAnalyzer` et `MediaTypeResolver`, sans ecraser une correction manuelle de media.

Les tags auto-generes restent inactifs par defaut. L'Admin expose :

- `Tags d'interet` pour lister les tags actifs lies au profil ;
- `A valider` pour examiner les tags auto-generes ;
- une action `Valider` qui active le tag et le marque comme lie au profil d'interet.

## Enrichissement automatique des tags

`Tag` devient une entite metier avec `name`, `slug`, `language`, `mediaType`, `isInterestRelated`, `isAutoGenerated`, `isActive`, `confidence`, `createdAt` et `updatedAt`.

Les `detectedTags` restent stockes sur `Entry` pour compatibilite et affichage rapide, mais les slugs connus sont aussi presents dans la table `tag`.

Auto-enrichissement prudent :

- le candidat vient des categories RSS ou des tags detectes ;
- le tag n'existe pas deja ;
- au moins 3 signaux convergent : media detecte, mots-cles positifs, tag pertinent deja connu, score de pertinence suffisant ;
- le tag cree automatiquement est `isAutoGenerated = true`, `isActive = false`, avec une confiance et une note ;
- il doit etre valide dans l'Admin avant de devenir un tag actif du profil.

Ce garde-fou evite de polluer la base avec chaque categorie RSS anecdotique.

## Interest level et fiches brouillon

`interestLevel` reste un entier de 0 a 5. Il est recalcule pendant l'analyse par `InterestLevelCalculator`.

Formule simplifiee :

- base = `relevanceScore * 0.70` ;
- bonus decision : `relevant` +18, `maybe_relevant` +6 ;
- malus decision : `ignored` -18, `clickbait` -35 ;
- bonus mots-cles positifs : +2 par correspondance, plafonne a +12 ;
- malus mots-cles negatifs : -5 par correspondance, plafonne a -20 ;
- bonus media detecte utile : +5 ;
- bonus coherence media manuel/detecte : +3 ;
- malus clickbait : `suspicious` -8, `clickbait` -30.

Mapping :

- 95+ => 5 ;
- 82+ => 4 ;
- 68+ => 3 ;
- 50+ => 2 ;
- 30+ => 1 ;
- sinon 0.

Si une Entry atteint `interestLevel` 4 ou 5 et ne possede pas encore de fiche, `DraftReviewCreator` cree une `Review` brouillon, pre-remplie avec le titre, la source, le lien, l'extrait, les signaux positifs et les points a verifier. L'operation est idempotente : aucune seconde fiche n'est creee si une Review existe deja.

Commandes utiles :

```bash
docker compose exec app php bin/console app:reanalyze-entries --all
docker compose exec app php bin/console app:generate-draft-reviews
docker compose exec app php bin/console app:generate-draft-reviews --limit=50
```

## Synthèses manuelles

La page `Synthèses` permet de générer manuellement une fiche récapitulative HTML imprimable.

Logique de sélection :

- inclut les Entry dont `decision` vaut `relevant` ;
- peut inclure aussi `maybe_relevant` via la case du formulaire, avec un score minimum optionnel ;
- exclut toujours `ignored` et `clickbait` ;
- ne reprend que les Entry dont `lastSynthesizedAt` est nul ;
- peut limiter la période avec `Depuis` / `Jusqu a` ;
- peut limiter à certains médias finaux ;
- peut imposer un `interestLevel` minimum ;
- peut exclure les contenus commerciaux repérés par signaux simples : promo, précommande, bon plan, battle pass, discount, offre, etc.

Logique de différentiel : la prévisualisation ne modifie rien. Au moment où une Entry est ajoutée à une synthèse générée, son champ `lastSynthesizedAt` est renseigné. Elle ne ressort donc pas dans les synthèses suivantes. Le rapport conserve sa période avec `fromDate` / `toDate`, depuis les critères saisis ou, par défaut, depuis la synthèse précédente jusqu'à la date de génération.

Chaque `SynthesisReport` garde :

- titre ;
- date de génération ;
- période couverte ;
- notes ;
- option d'inclusion des `maybe_relevant` ;
- critères de génération dans `criteria` ;
- relation avec les Entry incluses ;
- relation avec les Review liées aux Entry incluses, quand une fiche enrichie existe ;
- contenu texte généré pour trace simple.

Le rendu regroupe les Entry par type média détecté si disponible, sinon par type manuel. Pour chaque Entry, la synthèse affiche titre, source, date, lien, extrait, tags détectés, score et raison de décision.

Deux blocs d'exploitation sont ajoutés quand ils sont utiles :

- `Signaux faibles a surveiller` pour les `maybe_relevant` inclus ;
- `Bruit editorial detecte` pour les contenus pertinents mais marques `suspicious`.

Actions disponibles :

- `Previsualiser` depuis le formulaire : affiche le résultat attendu sans consommer les Entry ;
- `Generer` : crée le rapport et marque les Entry comme synthétisées ;
- `Regenerer` depuis un rapport : crée une nouvelle synthèse avec les mêmes critères ;
- export Markdown ;
- export HTML autonome imprimable.

## Consultation quotidienne

La liste des entrées accepte des filtres transmis en query string :

- recherche texte sur le titre, le contenu brut, les URL, la source, les tags personnels, les tags detectes et les categories RSS ;
- source ;
- type de média ;
- statut ;
- niveau d’intérêt ;
- présence ou absence de fiche ;
- presence ou absence dans une synthese ;
- categorie RSS brute ;
- tri par date de publication, date d’import, score de pertinence, score putaclic, `interestLevel` ou date d'analyse.

La V1.6 ajoute une pagination sobre avec choix 5 / 10 / 25 / tout sur les listes longues : entrees, sources, imports, fiches, tags Admin et syntheses.

Actions rapides Entry :

- raccourcis de vue `A verifier`, `Medias other` et `Putaclic suspect` ;
- raccourcis de vue thematiques : Livres SFF, Jeux video, BD, Manga, Comics, JDR et Figurines ;
- mode compact de la liste Entry pour parcourir rapidement les gros volumes ;
- reanalyse du filtre courant ;
- recalcul des tags detectes du filtre courant ;
- marquage manuel `Pertinent` ou `Ignore` depuis la liste, avec CSRF et retour sur les filtres actifs.

La liste Entry affiche aussi un extrait RSS tronque proprement et un indicateur de source :

- `source neuve` si moins de trois Entry analysees existent ;
- `source fiable` quand l'historique contient surtout des contenus pertinents ;
- `source mixte` quand les decisions sont contrastees ;
- `source bruyante` quand les decisions `ignored` / `clickbait` dominent.

Le tableau de bord ajoute des raccourcis directs vers les Entry avec fiche, sans fiche, jamais synthetisees et le mode compact.

La liste Source propose les filtres `Sources RSS actives uniquement` et `Dernier import en erreur`. Elle affiche aussi le dernier ImportRun connu avec statut et compteurs, ainsi que le nombre d'entrees par source.

La liste des fiches accepte aussi des filtres par verdict, score minimum, type de média, source et tri par score ou date de modification.

Le tableau de bord affiche les compteurs principaux, les dernieres entrees importees, les entrees sans fiche, les dernieres fiches modifiees, les sources dont le dernier import est en erreur, les entrees non analysees, les entrees pertinentes sans fiche et les entrees pertinentes jamais synthetisees.

## Qualite UX

L'interface reste server-rendered avec Twig et CSS local. Tailwind n'est pas ajoute pour l'instant afin de ne pas introduire de pipeline front supplementaire dans ce projet local-first.

La passe UX ajoute :

- navigation et pages responsives mobile / tablette / desktop ;
- grilles de filtres adaptatives ;
- boutons et actions empilables sur mobile ;
- tables consultables sur petits ecrans via defilement horizontal ;
- libelles principaux harmonises autour de `Entree`, `Fiche`, `Synthese` ;
- etats vides utiles avec actions probables ;
- confirmations explicites pour suppressions et actions massives ;
- indication visuelle des filtres actifs ;
- bouton de copie du lien filtre ;
- page `Aide rapide` pour rappeler le workflow Source -> Import -> Entree -> Analyse -> Fiche -> Synthese ;
- aides contextuelles courtes sur Import CSV, Source RSS, Analyse et Synthese.

## Suppressions

Les suppressions passent par un formulaire POST avec token CSRF et confirmation navigateur.

Règles métier :

- une `Source` qui possède encore des `Entry` ne peut pas être supprimée ;
- une `Entry` liée à une `Review` ne peut pas être supprimée ;
- une `Review` peut être supprimée seule ;
- il n’y a pas de cascade silencieuse Entry vers Review.

## Statuts et verdicts

Les statuts métier sont représentés par des enums PHP :

- `EntryStatus` : à tester, à surveiller, à attendre en promo, à ignorer, valeur sûre.
- `ReviewVerdict` : prioritaire, recommandé, curiosité, attendre, passer.
- `MediaType` : jeu video, livre, roman SF, fantasy, space opera, BD, manga, manhwa, manhua, comics, anime, film, serie, JDR, figurines, autre.
- `SourceType` : site web, RSS, newsletter, YouTube, podcast, réseau social, autre.

## V2 possibles

- Détection de doublons sur titre + auteur/studio + URL.
- Recherche et filtres avancés par tags, statut, média et score.
- Historique de changements de verdict.
- Export Markdown ou CSV des fiches.
- Vue “à acheter en promo”.
- Champs de dates utiles : sortie prévue, disponibilité, date de promo repérée.
- Authentification uniquement si l’usage sort du local strict.
