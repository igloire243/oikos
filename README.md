# Oikos — la console de l'éditeur

Clients, installations, offres, abonnements par entité, factures et licences signées du produit
**Génération Joël v2**. Voir `CLAUDE.md` pour le contexte, les règles et l'état du travail.

```bash
composer install && npm install --ignore-scripts && npm run build
cp .env.example .env && php artisan key:generate
php artisan migrate
php artisan oikos:operateur vous@exemple.test --nom="Votre nom"
php artisan serve --port=8001   # puis http://127.0.0.1:8001/console/login
```
