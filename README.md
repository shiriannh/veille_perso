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
docker compose exec app php bin/console app:import-sources
docker compose exec app php bin/console app:analyze-new-entries
docker compose exec app php bin/console app:reanalyze-entries --all
docker compose exec app php bin/console app:detect-entry-tags
```

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

Déduplication, par priorité :

1. `externalId` : `guid` RSS ou `id` Atom.
2. URL canonique : `link` RSS ou lien Atom `alternate`.
3. Hash métier `sourceHash` : titre normalisé + URL canonique + date de publication.

Si une entrée existante correspond à l’un de ces critères pour la même source, l’item est ignoré et compté dans `skippedCount`.

## Import CSV des sources

La page Sources propose un bouton `Importer un CSV`. L'import est strictement transactionnel : toutes les lignes sont validees avant insertion. Si une erreur est detectee, aucune source n'est creee.

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

Regle de doublon : le nom de Source doit etre unique, sans tenir compte de la casse, a la fois dans le CSV et par rapport aux sources deja presentes en base.

## Analyse post-RSS

La V1.4 ajoute une analyse simple apres import RSS. Depuis `rules-v2`, l'analyse est decoupee en blocs lisibles :

1. import RSS ;
2. detection de tags et proposition prudente de type media ;
3. normalisation du titre, contenu, URL et source ;
4. detection legere de langue `fr`, `en` ou `mixed` ;
5. scoring media, scoring thematique, scoring de qualite editoriale ;
6. detection heuristique du bruit / clickbait FR et EN ;
7. appel IA optionnel uniquement sur les cas ambigus ;
8. stockage de la decision finale et de ses raisons sur `Entry`.

Les entrees sont classees, jamais supprimees automatiquement.

Decisions possibles :

- `relevant` : contenu clairement pertinent ;
- `maybe_relevant` : contenu a verifier manuellement ;
- `ignored` : contenu hors profil ;
- `clickbait` : contenu classe comme putaclic.

Les champs d'analyse principaux sur `Entry` sont : `detectedMediaType`, `mediaDetectionConfidence`, `thematicScore`, `editorialQualityScore`, `normalizedTitle`, `normalizedContent`, `relevanceScore`, `clickbaitScore`, `decision`, `decisionReason`, `analysisSignals`, `matchedPositiveKeywords`, `matchedNegativeKeywords`, `clickbaitSignals`, `clickbaitLevel`, `analysisLanguage`, `aiAnalyzedAt`, `aiModel`, `aiRawResult`, `analysisVersion` et `analysisStatus`.

Le profil d'interet est configure dans `app/config/services.yaml` avec `app.analysis.positive_keywords`, `app.analysis.negative_keywords`, `app.analysis.boosted_phrases`, `app.analysis.excluded_phrases`, `app.analysis.minimum_relevance_score`, `app.analysis.clickbait_suspicion_threshold` et `app.analysis.clickbait_threshold`.

La logique de score reste simple et deterministe :

- score media : type detecte, tags de media preferes, coherence avec le type manuel ;
- score thematique : themes, licences, univers, studios et editeurs suivis ;
- penalites : sujets a eviter et contenus a declasser ;
- qualite editoriale : bonus pour critique, analyse, interview, review ; malus pour rumeur, drama, polemique ;
- pertinence finale : combinaison media 25 %, thematique 50 %, qualite editoriale 25 %, avec penalite si clickbait.

La detection clickbait FR/EN cherche des signaux explicites : lexique sensationnaliste, promesses vides, rumeurs/polemiques, ponctuation excessive, majuscules insistantes et listes creuses.

Commandes d'analyse :

```bash
docker compose exec app php bin/console app:analyze-new-entries
docker compose exec app php bin/console app:analyze-new-entries --limit=50
docker compose exec app php bin/console app:reanalyze-entries <entryId>
docker compose exec app php bin/console app:reanalyze-entries --all
docker compose exec app php bin/console app:reanalyze-entries <entryId> --force-ai
```

L'interface Entry affiche les badges de decision, le score de pertinence, le niveau putaclic, les raisons et les mots-cles detectes. La fiche detail d'une Entry propose aussi un bouton pour relancer l'analyse.

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

La liste des Entry propose aussi un filtre simple par tag detecte exact et un filtre par langue d'analyse.

Les dictionnaires sont centralises dans `app/src/Service/EntryTagDetector.php`. Exemple de structure :

```php
'battlefield' => ['battlefield'],
'battlefield-6' => ['battlefield 6', 'battlefield vi'],
'monetisation' => ['monetisation', 'microtransaction', 'loot box'],
```

Pour enrichir la detection, ajouter un tag canonique et ses variantes dans `TAG_DICTIONARY`. Pour proposer un type d'oeuvre quand le signal est fort, ajouter le tag dans `MEDIA_TYPE_BY_TAG`. Les regles restent deterministes : pas d'IA, pas de NLP avance, pas de taxonomie multi-entites.

Le profil d'interet de l'analyse de pertinence est dans `app/src/Service/EntryAnalyzer.php`, constantes `INTERESTS` et `CLICKBAIT_SIGNALS`. Exemple :

```php
'licenses' => ['battlefield', 'final fantasy', 'warhammer 40k'],
'avoid' => ['people', 'celebrity', 'drama'],
'deprioritize' => ['battle pass', 'microtransaction', 'loot box'],
```

## Consultation quotidienne

La liste des entrées accepte des filtres transmis en query string :

- recherche texte sur le titre ;
- source ;
- type de média ;
- statut ;
- niveau d’intérêt ;
- présence ou absence de fiche ;
- tri par date de publication ou date d’import.

La liste des fiches accepte aussi des filtres par verdict, score minimum, type de média, source et tri par score ou date de modification.

Le tableau de bord affiche les compteurs principaux, les dernières entrées importées, les entrées sans fiche, les dernières fiches modifiées et les sources dont le dernier import est en erreur.

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
- `MediaType` : jeu vidéo, roman SF, fantasy, space opera, BD, film, série, autre.
- `SourceType` : site web, RSS, newsletter, YouTube, podcast, réseau social, autre.

## V2 possibles

- Détection de doublons sur titre + auteur/studio + URL.
- Recherche et filtres avancés par tags, statut, média et score.
- Historique de changements de verdict.
- Export Markdown ou CSV des fiches.
- Vue “à acheter en promo”.
- Champs de dates utiles : sortie prévue, disponibilité, date de promo repérée.
- Authentification uniquement si l’usage sort du local strict.
