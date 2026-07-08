<?php
/* Traitement des demandes de reservation d'atelier d'art floral.
   Envoi par mail() PHP (compatible OVH mutualise). Anne-Marie recoit la demande
   avec le creneau choisi, elle valide a la main et organise le paiement (cheque, etc.). */

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

// Honeypot anti-spam
if (!empty($_POST['website'])) {
    respond(true, 'Demande envoyée.');
}

$nom       = trim($_POST['nom'] ?? '');
$prenom    = trim($_POST['prenom'] ?? '');
$email     = trim($_POST['email'] ?? '');
$telephone = trim($_POST['telephone'] ?? '');
$atelier   = trim($_POST['atelier'] ?? '');
$adultes   = trim($_POST['adultes'] ?? '0');
$enfants   = trim($_POST['enfants'] ?? '0');
$message   = trim($_POST['message'] ?? '');

if ($nom === '' || $prenom === '' || $email === '' || $telephone === '' || $atelier === '') {
    http_response_code(400);
    respond(false, 'Merci de remplir les champs obligatoires.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    respond(false, 'Adresse e-mail invalide.');
}

function clean_header($value) {
    return str_replace(["\r", "\n", "%0a", "%0d"], '', $value);
}
$nom = clean_header($nom);
$prenom = clean_header($prenom);
$email = clean_header($email);
$atelier = clean_header($atelier);

$subject = "[Site Afleuressences] Demande d'atelier : " . $atelier;

$body = "Nouvelle demande de reservation d'atelier recue le " . date('d/m/Y à H:i') . "\n\n"
      . "Atelier demandé : $atelier\n"
      . "Participants : $adultes adulte(s), $enfants enfant(s)\n\n"
      . "Nom : $nom\n"
      . "Prénom : $prenom\n"
      . "Email : $email\n"
      . "Téléphone : $telephone\n\n"
      . "Message :\n$message\n";

$headers = "From: Site Afleuressences <no-reply@afleuressences.fr>\r\n"
         . "Reply-To: $prenom $nom <$email>\r\n"
         . "Content-Type: text/plain; charset=UTF-8\r\n";

$sent = mail($DEST_EMAIL, $subject, $body, $headers);

if ($sent) {
    respond(true, 'Votre demande a bien été envoyée, Anne-Marie vous recontacte pour confirmer.');
} else {
    http_response_code(500);
    respond(false, "L'envoi a échoué, merci de réessayer ou de nous appeler directement.");
}
