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

    private $initials;

    public function __construct()
    {
        $this->initials = '/^(' . implode('|', array_map([$this, 'quote'], array_keys($this->pairs))) . ')/';
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

        while (strlen($result->buffer) > 0) {
            $this->resetResult($result);

            if ($result->state == '<<<') {
                if (!$this->initializeHeredoc($result)) {
                    continue;
                }
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

        if (!empty($result->statements) && trim($result->stmt) === '' && strlen($result->buffer) == 0) {
            $this->combineStatements($result);
            $this->prepareForDebug($result);
            return $result->statements;
        }
    }

    public function quote($token)
    {
        return preg_quote($token, '/');
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
            ? '/^(.*?' . preg_quote($this->pairs[$result->state], '/') . ')/s'
            : null
            ;
    }

    private function combineStatements($result)
    {
        $combined = [];

        foreach ($result->statements as $scope) {
            if (trim($scope) == ';' || substr(trim($scope), -1) != ';') {
                $combined[] = ((string) array_pop($combined)) . $scope;
            } else {
                $combined[] = $scope;
            }
        }

        $result->statements = $combined;
    }

    private function prepareForDebug($result)
    {
        $result->statements []= $this->prepareDebugStmt(array_pop($result->statements));
    }

    private function initializeHeredoc($result)
    {
        if (preg_match('/^([\'"]?)([a-z_][a-z0-9_]*)\\1/i', $result->buffer, $match)) {
            $docId = $match[2];
            $result->stmt .= $match[0];
            $result->buffer = substr($result->buffer, strlen($match[0]));

            $result->terminator = '/^(.*?\n' . $docId . ');?\n/s';

            return true;
        } else {
            return false;
        }
    }

    private function scanWsp($result)
    {
        if (preg_match('/^\s+/', $result->buffer, $match)) {
            if (!empty($result->statements) && $result->stmt === '') {
                $result->statements[] = array_pop($result->statements) . $match[0];
            } else {
                $result->stmt .= $match[0];
            }
            $result->buffer = substr($result->buffer, strlen($match[0]));

            return true;
        } else {
            return false;
        }
    }

    private function scanEscapedChar($result)
    {
        if (($result->state == '"' || $result->state == "'")
                && preg_match('/^[^' . $result->state . ']*?\\\\./s', $result->buffer, $match)) {

            $result->stmt .= $match[0];
            $result->buffer = substr($result->buffer, strlen($match[0]));

            return true;
        } else {
            return false;
        }
    }

    private function scanRegion($result)
    {
        if (in_array($result->state, ['"', "'", '<<<', '//', '#', '/*'])) {
            if (preg_match($result->terminator, $result->buffer, $match)) {
                $result->stmt .= $match[1];
                $result->buffer = substr($result->buffer, strlen($match[1]));
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
        if (preg_match($this->initials, $result->buffer, $match)) {
            $result->stmt .= $match[0];
            $result->buffer = substr($result->buffer, strlen($match[0]));
            $result->states[] = $match[0];

            return true;
        } else {
            return false;
        }
    }

    private function scanChar($result)
    {
        $chr = substr($result->buffer, 0, 1);
        $result->stmt .= $chr;
        $result->buffer = substr($result->buffer, 1);
        if ($result->state && $chr == $this->pairs[$result->state]) {
            array_pop($result->states);
        }

        if (empty($result->states) && ($chr == ';' || $chr == '}')) {
            if (!$this->isLambda($result->stmt) || $chr == ';') {
                $result->statements[] = $result->stmt;
                $result->stmt = '';
            }
        }

        return true;
    }

    private function isLambda($input)
    {
        return preg_match(
            '/^([^=]*?=\s*)?function\s*\([^\)]*\)\s*(use\s*\([^\)]*\)\s*)?\s*\{.*\}\s*;?$/is',
            trim($input)
        );
    }

    private function isReturnable($input)
    {
        $input = trim($input);
        if (substr($input, -1) == ';' && substr($input, 0, 1) != '{') {
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
        if ($this->isReturnable($input) && !preg_match('/^\s*return/i', $input)) {
            $input = sprintf('return %s', $input);
        }

        return $input;
    }
}