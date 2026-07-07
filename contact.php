<?php
/* Traitement du formulaire de contact — envoi par mail() PHP standard (compatible hébergement OVH mutualisé).
   Pas de dépendance externe, pas de service tiers. */

header('Content-Type: application/json; charset=utf-8');

$DEST_EMAIL = 'afleuressences@gmail.com';

function respond($ok, $message) {
    echo json_encode(['ok' => $ok, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    respond(false, 'Méthode non autorisée.');
}

// Honeypot anti-spam : champ caché, doit rester vide
if (!empty($_POST['website'])) {
    respond(true, 'Message envoyé.'); // réponse silencieuse pour ne pas alerter le bot
}

$nom       = trim($_POST['nom'] ?? '');
$prenom    = trim($_POST['prenom'] ?? '');
$email     = trim($_POST['email'] ?? '');
$telephone = trim($_POST['telephone'] ?? '');
$objet     = trim($_POST['objet'] ?? 'Demande générale');
$message   = trim($_POST['message'] ?? '');

if ($nom === '' || $prenom === '' || $email === '' || $telephone === '') {
    http_response_code(400);
    respond(false, 'Merci de remplir les champs obligatoires.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    respond(false, 'Adresse e-mail invalide.');
}

// Nettoyage anti-injection d'en-têtes email
function clean_header($value) {
    return str_replace(["\r", "\n", "%0a", "%0d"], '', $value);
}
$nom = clean_header($nom);
$prenom = clean_header($prenom);
$email = clean_header($email);

$subject = "[Site Afleuressences] Nouvelle demande — " . $objet;

$body = "Nouvelle demande de contact reçue le " . date('d/m/Y à H:i') . "\n\n"
      . "Nom : $nom\n"
      . "Prénom : $prenom\n"
      . "Email : $email\n"
      . "Téléphone : $telephone\n"
      . "Objet : $objet\n\n"
      . "Message :\n$message\n";

$headers = "From: Site Afleuressences <no-reply@afleuressences.fr>\r\n"
         . "Reply-To: $prenom $nom <$email>\r\n"
         . "Content-Type: text/plain; charset=UTF-8\r\n";

$sent = mail($DEST_EMAIL, $subject, $body, $headers);

if ($sent) {
    respond(true, 'Votre message a bien été envoyé, merci !');
} else {
    http_response_code(500);
    respond(false, "L'envoi a échoué, merci de réessayer ou de nous appeler directement.");
}
