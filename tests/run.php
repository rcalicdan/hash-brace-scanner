<?php

declare(strict_types=1);

require __DIR__ . '/../src/Category.php';
require __DIR__ . '/../src/Classifier.php';
require __DIR__ . '/../src/Scanner.php';

use HashBraceScanner\Category;
use HashBraceScanner\Classifier;
use HashBraceScanner\Scanner;

$failures = 0;

function check(string $label, mixed $expected, mixed $actual): void
{
    global $failures;
    if ($expected === $actual) {
        echo "  ok   {$label}\n";

        return;
    }
    $failures++;
    echo "  FAIL {$label}\n       expected: " . var_export($expected, true)
        . "\n       actual:   " . var_export($actual, true) . "\n";
}

echo "Classifier\n";
check('clean annotation', Category::Clean, Classifier::classify('#{@var int}'));
check('clean nested braces', Category::Clean, Classifier::classify('#{@param array{id: int, name: string}}'));
check('trailing code', Category::TrailingCode, Classifier::classify('#{ old } $x = 1;'));
check('unterminated', Category::Unterminated, Classifier::classify('#{ no closing brace'));
check('unbalanced nested', Category::Unterminated, Classifier::classify('#{@param array{id: int}'));
check('ruby-style interpolation', Category::TrailingCode, Classifier::classify('#{name} is here'));
check('annotation-like yes', true, Classifier::isAnnotationLike('#{@var int}'));
check('annotation-like with space', true, Classifier::isAnnotationLike('#{ @var int}'));
check('annotation-like no', false, Classifier::isAnnotationLike('#{ just text }'));

echo "Scanner\n";
$dir = sys_get_temp_dir() . '/hbs-test-' . bin2hex(random_bytes(4));
mkdir($dir);
file_put_contents($dir . '/a.php', "<?php\n#{@var int}\n\$x = 1;\n#{ old } \$y = 2;\n#{ unterminated\n");
file_put_contents($dir . '/b.php', "<?php\n\$s = '#{notacomment}';\n// #{ also not at start }\n");
file_put_contents($dir . '/c.php', "<?php\n\$h = <<<TXT\n#{heredoc}\nTXT;\n");
file_put_contents($dir . '/d.txt', '#{ not php }');
file_put_contents($dir . '/e.php', "<?php\n/**\n * Docblock mentioning\n#{@var int} on its own line\n */\nfunction f() {}\n/*\n#{ in a block comment } \$z = 1;\n*/\n");

$scanner = new Scanner();
$scanner->scanDirectory($dir);
$stats = $scanner->report()['stats'];

check('files scanned (php only)', 4, $stats['files_scanned']);
check('files with hits (docblock and block comment ignored)', 1, $stats['files_with_hits']);
check('clean hits', 1, $stats['hits']['clean']);
check('trailing_code hits', 1, $stats['hits']['trailing_code']);
check('unterminated hits', 1, $stats['hits']['unterminated']);
check('breaking hits', 2, $stats['breaking_hits']);
check('annotation_like', 1, $stats['annotation_like']);

array_map('unlink', glob($dir . '/*'));
rmdir($dir);

echo $failures === 0 ? "\nAll checks passed\n" : "\n{$failures} check(s) failed\n";
exit($failures === 0 ? 0 : 1);
