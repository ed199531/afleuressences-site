<?php
/* Page liste du blog : fusionne les articles statiques deja en ligne
   et ceux publies depuis Notion, tries du plus recent au plus ancien.
   Sans token Notion, on retombe simplement sur blog.html : rien ne casse. */

require __DIR__ . '/api/notion-blog.php';

$lang     = (isset($_GET['lang']) && $_GET['lang'] === 'EN') ? 'EN' : 'FR';
$statique = __DIR__ . ($lang === 'EN' ? '/en/blog.html' : '/blog.html');
$notion   = nb_articles($lang);

if (empty($notion)) {                      // pas de token, ou aucun article Notion publie
    header('Content-Type: text/html; charset=utf-8');
    echo file_get_contents($statique);
    exit;
}

$html = file_get_contents($statique);

/* --- categorie Notion -> valeur de filtre du site --- */
$CAT = ['Mariages'=>'mariages','Conseils'=>'conseils','Saison'=>'saison',
        'Château'=>'chateaux','Actualité'=>'actualites'];
$MOIS = [1=>'janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];

function carte($a, $CAT, $MOIS, $prefixe = '/blog/') {
    $cat  = $CAT[$a['categorie']] ?? 'actualites';
    $ts   = $a['date'] ? strtotime($a['date']) : time();
    $date = date('j', $ts) . ' ' . $MOIS[(int)date('n', $ts)] . ' ' . date('Y', $ts);
    $img  = $a['image'] !== '' ? $a['image'] : '/images/og-afleuressences.jpg';
    $res  = $a['resume'] !== '' ? $a['resume'] : '';
    return '      <a class="service-card" data-category="' . nb_esc($cat) . '" href="' . $prefixe . nb_esc($a['slug'])
         . '" style="text-align:left;display:block">' . "\n"
         . '        <img src="' . nb_esc($img) . '" alt="' . nb_esc($a['titre'])
         . '" style="border-radius:6px;margin-bottom:14px;aspect-ratio:16/10;object-fit:cover;width:100%" loading="lazy">' . "\n"
         . '        <p class="blog-card-meta">' . nb_esc($a['categorie']) . ' · ' . nb_esc($date) . "</p>\n"
         . '        <h3>' . nb_esc($a['titre']) . "</h3>\n"
         . '        <p>' . nb_esc($res) . "</p>\n"
         . '        <span class="link" style="color:var(--rose);font-weight:600;font-size:13px">Lire l\'article →</span>' . "\n"
         . "      </a>\n";
}

/* --- on insere les cartes Notion en tete de grille (les plus recentes) --- */
$cartes = '';
$slugsStatiques = [];
if (preg_match_all('#href="/blog/([a-z0-9-]+)"#', $html, $m)) $slugsStatiques = $m[1];
$prefixe = $lang === 'EN' ? '/en/blog/' : '/blog/';
foreach ($notion as $a) {
    if (in_array($a['slug'], $slugsStatiques, true)) continue;   // deja en statique : on ne double pas
    $cartes .= carte($a, $CAT, $MOIS, $prefixe);
}

if ($cartes !== '') {
    $anchor = '<div class="services-grid" id="blog-grid"';
    $pos = strpos($html, $anchor);
    if ($pos !== false) {
        $fin = strpos($html, '>', $pos) + 1;
        $html = substr($html, 0, $fin) . "\n" . $cartes . substr($html, $fin);
    }
}

header('Content-Type: text/html; charset=utf-8');
echo $html;
