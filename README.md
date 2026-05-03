# veille_perso

Outil local de veille culturelle pour repérer, centraliser, trier et annoter des œuvres.

La V1 reste volontairement simple : Symfony rend les pages HTML avec Twig, PostgreSQL stocke les sources, les entrées repérées et les fiches personnelles, Apache sert l’application, Adminer permet d’inspecter la base.

La V1.2 ajoute uniquement l’import RSS manuel : une source configurée avec une URL de flux peut être importée à la demande, sans worker, sans scraping HTML et sans API.

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

Déduplication, par priorité :

1. `externalId` : `guid` RSS ou `id` Atom.
2. URL canonique : `link` RSS ou lien Atom `alternate`.
3. Hash métier `sourceHash` : titre normalisé + URL canonique + date de publication.

Si une entrée existante correspond à l’un de ces critères pour la même source, l’item est ignoré et compté dans `skippedCount`.

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
