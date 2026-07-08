<?php
/* Connecteur Notion -> site pour la base "Ateliers".
   Lit la base Notion, renvoie du JSON propre pour la page Cours d'art floral.
   Fonctionne sur OVH (PHP + curl). Met en cache quelques minutes pour ne pas
   appeler Notion a chaque visite. Si la config Notion est absente ou en erreur,
   retombe automatiquement sur le fichier statique /data/ateliers.json.

   POUR ACTIVER LA SYNCHRO NOTION (a faire une fois, au passage sur OVH) :
   1. Sur notion.so/my-integrations, creer une integration interne, copier son secret.
   2. Partager la base "Ateliers" avec cette integration (menu ... > Connexions).
   3. Renseigner NOTION_TOKEN et NOTION_DATA_SOURCE_ID ci-dessous.
   Tant que NOTION_TOKEN est vide, le site affiche les ateliers du fichier JSON. */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$NOTION_TOKEN          = '';                                        // <-- coller le secret d'integration ici
$NOTION_DATA_SOURCE_ID = '3a80ba2c-0278-49b4-8061-9fb152f18b39';    // base "Ateliers"
$CACHE_FILE            = __DIR__ . '/ateliers-cache.json';
$CACHE_TTL             = 300;                                       // secondes (5 min)
$FALLBACK_FILE         = __DIR__ . '/../data/ateliers.json';

/* Renvoie le fichier statique en dernier recours */
function fallback($file) {
    if (is_readable($file)) {
        echo file_get_contents($file);
    } else {
        echo json_encode(['ateliers' => []]);
    }
    exit;
}

/* Cache encore valide ? on le sert directement */
if (is_readable($CACHE_FILE) && (time() - filemtime($CACHE_FILE) < $CACHE_TTL)) {
    echo file_get_contents($CACHE_FILE);
    exit;
}

/* Pas de token = pas de synchro Notion, on sert le JSON statique */
if ($NOTION_TOKEN === '' || !function_exists('curl_init')) {
    fallback($FALLBACK_FILE);
}

/* Appel de l'API Notion (query de la data source) */
$url = 'https://api.notion.com/v1/data_sources/' . $NOTION_DATA_SOURCE_ID . '/query';
$payload = json_encode([
    'sorts' => [['property' => 'Date', 'direction' => 'ascending']],
    'page_size' => 100,
]);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_TIMEOUT => 8,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $NOTION_TOKEN,
        'Notion-Version: 2022-06-28',
        'Content-Type: application/json',
    ],
]);
$response = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false || $code !== 200) {
    fallback($FALLBACK_FILE);
}

$data = json_decode($response, true);
if (!isset($data['results'])) {
    fallback($FALLBACK_FILE);
}

/* Petite aide pour lire une propriete Notion quel que soit son type */
function prop($p, $name, $type) {
    if (!isset($p[$name])) return null;
    $v = $p[$name];
    switch ($type) {
        case 'title':
            return isset($v['title'][0]['plain_text']) ? $v['title'][0]['plain_text'] : '';
        case 'rich_text':
            return isset($v['rich_text'][0]['plain_text']) ? $v['rich_text'][0]['plain_text'] : '';
        case 'number':
            return isset($v['number']) ? $v['number'] : null;
        case 'select':
            return isset($v['select']['name']) ? $v['select']['name'] : '';
        case 'date':
            return isset($v['date']['start']) ? $v['date']['start'] : '';
    }
    return null;
}

$ateliers = [];
foreach ($data['results'] as $page) {
    $p = $page['properties'];
    $statut = prop($p, 'Statut', 'select');
    if ($statut === 'Masqué') continue;                // on n'expose jamais les ateliers masques
    $ateliers[] = [
        'theme'        => prop($p, 'Thème', 'title'),
        'date'         => prop($p, 'Date', 'date'),
        'horaire'      => prop($p, 'Horaire', 'rich_text'),
        'tarif_adulte' => prop($p, 'Tarif adulte', 'number'),
        'tarif_enfant' => prop($p, 'Tarif enfant', 'number'),
        'statut'       => $statut,
        'langue'       => prop($p, 'Langue', 'select'),
        'description'  => prop($p, 'Description', 'rich_text'),
    ];
}

$out = json_encode(['ateliers' => $ateliers], JSON_UNESCAPED_UNICODE);
@file_put_contents($CACHE_FILE, $out);                  // on rafraichit le cache
echo $out;
