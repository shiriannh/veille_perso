# veille_perso

Outil local de veille culturelle pour repérer, centraliser, trier et annoter des œuvres.

La V1 reste volontairement simple : Symfony rend les pages HTML avec Twig, PostgreSQL stocke les sources, les entrées repérées et les fiches personnelles, Apache sert l’application, Adminer permet d’inspecter la base.

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

### Entry

Contenu brut repéré. Une entrée garde la donnée d’origine dans `rawContent`, le type d’œuvre, l’URL source, un niveau d’intérêt, un statut et des tags personnels.

### Review

Fiche synthétique personnelle : résumé, points forts, points faibles, note personnelle, verdict et score.

Choix de relation : `Review` est liée à `Entry` en `OneToOne`. Pour la V1, l’objectif est d’avoir une fiche personnelle canonique par œuvre repérée. Une `Entry` peut exister sans `Review`, ce qui permet de capturer vite une piste avant de l’enrichir. Si une V2 demande plusieurs avis, historiques ou relectures, cette relation pourra évoluer vers un modèle `ManyToOne` ou vers une table d’historique.

Le tableau de bord affiche aussi les entrées “À ficher”, pour garder visible le stock de pistes repérées qui n’ont pas encore de jugement personnel.

## Statuts et verdicts

Les statuts métier sont représentés par des enums PHP :

- `EntryStatus` : à tester, à surveiller, à attendre en promo, à ignorer, valeur sûre.
- `ReviewVerdict` : prioritaire, recommandé, curiosité, attendre, passer.
- `MediaType` : jeu vidéo, roman SF, fantasy, space opera, BD, film, série, autre.
- `SourceType` : site web, RSS, newsletter, YouTube, podcast, réseau social, autre.

## V2 possibles

- Import RSS simple.
- Détection de doublons sur titre + auteur/studio + URL.
- Recherche et filtres avancés par tags, statut, média et score.
- Historique de changements de verdict.
- Export Markdown ou CSV des fiches.
- Vue “à acheter en promo”.
- Champs de dates utiles : sortie prévue, disponibilité, date de promo repérée.
- Authentification uniquement si l’usage sort du local strict.
