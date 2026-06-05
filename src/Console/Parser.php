<?php

namespace Yuga\Repl\Console;

class Parser
{
    private $pairs = [
        '('   => ')',
        '{'   => '}',
        '['   => ']',
        '"'   => '"',
        "'"   => "'",
        '//'  => "\n",
        '#'   => "\n",
        '/*'  => '*/',
        '<<<' => '_heredoc_special_case_'
    ];

    private readonly string $initials;

    public function __construct()
    {
        $this->initials = '/^(' . implode('|', array_map($this->quote(...), array_keys($this->pairs))) . ')/';
    }

    /**
     * Break the $buffer into chunks, with one for each highest-level construct possible.
     *
     * If the buffer is incomplete, returns an empty array.
     *
     * @param string $buffer
     *
     * @return array
     */
    public function statements($buffer)
    {
        $result = $this->createResult($buffer);

        while ((string) $result->buffer !== '') {
            $this->resetResult($result);

            if ($result->state == '<<<' && !$this->initializeHeredoc($result)) {
                continue;
            }

            $rules = ['scanEscapedChar', 'scanRegion', 'scanStateEntrant', 'scanWsp', 'scanChar'];

            foreach ($rules as $method) {
                if ($this->$method($result)) {
                    break;
                }
            }

            if ($result->stop) {
                break;
            }
        }

        if (!empty($result->statements) && trim($result->stmt) === '' && (string) $result->buffer === '') {
            $this->combineStatements($result);
            $this->prepareForDebug($result);
            return $result->statements;
        }
    }

    public function quote($token)
    {
        return preg_quote((string) $token, '/');
    }

    // -- Private Methods

    private function createResult($buffer)
    {
        $result = new \stdClass();
        $result->buffer     = $buffer;
        $result->stmt       = '';
        $result->state      =  null;
        $result->states     = [];
        $result->statements = [];
        $result->stop       = false;

        return $result;
    }

    private function resetResult($result)
    {
        $result->stop       = false;
        $result->state      = end($result->states);
        $result->terminator = $result->state
            ? '/^(.*?' . preg_quote((string) $this->pairs[$result->state], '/') . ')/s'
            : null
            ;
    }

    private function combineStatements($result)
    {
        $combined = [];

        foreach ($result->statements as $scope) {
            $combined[] = trim((string) $scope) == ';' || substr(trim((string) $scope), -1) != ';' ? (array_pop($combined)) . $scope : $scope;
        }

        $result->statements = $combined;
    }

    private function prepareForDebug($result)
    {
        $result->statements []= $this->prepareDebugStmt(array_pop($result->statements));
    }

    private function initializeHeredoc($result)
    {
        if (preg_match('/^([\'"]?)([a-z_][a-z0-9_]*)\\1/i', (string) $result->buffer, $match)) {
            $docId = $match[2];
            $result->stmt .= $match[0];
            $result->buffer = substr((string) $result->buffer, strlen($match[0]));

            $result->terminator = '/^(.*?\n' . $docId . ');?\n/s';

            return true;
        } else {
            return false;
        }
    }

    private function scanWsp($result)
    {
        if (preg_match('/^\s+/', (string) $result->buffer, $match)) {
            if (!empty($result->statements) && $result->stmt === '') {
                $result->statements[] = array_pop($result->statements) . $match[0];
            } else {
                $result->stmt .= $match[0];
            }
            $result->buffer = substr((string) $result->buffer, strlen($match[0]));

            return true;
        } else {
            return false;
        }
    }

    private function scanEscapedChar($result)
    {
        if (($result->state == '"' || $result->state == "'")
                && preg_match('/^[^' . $result->state . ']*?\\\\./s', (string) $result->buffer, $match)) {

            $result->stmt .= $match[0];
            $result->buffer = substr((string) $result->buffer, strlen($match[0]));

            return true;
        } else {
            return false;
        }
    }

    private function scanRegion($result)
    {
        if (in_array($result->state, ['"', "'", '<<<', '//', '#', '/*'])) {
            if (preg_match($result->terminator, (string) $result->buffer, $match)) {
                $result->stmt .= $match[1];
                $result->buffer = substr((string) $result->buffer, strlen($match[1]));
                array_pop($result->states);
            } else {
                $result->stop = true;
            }

            return true;
        } else {
            return false;
        }
    }

    private function scanStateEntrant($result)
    {
        if (preg_match($this->initials, (string) $result->buffer, $match)) {
            $result->stmt .= $match[0];
            $result->buffer = substr((string) $result->buffer, strlen($match[0]));
            $result->states[] = $match[0];

            return true;
        } else {
            return false;
        }
    }

    private function scanChar($result)
    {
        $chr = substr((string) $result->buffer, 0, 1);
        $result->stmt .= $chr;
        $result->buffer = substr((string) $result->buffer, 1);
        if ($result->state && $chr == $this->pairs[$result->state]) {
            array_pop($result->states);
        }

        if (empty($result->states) && ($chr === ';' || $chr === '}') && (!$this->isLambda($result->stmt) || $chr === ';')) {
            $result->statements[] = $result->stmt;
            $result->stmt = '';
        }

        return true;
    }

    private function isLambda($input)
    {
        return preg_match(
            '/^([^=]*?=\s*)?function\s*\([^\)]*\)\s*(use\s*\([^\)]*\)\s*)?\s*\{.*\}\s*;?$/is',
            trim((string) $input)
        );
    }

    private function isReturnable($input)
    {
        $input = trim((string) $input);
        if (str_ends_with($input, ';') && !str_starts_with($input, '{')) {
            return $this->isLambda($input) || !preg_match(
                '/^(' .
                'echo|print|exit|die|goto|global|include|include_once|require|require_once|list|' .
                'return|do|for|foreach|while|if|function|namespace|class|interface|abstract|switch|' .
                'declare|throw|try|unset' .
                ')\b/i',
                $input
            );
        } else {
            return false;
        }
    }

    private function prepareDebugStmt($input)
    {
        if ($this->isReturnable($input) && !preg_match('/^\s*return/i', (string) $input)) {
            $input = sprintf('return %s', $input);
        }

        return $input;
    }
}