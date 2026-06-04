# [mtg-multi](https://github.com/dolonet/mtg-multi) - Proxy MTProto pour Telegram (fork multi-utilisateurs)

[mtg-multi](https://github.com/dolonet/mtg-multi) est un fork de [MTG](https://github.com/9seconds/mtg) avec support multi-secret et statistiques par utilisateur. C'est un proxy MTProto autonome et très spécifique pour Telegram. Il offre un proxy rapide et léger qui permet de contourner les restrictions réseau grâce à la technique du « domain fronting ».

## Fonctionnalités

- **Support multi-utilisateurs** : plusieurs secrets par instance avec suivi des statistiques par utilisateur
- **Domain fronting** : génère automatiquement un secret lié à un véritable domaine HTTPS (par exemple `google.com`), faisant passer le trafic pour du trafic HTTPS normal
- **API de statistiques** : point de terminaison HTTP léger affichant les connexions et le trafic en direct par utilisateur
- **Limitation des connexions** : limites automatiques par utilisateur pour protéger le serveur contre les surcharges
- **Listes de blocage d'adresses IP automatiques** : télécharge et applique des listes de blocage basées sur FireHOL pour filtrer le trafic malveillant
- **Protection anti-replay** : empêche les attaques par rejeu grâce à une mise en cache intégrée
- **Métriques Prometheus** : point de terminaison `/metrics` facultatif pour la surveillance
- **Encombrement minimal** : un seul binaire, aucune dépendance d'exécution

## Fonctionnement

Une fois installé, le proxy s'exécute sur le port choisi. Les utilisateurs se connectent depuis Telegram à l'aide de la clé secrète générée. Le trafic est acheminé via une connexion de type HTTPS vers les centres de données de Telegram, contournant ainsi l'inspection approfondie des paquets.
