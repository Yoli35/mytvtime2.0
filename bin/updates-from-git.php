<?php
/*
 * Génère les blocs <li> de templates/user/updates.html.twig à partir de l'historique git.
 *
 * Les dates de mise en prod sont lues dans les <h2> du template : une mise à jour N
 * regroupe les commits faits après la mise à jour N-1 et jusqu'à la date de N.
 *
 * Usage : php bin/updates-from-git.php [premier N] [dernier N] [--skip-empty] [--write]
 *   ex.  php bin/updates-from-git.php 81 93 > var/updates.html
 *   --skip-empty : ignore les commits sans description (ex. « Season poster images »)
 *   --write      : remplace dans le template les « The changes included in Update N will be added soon »
 *                  (les commits déjà listés dans la mise à jour sont ignorés)
 *   --indent=N   : nombre d'espaces devant <li> (24 par défaut, 12 pour index.php en prod)
 *   ex.  php bin/updates-from-git.php 81 93 --skip-empty --indent=12 > var/updates.html
 */

$args = array_values(array_filter(array_slice($argv, 1), fn($a) => !str_starts_with($a, '--')));
$skipEmpty = in_array('--skip-empty', $argv, true);
$write = in_array('--write', $argv, true);
$indent = preg_grep('/^--indent=\d+$/', $argv);
$pad = str_repeat(' ', $indent ? (int)substr(reset($indent), 9) : 24);
// Commits d'images sans intérêt (début du titre, casse ignorée), souvent suivis du nom de la série
$ignored = '/^(' . implode('|', [
    'images? map\b',      // lieux de tournage
    'episodes? stills?\b',
    'comments? images?\b',
]) . ')/i';
$from = (int)($args[0] ?? 0);
$to = (int)($args[1] ?? PHP_INT_MAX);

$root = dirname(__DIR__);
$tz = new DateTimeZone('Europe/Paris');
$templatePath = $root . '/templates/user/updates.html.twig';
$template = file_get_contents($templatePath);

// « 93° mise à jour : 21/10/2026 13:00 » ou « 75ᵉ mise à jour : 08/04/2026 14:07 » ou sans heure
preg_match_all('#<h2>(\d+)\S* mise à jour : (\d{2}/\d{2}/\d{4})(?: (\d{2}:\d{2}))?</h2>#u', $template, $matches, PREG_SET_ORDER);
$updates = [];
foreach ($matches as $m) {
    $date = DateTimeImmutable::createFromFormat('d/m/Y H:i', $m[2] . ' ' . ($m[3] ?? '23:59'), $tz);
    $updates[(int)$m[1]] = $date->getTimestamp();
}
ksort($updates);

// Séparateurs improbables dans un message de commit
$log = shell_exec('git -C ' . escapeshellarg($root) . ' log --no-merges --reverse --format=%ct%x1f%s%x1f%b%x1e');
$commits = [];
foreach (explode("\x1e", $log) as $entry) {
    $parts = explode("\x1f", trim($entry));
    if (count($parts) === 3) {
        $commits[] = ['time' => (int)$parts[0], 'subject' => trim($parts[1]), 'body' => trim(preg_replace('/\s+/', ' ', $parts[2]))];
    }
}

$e = fn(string $s) => htmlspecialchars($s, ENT_NOQUOTES);
$placeholder = fn(int $n) => '#\h*<li>\s*<div class="change">→ The changes included in Update ' . $n . ' will be added soon</div>\s*<div class="details"></div>\s*</li>\n#u';
$previous = null;
foreach ($updates as $n => $time) {
    if ($n >= $from && $n <= $to) {
        $items = array_filter($commits, fn($c) => $c['time'] <= $time && ($previous === null || $c['time'] > $previous)
            && !($skipEmpty && $c['body'] === '')
            && !preg_match($ignored, $c['subject']));
        if ($write) {
            // Contenu actuel de la mise à jour N (de son <h2> au suivant) pour ne pas dupliquer les entrées existantes
            preg_match('#<h2>' . $n . '\D.*?(?=<h2>|$)#su', $template, $section);
            $items = array_filter($items, fn($c) => !str_contains($section[0] ?? '', '→ ' . $e($c['subject']) . '</div>'));
        }
        $html = '';
        foreach (array_reverse($items) as $c) {
            $html .= "$pad<li>\n";
            $html .= "$pad    <div class=\"change\">→ {$e($c['subject'])}</div>\n";
            $html .= "$pad    <div class=\"details\">{$e($c['body'])}</div>\n";
            $html .= "$pad</li>\n";
        }
        if ($write) {
            $count = 0;
            if ($html !== '') {
                $template = preg_replace($placeholder($n), addcslashes($html, '\\$'), $template, 1, $count);
            }
            fwrite(STDERR, "{$n}° mise à jour : " . ($count ? count($items) . ' commit(s) insérés' : 'inchangée') . "\n");
        } else {
            echo "<!-- ===== {$n}° mise à jour : " . (new DateTimeImmutable("@$time"))->setTimezone($tz)->format('d/m/Y H:i') . ' — ' . count($items) . " commit(s) ===== -->\n";
            echo $html . "\n";
        }
    }
    $previous = $time;
}

if ($write) {
    file_put_contents($templatePath, $template);
}
