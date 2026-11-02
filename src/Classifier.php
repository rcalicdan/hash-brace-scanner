<?php

declare(strict_types=1);

namespace HashBraceScanner;

final class Classifier
{
    /**
     * @param string $comment Raw T_COMMENT text, which must start with "#{".
     */
    public static function classify(string $comment): Category
    {
        $depth = 0;
        $length = \strlen($comment);

        for ($i = 1; $i < $length; $i++) {
            $char = $comment[$i];

            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    return trim(substr($comment, $i + 1)) === ''
                        ? Category::Clean
                        : Category::TrailingCode;
                }
            }
        }

        return Category::Unterminated;
    }

    /**
     * True when the comment already looks like the proposed syntax: "#{@tag ...".
     */
    public static function isAnnotationLike(string $comment): bool
    {
        return preg_match('/^#\{\s*@/', $comment) === 1;
    }
}
