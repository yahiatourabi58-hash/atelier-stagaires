<?php
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/db.php';

use Cloudinary\Cloudinary;

function reponse(int $code, array $data): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    reponse(405, ['success' => false, 'message' => 'Méthode non autorisée.']);
}

// 1. Réception des données
$nom    = trim($_POST['nom'] ?? '');
$prenom = trim($_POST['prenom'] ?? '');
$ville  = trim($_POST['ville'] ?? '');

// 2. Vérifications
if ($nom === '' || $prenom === '' || $ville === '') {
    reponse(400, ['success' => false, 'message' => 'Tous les champs sont obligatoires.']);
}

if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    reponse(400, ['success' => false, 'message' => 'Photo manquante ou invalide.']);
}

$fichier = $_FILES['photo'];

if ($fichier['size'] > 5 * 1024 * 1024) {
    reponse(400, ['success' => false, 'message' => 'Photo trop lourde (5 Mo max).']);
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($fichier['tmp_name']);
$autorises = ['image/jpeg', 'image/png', 'image/webp'];

if (!in_array($mime, $autorises, true)) {
    reponse(400, ['success' => false, 'message' => 'Format autorisé : JPG, PNG ou WEBP.']);
}

try {
    // 3. Envoi vers Cloudinary (CLOUDINARY_URL lue depuis l'environnement)
    $cloudinary = new Cloudinary(getenv('CLOUDINARY_URL'));
    $upload = $cloudinary->uploadApi()->upload($fichier['tmp_name'], [
        'folder' => 'stagiaires'
    ]);

    // 4. URL sécurisée
    $photoUrl = $upload['secure_url'];

    // 5. Insertion dans MySQL
    $pdo = getDatabase();
    $stmt = $pdo->prepare(
        'INSERT INTO stagiaires (nom, prenom, ville, photo) VALUES (:nom, :prenom, :ville, :photo)'
    );
    $stmt->execute([
        ':nom'    => $nom,
        ':prenom' => $prenom,
        ':ville'  => $ville,
        ':photo'  => $photoUrl,
    ]);

    // 6. Réponse JSON
    reponse(201, [
        'success' => true,
        'message' => 'Stagiaire inscrit avec succès.',
        'photo'   => $photoUrl
    ]);
} catch (Throwable $e) {
    error_log($e->getMessage());
    reponse(500, ['success' => false, 'message' => 'Erreur serveur.']);
}
