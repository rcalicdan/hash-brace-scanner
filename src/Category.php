<?php

declare(strict_types=1);

namespace HashBraceScanner;

/**
 * How a `#{` comment would be affected by a bounded `#{ ... }` token.
 */
enum Category: string
{
    /**
     * No matching "}" on the line: would become a parse error.
     */
    case Unterminated = 'unterminated';

    /**
     * Text follows the matching "}": the tail would become live code.
     */
    case TrailingCode = 'trailing_code';

    /**
     * Closes cleanly with nothing after: comment becomes doc-comment-like.
     */
    case Clean = 'clean';
}
