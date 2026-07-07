# Afleuressences — site web

Site vitrine d'Afleuressences, fleuriste artisanale à L'Union (près de Toulouse).
Refonte réalisée par Localisia. Site statique (HTML / CSS / JS), FR + EN.

## Structure
- Pages FR à la racine, pages EN sous `/en/`.
- `prestations/` : pages de prestations (mariages, fine art, événements, hommages, fleurissement de sépultures, cours d'art floral, abonnements).
- `images/gallery/` : photos curées par catégorie.
- `contact.php` : traitement du formulaire (nécessite PHP — actif sur l'hébergement OVH, inactif sur l'aperçu Vercel).
- `.htaccess` : redirections 301, URLs propres, sécurité (pour le déploiement Apache/OVH).
- `sitemap.xml`, `robots.txt`, `llms.txt` : SEO + GEO.
- `serve.js` : petit serveur Node de prévisualisation locale (`node serve.js 8766`).

## Aperçu Vercel
Déploiement statique de démonstration. Le formulaire de contact n'envoie pas d'e-mail sur Vercel (PHP requis) — cette partie fonctionne sur l'hébergement OVH final.
