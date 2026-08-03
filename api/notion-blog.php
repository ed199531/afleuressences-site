<?php
/* Connecteur Notion -> blog Afleuressences.
   Meme logique que le site Localisia, portee en PHP pour OVH mutualise.

   PUBLICATION PROGRAMMEE : un article n'apparait sur le site que si
   Statut = "Publié"  ET  Date de publication <= aujourd'hui.
   Il suffit donc de passer le statut a Publié et de mettre une date future :
   l'article sortira tout seul le jour dit.

   Le token vient de api/config.local.php (exclu de git). Sans token, le blog
   affiche uniquement les articles statiques deja en place : rien ne casse.
*/

const NOTION_BLOG_DS   = 'cfef972f-5453-4a24-b91b-1e5ef427ecf8'; // base "Blog & Actualités"
const NOTION_VERSION   = '2022-06-28';
const BLOG_CACHE_TTL   = 300;   // 5 min : un article programme sort dans les 5 min

function nb_token() {
    $t = getenv('NOTION_TOKEN') ?: '';
    if ($t === '' && is_readable(__DIR__ . '/config.local.php')) {
        $c = require __DIR__ . '/config.local.php';
        if (is_array($c) && !empty($c['NOTION_TOKEN'])) $t = $c['NOTION_TOKEN'];
    }
    return $t;
}

function nb_request($method, $endpoint, $payload = null) {
    $token = nb_token();
    if ($token === '' || !function_exists('curl_init')) return null;
    $ch = curl_init('https://api.notion.com/v1' . $endpoint);
    $opt = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $token,
            'Notion-Version: ' . NOTION_VERSION,
            'Content-Type: application/json',
        ],
    ];
    if ($method === 'POST') {
        $opt[CURLOPT_POST] = true;
        $opt[CURLOPT_POSTFIELDS] = json_encode($payload === null ? new stdClass() : $payload);
    }
    curl_setopt_array($ch, $opt);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($res === false || $code !== 200) return null;
    $d = json_decode($res, true);
    return is_array($d) ? $d : null;
}

/* ---------- lecture des proprietes Notion ---------- */
function nb_prop($p, $name, $type) {
    if (!isset($p[$name])) return null;
    $v = $p[$name];
    switch ($type) {
        case 'title':     return isset($v['title'][0]['plain_text']) ? $v['title'][0]['plain_text'] : '';
        case 'rich_text': return isset($v['rich_text'][0]['plain_text']) ? $v['rich_text'][0]['plain_text'] : '';
        case 'select':    return isset($v['select']['name']) ? $v['select']['name'] : '';
        case 'date':      return isset($v['date']['start']) ? substr($v['date']['start'], 0, 10) : '';
        case 'file':
            if (!empty($v['files'][0]['file']['url']))     return $v['files'][0]['file']['url'];
            if (!empty($v['files'][0]['external']['url'])) return $v['files'][0]['external']['url'];
            return '';
    }
    return null;
}

/* ---------- rich text -> HTML ---------- */
function nb_esc($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function nb_rt($rt) {
    if (empty($rt)) return '';
    $out = '';
    foreach ($rt as $t) {
        $x = nb_esc($t['plain_text'] ?? '');
        $a = $t['annotations'] ?? [];
        if (!empty($a['code']))          $x = '<code>' . $x . '</code>';
        if (!empty($a['bold']))          $x = '<strong>' . $x . '</strong>';
        if (!empty($a['italic']))        $x = '<em>' . $x . '</em>';
        if (!empty($a['strikethrough'])) $x = '<s>' . $x . '</s>';
        if (!empty($t['href']))          $x = '<a href="' . nb_esc($t['href']) . '">' . $x . '</a>';
        $out .= $x;
    }
    return $out;
}

/* ---------- blocs Notion -> HTML ---------- */
function nb_blocks_html($blocks) {
    $html = ''; $list = null;
    $close = function () use (&$list, &$html) {
        if ($list) { $html .= "</$list>\n"; $list = null; }
    };
    foreach ((array)$blocks as $b) {
        $type = $b['type'] ?? '';
        $d    = $b[$type] ?? [];
        $txt  = nb_rt($d['rich_text'] ?? []);
        switch ($type) {
            case 'heading_1': $close(); $html .= "<h2 class=\"sub\">$txt</h2>\n"; break;
            case 'heading_2': $close(); $html .= "<h2 class=\"sub\">$txt</h2>\n"; break;
            case 'heading_3': $close(); $html .= "<h3>$txt</h3>\n"; break;
            case 'bulleted_list_item':
                if ($list !== 'ul') { $close(); $html .= "<ul class=\"practical-list\">\n"; $list = 'ul'; }
                $html .= "<li>$txt</li>\n"; break;
            case 'numbered_list_item':
                if ($list !== 'ol') { $close(); $html .= "<ol>\n"; $list = 'ol'; }
                $html .= "<li>$txt</li>\n"; break;
            case 'quote':    $close(); $html .= "<blockquote>$txt</blockquote>\n"; break;
            case 'callout':  $close(); $html .= "<p class=\"lead\"><strong>$txt</strong></p>\n"; break;
            case 'divider':  $close(); $html .= "<hr>\n"; break;
            case 'image':
                $close();
                $u = $d['file']['url'] ?? ($d['external']['url'] ?? '');
                $c = nb_rt($d['caption'] ?? []);
                if ($u) {
                    $html .= '<figure><img src="' . nb_esc($u) . '" alt="' . nb_esc(strip_tags($c)) . '" loading="lazy">';
                    if ($c) $html .= "<figcaption>$c</figcaption>";
                    $html .= "</figure>\n";
                }
                break;
            case 'paragraph':
            default:
                $close();
                if ($txt !== '') $html .= "<p>$txt</p>\n";
        }
    }
    $close();
    return $html;
}

/* ---------- requetes ---------- */
function nb_published_filter() {
    return ['and' => [
        ['property' => 'Statut',              'select' => ['equals' => 'Publié']],
        ['property' => 'Date de publication', 'date'   => ['on_or_before' => date('Y-m-d')]],
    ]];
}

function nb_map($page) {
    $p = $page['properties'] ?? [];
    return [
        'id'       => $page['id'] ?? '',
        'titre'    => nb_prop($p, 'Titre', 'title'),
        'slug'     => nb_prop($p, 'Slug', 'rich_text'),
        'resume'   => nb_prop($p, 'Résumé', 'rich_text'),
        'contenu'  => nb_prop($p, 'Contenu', 'rich_text'),
        'categorie'=> nb_prop($p, 'Catégorie', 'select'),
        'langue'   => nb_prop($p, 'Langue', 'select') ?: 'FR',
        'image'    => nb_prop($p, 'Image', 'file'),
        'date'     => nb_prop($p, 'Date de publication', 'date'),
        'source'   => 'notion',
    ];
}

/* Liste des articles publies, avec cache disque. $lang = 'FR' ou 'EN'. */
function nb_articles($lang = 'FR') {
    $cache = __DIR__ . '/blog-cache-' . strtolower($lang) . '-' . date('Y-m-d') . '.json';
    if (is_readable($cache) && (time() - filemtime($cache) < BLOG_CACHE_TTL)) {
        $c = json_decode(file_get_contents($cache), true);
        if (is_array($c)) return $c;
    }
    $d = nb_request('POST', '/data_sources/' . NOTION_BLOG_DS . '/query', [
        'filter' => nb_published_filter(),
        'sorts'  => [['property' => 'Date de publication', 'direction' => 'descending']],
        'page_size' => 100,
    ]);
    if ($d === null || !isset($d['results'])) return [];
    $out = [];
    foreach ($d['results'] as $page) {
        $a = nb_map($page);
        if ($a['slug'] === '' || $a['titre'] === '') continue;
        if ($a['langue'] !== 'FR + EN' && $a['langue'] !== $lang) continue;
        $out[] = $a;
    }
    // nettoyage des vieux caches
    foreach (glob(__DIR__ . '/blog-cache-*.json') as $f) {
        if ($f !== $cache && (time() - filemtime($f)) > 86400) @unlink($f);
    }
    @file_put_contents($cache, json_encode($out, JSON_UNESCAPED_UNICODE));
    return $out;
}

/* Un article par son slug, avec son corps HTML. */
function nb_article($slug, $lang = 'FR') {
    foreach (nb_articles($lang) as $a) {
        if ($a['slug'] === $slug) {
            $blocks = nb_request('GET', '/blocks/' . $a['id'] . '/children?page_size=100');
            $html = $blocks && isset($blocks['results']) ? nb_blocks_html($blocks['results']) : '';
            if (trim($html) === '' && $a['contenu'] !== '') {
                foreach (preg_split('/\n{2,}/', $a['contenu']) as $par) {
                    $par = trim($par);
                    if ($par !== '') $html .= '<p>' . nb_esc($par) . "</p>\n";
                }
            }
            $a['html'] = $html;
            return $a;
        }
    }
    return null;
}
