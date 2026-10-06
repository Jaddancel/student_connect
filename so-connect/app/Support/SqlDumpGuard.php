<?php

namespace App\Support;

use RuntimeException;

/**
 * Screens an SQL dump before it is piped into the mysql CLI client.
 *
 * Besides SQL, the client runs its own local commands (`\! cmd` runs a shell
 * command, `source`/`tee`/`pager` read and write files, `connect` switches
 * servers...). mysqldump never emits these, but an uploaded file could. So we
 * mirror how the client tokenizes its input (strings, comments, delimiter) and
 * reject any client command other than DELIMITER.
 */
final class SqlDumpGuard
{
    /** Named client commands, only recognized as the first word of a statement. */
    private const NAMED_COMMANDS = [
        '?', 'charset', 'clear', 'connect', 'edit', 'ego', 'exit', 'go', 'help',
        'nopager', 'notee', 'nowarning', 'pager', 'print', 'prompt', 'query_attributes',
        'quit', 'rehash', 'resetconnection', 'source', 'ssl_session_data_print',
        'status', 'system', 'tee', 'use', 'warnings',
    ];

    /** Backslash sequences allowed outside strings: \N (NULL) and \- (MariaDB sandbox mode). */
    private const ALLOWED_BACKSLASH = ['N', '-'];

    public static function assertSafe(string $sqlPath): void
    {
        $handle = fopen($sqlPath, 'r');
        if ($handle === false) {
            throw new RuntimeException('Could not read the SQL dump.');
        }

        try {
            self::scan($handle);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  resource  $handle
     */
    private static function scan($handle): void
    {
        $delimiter = ';';
        $quote = null;
        $inComment = false;
        $statementEmpty = true;
        $lineNo = 0;

        while (($line = fgets($handle)) !== false) {
            $lineNo++;

            if ($quote === null && ! $inComment && $statementEmpty
                && preg_match('/^\s*([a-z_]+|\?)/i', $line, $m)) {
                $word = strtolower($m[1]);

                if ($word === 'delimiter') {
                    $new = preg_split('/\s+/', trim(substr(ltrim($line), 9)))[0] ?? '';
                    if ($new === '' || str_contains($new, '\\')) {
                        self::reject($lineNo);
                    }
                    $delimiter = $new;

                    continue;
                }

                if (in_array($word, self::NAMED_COMMANDS, true)) {
                    self::reject($lineNo);
                }
            }

            $len = strlen($line);
            $i = 0;

            while ($i < $len) {
                if ($inComment) {
                    $end = strpos($line, '*/', $i);
                    if ($end === false) {
                        break;
                    }
                    $inComment = false;
                    $i = $end + 2;

                    continue;
                }

                if ($quote !== null) {
                    $j = $i + strcspn($line, '\\'.$quote, $i);
                    if ($j >= $len) {
                        break;
                    }
                    if ($line[$j] === '\\') {
                        $i = $j + 2;

                        continue;
                    }
                    $quote = null;
                    $i = $j + 1;

                    continue;
                }

                $c = $line[$i];
                $next = $line[$i + 1] ?? '';

                if (ctype_space($c)) {
                    $i++;

                    continue;
                }

                if (substr_compare($line, $delimiter, $i, strlen($delimiter)) === 0) {
                    $statementEmpty = true;
                    $i += strlen($delimiter);

                    continue;
                }

                if ($c === '\\') {
                    if (! in_array($next, self::ALLOWED_BACKSLASH, true)) {
                        self::reject($lineNo);
                    }
                    $statementEmpty = false;
                    $i += 2;

                    continue;
                }

                if ($c === '#' || ($c === '-' && $next === '-' && ctype_space($line[$i + 2] ?? "\n"))) {
                    break;
                }

                // `/*!` (versioned) and `/*+` (hint) comments are sent to the
                // server as SQL, and the client still honours commands in them.
                if ($c === '/' && $next === '*' && ! in_array($line[$i + 2] ?? '', ['!', '+'], true)) {
                    $inComment = true;
                    $i += 2;

                    continue;
                }

                if ($c === "'" || $c === '"' || $c === '`') {
                    $quote = $c;
                }

                $statementEmpty = false;
                $i++;
            }
        }
    }

    private static function reject(int $lineNo): never
    {
        throw new RuntimeException(
            "The SQL dump contains a mysql client command (line {$lineNo}), which is not allowed in uploaded backups."
        );
    }
}
