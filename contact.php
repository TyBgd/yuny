<?php
/**
 * Yuny — traitement du formulaire de contact (hébergement mutualisé OVH, fonction mail()).
 */
declare(strict_types=1);

const DESTINATAIRE = 'maylis@yuny.fr';
const EXPEDITEUR   = 'noreply@yuny.fr';
const MAX_PAR_HEURE = 5;   // envois max par adresse IP et par heure
const DELAI_MIN     = 3;   // secondes minimum entre l'affichage et l'envoi

$wantsJson = stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;

function repondre(bool $ok, string $erreur = ''): void
{
    global $wantsJson;
    if ($wantsJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($ok ? ['ok' => true] : ['ok' => false, 'error' => $erreur], JSON_UNESCAPED_UNICODE);
    } else {
        header('Location: ./?envoi=' . ($ok ? 'ok' : 'erreur') . '#contact', true, 303);
    }
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: ./', true, 303);
    exit;
}

/* ---- Anti-spam ---- */
// Pot de miel : un humain ne voit pas ce champ.
if (trim((string)($_POST['site'] ?? '')) !== '') {
    repondre(true); // on fait comme si tout allait bien pour ne pas renseigner le robot
}
// Délai minimum (renseigné par le JavaScript de la page).
$elapsed = filter_var($_POST['elapsed'] ?? '', FILTER_VALIDATE_INT);
if ($elapsed === false || $elapsed < DELAI_MIN) {
    repondre(false, 'Le message n’a pas pu être envoyé. Merci de réessayer dans quelques secondes.');
}
// Limitation par IP.
$ip = $_SERVER['REMOTE_ADDR'] ?? 'inconnue';
$fichierRate = sys_get_temp_dir() . '/yuny_contact_' . hash('sha256', $ip);
$now = time();
$hits = [];
if (is_file($fichierRate)) {
    $hits = array_filter(
        array_map('intval', explode(',', (string)@file_get_contents($fichierRate))),
        fn($t) => $t > $now - 3600
    );
}
if (count($hits) >= MAX_PAR_HEURE) {
    repondre(false, 'Trop de messages envoyés. Merci de réessayer plus tard ou d’écrire à ' . DESTINATAIRE . '.');
}

/* ---- Validation ---- */
function champ(string $nom, int $max): string
{
    $v = trim((string)($_POST[$nom] ?? ''));
    $v = str_replace("\0", '', $v);
    return mb_substr($v, 0, $max, 'UTF-8');
}
// Pour les en-têtes : aucune fin de ligne (anti-injection).
function enLigne(string $v): string
{
    return trim(preg_replace('/[\r\n\t]+/', ' ', $v) ?? '');
}

$nom     = enLigne(champ('nom', 100));
$email   = enLigne(champ('email', 150));
$societe = enLigne(champ('societe', 150));
$message = champ('message', 5000);

if (mb_strlen($nom, 'UTF-8') < 2) {
    repondre(false, 'Merci d’indiquer votre nom.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    repondre(false, 'Merci d’indiquer une adresse email valide.');
}
if (mb_strlen($message, 'UTF-8') < 10) {
    repondre(false, 'Votre message est un peu court (10 caractères minimum).');
}

/* ---- Envoi ---- */
$sujet = '=?UTF-8?B?' . base64_encode('[Yuny] Nouveau message de ' . $nom) . '?=';

$corps  = "Nouveau message depuis le formulaire de yuny.fr\n";
$corps .= str_repeat('-', 48) . "\n";
$corps .= "Nom        : {$nom}\n";
$corps .= "Email      : {$email}\n";
$corps .= 'Entreprise : ' . ($societe !== '' ? $societe : '—') . "\n";
$corps .= 'Date       : ' . date('d/m/Y H:i') . "\n";
$corps .= str_repeat('-', 48) . "\n\n";
$corps .= $message . "\n\n";
$corps .= "—\nRépondez directement à ce mail pour écrire à {$email}.\n";

$headers = [
    'From: =?UTF-8?B?' . base64_encode('Site Yuny') . '?= <' . EXPEDITEUR . '>',
    'Reply-To: ' . $email,
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
    'X-Mailer: Yuny',
];

$envoye = @mail(DESTINATAIRE, $sujet, $corps, implode("\r\n", $headers), '-f' . EXPEDITEUR);

if (!$envoye) {
    repondre(false, 'Le message n’a pas pu être envoyé. Vous pouvez écrire directement à ' . DESTINATAIRE . '.');
}

$hits[] = $now;
@file_put_contents($fichierRate, implode(',', $hits), LOCK_EX);

repondre(true);
