<?php
declare(strict_types=1);

function remove_html_comments(string $s): string {
    return preg_replace('/<!--.*?-->/s', '', $s) ?? $s;
}

function remove_css_comments(string $s): string {
    return preg_replace('/\/\*.*?\*\//s', '', $s) ?? $s;
}

function remove_java_comments(string $s): string {
    $out = '';
    $len = strlen($s);
    $i = 0;
    $inLine = false;
    $inBlock = false;
    $inSingle = false;
    $inDouble = false;
    $escape = false;
    while ($i < $len) {
        $c = $s[$i];
        $n = $i + 1 < $len ? $s[$i + 1] : '';
        if ($inLine) {
            if ($c === "\r" || $c === "\n") {
                $out .= $c;
                $inLine = false;
            }
            $i++;
            continue;
        }
        if ($inBlock) {
            if ($c === '*' && $n === '/') {
                $inBlock = false;
                $i += 2;
                continue;
            }
            $i++;
            continue;
        }
        if ($inSingle) {
            $out .= $c;
            if ($escape) {
                $escape = false;
            } elseif ($c === '\\') {
                $escape = true;
            } elseif ($c === "'") {
                $inSingle = false;
            }
            $i++;
            continue;
        }
        if ($inDouble) {
            $out .= $c;
            if ($escape) {
                $escape = false;
            } elseif ($c === '\\') {
                $escape = true;
            } elseif ($c === '"') {
                $inDouble = false;
            }
            $i++;
            continue;
        }
        if ($c === "'") {
            $inSingle = true;
            $out .= $c;
            $i++;
            continue;
        }
        if ($c === '"') {
            $inDouble = true;
            $out .= $c;
            $i++;
            continue;
        }
        if ($c === '/' && $n === '/') {
            $inLine = true;
            $i += 2;
            continue;
        }
        if ($c === '/' && $n === '*') {
            $inBlock = true;
            $i += 2;
            continue;
        }
        $out .= $c;
        $i++;
    }
    return $out;
}

function remove_php_comments(string $code): string {
    $tokens = token_get_all($code);
    $out = '';
    foreach ($tokens as $t) {
        if (is_array($t)) {
            $id = $t[0];
            $text = $t[1];
            if ($id === T_COMMENT || $id === T_DOC_COMMENT) {
                continue;
            }
            if ($id === T_INLINE_HTML) {
                $out .= remove_html_comments($text);
                continue;
            }
            $out .= $text;
        } else {
            $out .= $t;
        }
    }
    return $out;
}

function process_file(string $path): void {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $orig = file_get_contents($path);
    if ($orig === false) {
        return;
    }
    $new = $orig;
    if ($ext === 'php') {
        $new = remove_php_comments($orig);
    } elseif ($ext === 'html' || $ext === 'htm') {
        $new = remove_html_comments($orig);
    } elseif ($ext === 'css') {
        $new = remove_css_comments($orig);
    } elseif ($ext === 'java') {
        $new = remove_java_comments($orig);
    } else {
        return;
    }
    if ($new !== $orig) {
        file_put_contents($path, $new);
        echo "Updated: $path\n";
    }
}

function run(string $root): void {
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($rii as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $path = $file->getPathname();
        if (strpos($path, DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR) !== false) {
            continue;
        }
        process_file($path);
    }
}

function run_selected(array $dirs): void {
    foreach ($dirs as $d) {
        $path = is_dir($d) ? $d : (__DIR__ . DIRECTORY_SEPARATOR . $d);
        if (is_dir($path)) {
            run($path);
        }
    }
}

run_selected(['public', 'private', 'templates', 'includes']);

