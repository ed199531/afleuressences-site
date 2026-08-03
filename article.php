<?php
/* Rendu d'un article de blog issu de Notion.
   Appele par .htaccess pour /blog/<slug> quand aucun fichier statique n'existe.
   Le design vient de blog/_gabarit.html, copie conforme des articles statiques. */

require __DIR__ . '/api/notion-blog.php';

$slug = isset($_GET['slug']) ? preg_replace('/[^a-z0-9-]/', '', strtolower($_GET['slug'])) : '';
$lang = (isset($_GET['lang']) && $_GET['lang'] === 'EN') ? 'EN' : 'FR';

function blog_404() {
    http_response_code(404);
    $p = __DIR__ . '/404.html';
    echo is_readable($p) ? file_get_contents($p) : 'Article introuvable';
    exit;
}

if ($slug === '') blog_404();

$a = nb_article($slug, $lang);
if ($a === null) blog_404();

/* --- valeurs de rendu --- */
$MOIS = [1=>'janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
$dateFr = '';
if (!empty($a['date'])) {
    $ts = strtotime($a['date']);
    $dateFr = date('j', $ts) . ' ' . $MOIS[(int)date('n', $ts)] . ' ' . date('Y', $ts);
}
$mots    = str_word_count(strip_tags($a['html'] ?? ''));
$lecture = max(1, (int)round($mots / 200));
$image   = $a['image'] !== '' ? $a['image'] : '/images/og-afleuressences.jpg';
$imageAbs= (strpos($image, 'http') === 0) ? $image : 'https://afleuressences.fr' . $image;
$url     = 'https://afleuressences.fr/blog/' . $a['slug'];
$resume  = $a['resume'] !== '' ? $a['resume'] : mb_substr(trim(strip_tags($a['html'] ?? '')), 0, 155);

$jsonld = json_encode([
    '@context' => 'https://schema.org',
    '@type'    => 'BlogPosting',
    'headline' => $a['titre'],
    'description' => $resume,
    'image'    => $imageAbs,
    'datePublished' => $a['date'],
    'dateModified'  => $a['date'],
    'inLanguage'    => $lang === 'EN' ? 'en' : 'fr-FR',
    'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url],
    'author' => ['@type' => 'Person', 'name' => 'Anne-Marie Roig', 'jobTitle' => 'Artisan fleuriste'],
    'publisher' => [
        '@type' => 'Organization',
        'name'  => 'Afleuressences',
        'logo'  => ['@type' => 'ImageObject', 'url' => 'https://afleuressences.fr/images/afleuressences.png'],
    ],
    'articleSection' => $a['categorie'],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

$tpl = file_get_contents(__DIR__ . '/blog/_gabarit.html');
$html = strtr($tpl, [
    '{{TITRE}}'     => nb_esc($a['titre']),
    '{{RESUME}}'    => nb_esc($resume),
    '{{URL}}'       => nb_esc($url),
    '{{SLUG}}'      => nb_esc($a['slug']),
    '{{IMAGE}}'     => nb_esc($image),
    '{{IMAGE_ABS}}' => nb_esc($imageAbs),
    '{{CATEGORIE}}' => nb_esc($a['categorie']),
    '{{DATE_FR}}'   => nb_esc($dateFr),
    '{{LECTURE}}'   => (string)$lecture,
    '{{CORPS}}'     => $a['html'] ?? '',
    '{{JSONLD}}'    => '<script type="application/ld+json">' . $jsonld . '</script>',
]);

header('Content-Type: text/html; charset=utf-8');
echo $html;
